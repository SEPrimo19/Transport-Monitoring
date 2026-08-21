<?php

return [
    // Public project URL + anon key (safe to expose to the browser if ever needed).
    'url' => env('SUPABASE_URL'),
    'anon_key' => env('SUPABASE_ANON_KEY'),

    // HS256 secret used to VALIDATE GoTrue access tokens. Secret — .env only.
    //
    // The service_role key is intentionally NOT referenced anywhere in this app. That is
    // not primarily a blast-radius argument — this app already holds a comparably
    // privileged credential (DB_USERNAME=postgres.<project-ref>, the database owner; see
    // migration 020's `is_service_role() OR is_database_owner()` guard, and
    // DriverController's direct auth.users reads). The real reason is correctness: minting
    // or resetting a GoTrue user means writing password hashes, the auth.identities row,
    // and confirmation state exactly the way GoTrue's own internals expect, and GoTrue is
    // the only thing that can be trusted to keep doing that correctly across its own
    // upgrades. So those operations go through the `driver-account` Supabase Edge Function
    // (AMOECTrack repo: supabase/functions/driver-account), which calls GoTrue's supported
    // admin API and holds service_role only in its own Supabase secrets — never here.
    'jwt_secret' => env('SUPABASE_JWT_SECRET'),
];
