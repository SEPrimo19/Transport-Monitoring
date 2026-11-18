<?php

/*
|--------------------------------------------------------------------------
| Public landing page settings
|--------------------------------------------------------------------------
|
| Everything the public pages (/ and /privacy) show about the Android app and the
| project. Keep it here, not in the views, so a new release only changes this file.
|
| The download links use GitHub's "latest" address, so they keep working when a newer
| release is published; only the version, size and checksum below need to be updated.
*/

return [
    'version' => '1.1.12',
    'min_android' => 'Android 7.0 (API 24) or newer',
    'apk_size' => '41 MB',
    'sha256' => '3EAB19A82BD22D606457484A3C349A995BD7D4D5516EAAF412B79ADA3B2BC48C',

    'repo_url' => 'https://github.com/SEPrimo19/Transport-Monitoring',
    'apk_url' => 'https://github.com/SEPrimo19/Transport-Monitoring/releases/latest/download/AMOEC-Track-release.apk',
    'release_url' => 'https://github.com/SEPrimo19/Transport-Monitoring/releases/latest',
    'issues_url' => 'https://github.com/SEPrimo19/Transport-Monitoring/issues',

    // Served from public/downloads (same origin, so it needs no extra security-policy entry).
    'manual_path' => 'downloads/AMOEC-Track-User-Manual.pdf',

    'privacy_updated' => 'November 2026',

    // A contact route for people who are not on GitHub (a Facebook page or Messenger link). Set it on Render as
    // LANDING_CONTACT_URL without touching the code; while it is empty the pages fall back to GitHub issues.
    'contact_url' => env('LANDING_CONTACT_URL'),
    'contact_label' => env('LANDING_CONTACT_LABEL', 'Message us'),
];
