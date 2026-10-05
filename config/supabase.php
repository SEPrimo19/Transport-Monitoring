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

    // Signing algorithms this app will ACCEPT on a GoTrue access token.
    //
    // The verifier used to read the algorithm out of the token's own header and
    // pick its key accordingly. That is not the classic confusion bug — the HS256
    // branch uses this app's private SUPABASE_JWT_SECRET, never the published JWKS
    // key — but it meant a project that had moved to asymmetric keys would still
    // accept an HS256 token from anyone holding the legacy shared secret. That
    // secret is long-lived, visible to anyone with dashboard access, and does not
    // rotate when the signing keys do.
    //
    // This project's JWKS publishes a single ES256 key (verified 2026-10-05), so
    // HS256 is never legitimately issued and is simply refused. A legacy project
    // that still signs HS256 adds it back through SUPABASE_JWT_ALGS.
    'jwt_algorithms' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SUPABASE_JWT_ALGS', 'ES256,RS256'))
    ))),
];
