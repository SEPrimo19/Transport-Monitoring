{{--
    Base for every error page.

    Deliberately standalone rather than @extends('layouts.app'): that layout expects
    $brandColor and the AdminContext the authentication middleware fills in, and an
    error page is most needed precisely when those are absent — an unauthenticated
    visitor, or a 500 thrown before the context was ever built. A layout that threw
    while rendering the error page would replace a handled error with an unhandled
    one, which is the failure this whole file exists to prevent.

    No tenant branding for the same reason the sign-in page carries none: an error
    can surface before any organization has been resolved.
--}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · Transport Monitoring</title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eef2f9;
            color: #0f172a;
            padding: 1.5rem;
        }
        .error-card {
            max-width: 32rem;
            width: 100%;
            background: #fff;
            border: 1px solid rgba(15, 23, 42, .08);
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(15, 23, 42, .06), 0 12px 32px rgba(15, 23, 42, .07);
            padding: 2.5rem 2rem;
            text-align: center;
        }
        .error-code {
            font-size: .8rem;
            font-weight: 600;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: #64748b;
        }
        .error-icon { font-size: 2.5rem; color: #64748b; line-height: 1; }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { transition-duration: .001ms !important; animation-duration: .001ms !important; }
        }
    </style>
</head>
<body>
    <main class="error-card">
        <i class="bi @yield('icon', 'bi-exclamation-circle') error-icon d-block mb-3" aria-hidden="true"></i>
        <p class="error-code mb-2">@yield('code')</p>
        <h1 class="h4 fw-bold mb-2">@yield('title')</h1>
        <p class="text-secondary mb-4">@yield('message')</p>
        @yield('action')
    </main>
</body>
</html>
