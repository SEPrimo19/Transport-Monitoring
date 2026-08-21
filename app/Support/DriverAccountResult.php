<?php

namespace App\Support;

/**
 * Outcome of a {@see DriverAccountProvisioner} or {@see OrgAdminAccountProvisioner}
 * call — both callers share this shape rather than each inventing their own.
 * Controllers get a success flag, the fields they need, and a message that is
 * already safe to show an admin — never the raw HTTP/JSON response from the Edge
 * Function. `$accountDeleted` is meaningful only for the driver `delete_account`
 * action; org-admin callers simply never read it.
 */
final class DriverAccountResult
{
    private function __construct(
        public readonly bool $success,
        public readonly ?string $profileId,
        public readonly ?string $email,
        public readonly ?string $message,
        // Only meaningful for the delete_account action: whether the Edge Function
        // actually deleted an auth.users row (false for a promoted driver's personal
        // email, which delete_account deliberately leaves alone).
        public readonly ?bool $accountDeleted = null,
        // True when we could not tell whether the Edge Function ran at all (a timeout,
        // a dropped connection, DNS failure, ...) — as opposed to it running and
        // REJECTING the request (bad input, 409, etc). This distinction matters: on a
        // provincial link the likeliest cause of "no response" is the call having
        // SUCCEEDED and the response being lost, not it having failed. An admin who
        // reads $message for either case the same way retries blind and, on create,
        // can end up with two driver rows for one person. See DriverAccountProvisioner.
        public readonly bool $unreachable = false,
    ) {}

    public static function ok(?string $profileId = null, ?string $email = null, ?bool $accountDeleted = null): self
    {
        return new self(true, $profileId, $email, null, $accountDeleted);
    }

    public static function failed(string $message): self
    {
        return new self(false, null, null, $message);
    }

    /** A confirmed rejection response (4xx/5xx) is NOT this — only a transport failure is. */
    public static function unreachable(string $message): self
    {
        return new self(false, null, null, $message, unreachable: true);
    }
}
