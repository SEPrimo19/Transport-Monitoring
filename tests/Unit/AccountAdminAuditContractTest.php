<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * The audit table records who created, reset or removed an account.
 *
 * A super_admin mints cooperative administrators and an org_admin mints drivers. Both
 * issue real sign-in credentials to real people, and none of it was recorded anywhere
 * before this. The case that matters is the one the system exists to handle: a passenger
 * files a misconduct report naming a driver, and nothing ties the driver's account
 * history to the administrator who changed it.
 *
 * These tests pin the CONTRACT — the column names, the allowed values, and the guarantee
 * that no secret is written — by reading the migration and the two writers as text. The
 * writers are a Deno Edge Function in the other repo and a controller here, so nothing in
 * PHPUnit can exercise them end to end. Reading them is weaker than running them, and it
 * is what stops a column rename in one repo silently breaking the other.
 */
class AccountAdminAuditContractTest extends TestCase
{
    private function migration(): string
    {
        $p = __DIR__.'/../../../../AndroidStudioProjects/AMOECTrack/supabase/migrations/20260823_037_account_admin_audit.sql';
        $this->assertFileExists($p, 'The audit migration is missing. It is the schema both writers target.');

        return file_get_contents($p);
    }

    private function edgeFunction(): string
    {
        $p = __DIR__.'/../../../../AndroidStudioProjects/AMOECTrack/supabase/functions/driver-account/index.ts';
        $this->assertFileExists($p);

        return file_get_contents($p);
    }

    public function test_the_controller_writes_every_column_the_migration_defines(): void
    {
        $sql = $this->migration();
        $php = file_get_contents(__DIR__.'/../../app/Http/Controllers/OrgAdminController.php');

        foreach (['actor_profile_id', 'actor_role', 'target_profile_id', 'target_identifier',
            'target_kind', 'action', 'organization_id', 'succeeded'] as $column) {
            $this->assertStringContainsString($column, $sql, "Migration is missing {$column}.");
            $this->assertStringContainsString($column, $php, "OrgAdminController does not write {$column}.");
        }
    }

    public function test_every_action_the_writers_use_is_permitted_by_the_check_constraint(): void
    {
        $sql = $this->migration();
        $ts = $this->edgeFunction();

        // A value the constraint rejects makes the insert throw. Both writers swallow that
        // to protect the operation they are recording, so the event would vanish silently
        // and the table would look healthy while recording nothing.
        // Scan only inside recordEvent calls. The file's header comments document the
        // dispatch actions in the same `action: "..."` shape, and matching those would
        // assert the constraint permits values nothing ever writes.
        preg_match_all('/recordEvent\(.*?
  \}\);/s', $ts, $calls);
        preg_match_all('/action:\s*"([a-z_]+)"/', implode('
', $calls[0]), $m);
        $used = array_unique($m[1]);
        $this->assertNotEmpty($used, 'No recordEvent action literals found in the Edge Function.');

        foreach ([...$used, 'deactivate'] as $action) {
            $this->assertMatchesRegularExpression(
                "/check \(action in \([^)]*'".preg_quote($action, '/')."'/s",
                $sql,
                "'{$action}' is written but not allowed by the action check constraint."
            );
        }
    }

    public function test_the_table_is_append_only_through_the_api(): void
    {
        $sql = $this->migration();

        // Exactly one policy, for SELECT. An audit record an actor can edit or delete is
        // not an audit record; only service_role and the database owner bypass RLS.
        $this->assertSame(1, preg_match_all('/create policy/i', $sql),
            'The audit table should declare exactly one policy.');
        $this->assertMatchesRegularExpression('/for\s+select/i', $sql);
        $this->assertDoesNotMatchRegularExpression('/for\s+(insert|update|delete|all)\b/i', $sql,
            'A write policy would let a client forge or erase its own audit trail.');
        $this->assertMatchesRegularExpression('/enable row level security/i', $sql);
    }

    public function test_no_secret_is_ever_written_to_the_audit_table(): void
    {
        $ts = $this->edgeFunction();

        // Isolate the recorder's call sites and assert no password variable reaches them.
        preg_match_all('/recordEvent\(.*?
  \}\);/s', $ts, $m);
        $this->assertNotEmpty($m[0], 'No recordEvent call sites found.');

        foreach ($m[0] as $call) {
            // The ACTION NAMES legitimately contain the word, so strip those first: the
            // thing being asserted is that no password VALUE reaches the table, not that
            // the word never appears.
            $body = str_replace(['"reset_password"', '"reset_org_admin_password"'], '""', $call);

            foreach (['password', 'token', 'hash', 'serviceRoleKey'] as $secret) {
                $this->assertStringNotContainsStringIgnoringCase($secret, $body,
                    "A recordEvent call references '{$secret}'. The audit records THAT a ".
                    'password was set, never what it was set to.');
            }
        }
    }

    public function test_deleted_accounts_keep_their_history(): void
    {
        $sql = $this->migration();

        // Removing someone must not erase the evidence that they were removed, which is
        // exactly when the record matters most. Both profile FKs null out instead.
        $this->assertSame(2, preg_match_all('/references public\.profiles \(id\) on delete set null/i', $sql),
            'Both profile references must be ON DELETE SET NULL, not CASCADE.');
        $this->assertStringContainsString('target_identifier', $sql,
            'Without a denormalised identifier, a nulled FK leaves an event naming nobody.');
    }
}
