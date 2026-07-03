<?php

return [
    // Public project URL + anon key (safe to expose to the browser if ever needed).
    'url' => env('SUPABASE_URL'),
    'anon_key' => env('SUPABASE_ANON_KEY'),

    // HS256 secret used to VALIDATE GoTrue access tokens. Secret — .env only.
    // The service_role key is intentionally NOT referenced anywhere in this app.
    'jwt_secret' => env('SUPABASE_JWT_SECRET'),
];
