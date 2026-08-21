<?php

namespace App\Support;

/**
 * The domain admin-created driver logins are minted under
 * (`<employee_code>@drivers.amoectrack.invalid` — see the `driver-account` Supabase
 * Edge Function, Android repo: supabase/functions/driver-account/index.ts).
 *
 * This MUST stay under the `.invalid` TLD, reserved for exactly this purpose by
 * RFC 6761 §6.4: no registrar will ever sell it, so nobody can ever stand up a mail
 * server for it and receive a password-recovery email addressed to a driver account.
 * The mobile app ships the Supabase anon key, so anyone can call GoTrue's recovery
 * endpoint for a guessed `<username>@<this domain>`; if this were a real, ownable
 * domain, buying it and pointing a catch-all MX at it would be enough to receive that
 * OTP and take over any driver account. Do not change this to a real-looking domain.
 *
 * A driver's login is one of two kinds:
 *  - admin-created ("Create a new login"): the auth.users email ends with this
 *    domain, and `employee_code` IS the username — renaming it after creation
 *    would silently lock the driver out, because the address is baked in at
 *    creation and never changes.
 *  - promoted from a self-registered app account: a real personal email,
 *    editable as before, and this domain never applies to it.
 *
 * Kept in one place so the web admin and the Edge Function can't drift on what
 * "an admin-created login" means.
 */
final class DriverAccountEmail
{
    public const DOMAIN = 'drivers.amoectrack.invalid';

    public static function isUsernameLogin(?string $email): bool
    {
        return is_string($email) && str_ends_with(strtolower($email), '@'.self::DOMAIN);
    }
}
