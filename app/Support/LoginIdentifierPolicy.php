<?php

namespace App\Support;

use App\Http\Middleware\AuthenticateSupabaseAdmin;

/**
 * Turns whatever an admin types into this app's own "Email or username" sign-in
 * field into the address GoTrue actually authenticates against.
 *
 * Web-admin sibling of the mobile app's `LoginIdentifierPolicy.kt` (Android repo:
 * app/src/main/java/com/example/amoectrack/core/common/LoginIdentifierPolicy.kt) —
 * same shape, different domain. That mobile policy resolves a DRIVER's
 * admin-created username under `drivers.amoectrack.invalid`; this one resolves an
 * ORG ADMIN's under {@see OrgAdminAccountEmail::DOMAIN} (`admins.amoectrack.invalid`).
 *
 * WHY THIS DOESN'T ALSO HANDLE DRIVER USERNAMES — AND WHY THAT'S NOT A GAP: drivers
 * sign in on the mobile app, never here. {@see AuthenticateSupabaseAdmin}
 * refuses a driver's token outright regardless of which address it authenticated
 * with, because it only accepts an ACTIVE org_admin or super_admin profile.
 * Symmetrically, an org admin can't sign into the mobile app at all — see the
 * Android repo's `UserRole.mobileUnsupportedMessage`, which refuses every admin-tier
 * role there before a login screen for it would even make sense. Because each
 * client only ever has to authenticate its own kind of account, the two `.invalid`
 * namespaces never need to be resolved by the same code path on either side. Do NOT
 * "unify" this into one policy that tries both domains — that would just
 * reintroduce an ambiguity neither login screen actually has today.
 */
final class LoginIdentifierPolicy
{
    /**
     * @param  string  $input  whatever was typed into the sign-in field, as-is.
     * @return string the address to authenticate with.
     */
    public static function resolveAuthEmail(string $input): string
    {
        $trimmed = trim($input);

        if (! str_contains($trimmed, '@')) {
            // Lowercased to match the Edge Function, which lowercases the username
            // before building the account's actual email — so an admin typing their
            // username in any case still resolves to the one address that exists.
            return strtolower($trimmed).'@'.OrgAdminAccountEmail::DOMAIN;
        }

        // An admin-created login's address IS this domain, minted lowercase by the
        // Edge Function — so if what was typed already resolves to it (any casing,
        // since the domain itself isn't case-sensitive to a user), the whole address
        // must still be lowercased to match the one account that exists. A real
        // email's case, in contrast, is the account's own business and is never
        // rewritten.
        if (str_ends_with(strtolower($trimmed), '@'.OrgAdminAccountEmail::DOMAIN)) {
            return strtolower($trimmed);
        }

        return $trimmed;
    }
}
