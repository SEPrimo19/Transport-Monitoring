<?php

namespace App\Support;

/**
 * The domain admin-created org-admin logins are minted under, when no real email is
 * supplied at creation (`<username>@admins.amoectrack.invalid` — see the
 * `driver-account` Supabase Edge Function's `create_org_admin` action, Android repo:
 * supabase/functions/driver-account/index.ts).
 *
 * A DIFFERENT subdomain from the driver one ({@see DriverAccountEmail::DOMAIN},
 * `drivers.amoectrack.invalid`) — kept distinct so the two account kinds can never
 * collide or be mistaken for each other by a suffix check.
 *
 * Same `.invalid`-TLD reasoning as the driver domain: reserved by RFC 6761 §6.4, so
 * no registrar will ever sell it and nobody can ever stand up a mail server for it to
 * receive a password-recovery email addressed to a login created without one.
 *
 * An org admin's login is one of three kinds:
 *  - admin-created with no email supplied ("Create a new login", email left blank):
 *    the auth.users email ends with this domain, and `username` IS the local part —
 *    a password reset here is the only way to change its password.
 *  - admin-created WITH a real email supplied at creation: a real personal email,
 *    and this domain never applies to it — normal password recovery works.
 *  - promoted from a self-registered passenger account (unchanged flow): also a real
 *    personal email.
 *
 * Kept in one place, mirroring {@see DriverAccountEmail}, so the web admin and the
 * Edge Function can't drift on what "an admin-created login" means.
 */
final class OrgAdminAccountEmail
{
    public const DOMAIN = 'admins.amoectrack.invalid';

    public static function isUsernameLogin(?string $email): bool
    {
        return is_string($email) && str_ends_with(strtolower($email), '@'.self::DOMAIN);
    }
}
