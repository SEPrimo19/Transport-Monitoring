<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Laravel ships public/favicon.ico as a ZERO-BYTE placeholder, and nothing
         declared an icon, so every tab fell back to the browser's default globe.
         The .ico carries 16-256px for the tab, bookmarks bar and taskbar; the PNGs
         are preferred by modern browsers; the apple-touch icon is flattened onto
         white because iOS composites transparency onto black. --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/favicon-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
    {{-- Deliberately generic, not the tenant's name: sign-in happens BEFORE any
         organization is resolved, so there is no tenant to brand this page with.
         (config('app.name') is the deployment's own label and belongs after login.) --}}
    <title>Sign in · Transport Monitoring</title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    @php
        // No org is known yet at sign-in (auth precedes tenant resolution), so
        // $brandColor isn't actually shared with this view today — but this
        // validates it the same way layouts/app.blade.php does, so the moment
        // a controller starts passing it here, an unvalidated DB value (bare
        // "165935", malformed, etc.) can't silently make --amoec-brand invalid
        // CSS, which would render .btn-primary / .login-icon white-on-nothing.
        $rawLoginBrand = strtolower(ltrim(trim((string) ($brandColor ?? '')), '#'));
        $loginBrand = preg_match('/^[0-9a-f]{6}$/', $rawLoginBrand) ? '#' . $rawLoginBrand : '#165935';   // AMOEC green, the same as the public landing page, so signing in feels like the same product
        $loginHex = ltrim($loginBrand, '#');
        $loginBrandRgb = hexdec(substr($loginHex, 0, 2)) . ', ' . hexdec(substr($loginHex, 2, 2)) . ', ' . hexdec(substr($loginHex, 4, 2));
    @endphp
    <style>
        :root {
            --amoec-brand: {{ $loginBrand }};
            --amoec-brand-rgb: {{ $loginBrandRgb }};

            /* LIGHT right panel. The earlier dark treatment fixed the white-card-on-
               black-void clash but left the whole screen murky. The clash is really
               about the panel and the card disagreeing, so they agree here instead —
               both light — and the sign-in side now carries its own luminance rather
               than borrowing the hero's. The vivid photo panel supplies the contrast. */
            --auth-green: #165935;        /* the Home page's brand colours */
            --auth-green-dark: #0d3b23;
            --auth-accent: #ffc107;
            --auth-page-bg: #eef5f0;
            --auth-panel-tint: #ffffff;
            --auth-card-bg: #ffffff;
            --auth-card-border: rgba(15, 23, 42, .08);
            --auth-field-bg: #f8fafc;
            --auth-field-border: #cbd5e1;
            --auth-ink: #0f172a;          /* headings, entered text */
            --auth-ink-soft: #334155;     /* subtitle, labels */
            --auth-ink-muted: #64748b;    /* icons, placeholders, footnote */
            --auth-danger: #b42318;
            --auth-danger-bg: #fef3f2;
            --auth-danger-border: #fecdca;
            --auth-success: #067647;
            --auth-success-bg: #ecfdf3;
            --auth-success-border: #abefc6;
        }

        body {
            min-height: 100vh;
            margin: 0;
            background: var(--auth-page-bg);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }

        .auth-shell { min-height: 100vh; }

        /* ── Left: the public Home page's hero, so signing in feels like the same product ──
           Same dark-green gradient, faint grid and soft yellow glow as the landing hero; white copy
           with yellow icons; the transport illustration stays as a faded strip along the bottom. */
        .auth-hero {
            position: relative;
            isolation: isolate;
            color: #fff;
            padding: 3rem;
            background:
                radial-gradient(900px 500px at 85% 10%, rgba(255, 193, 7, .18), transparent 60%),
                radial-gradient(700px 500px at 0% 100%, rgba(46, 158, 91, .35), transparent 60%),
                linear-gradient(135deg, var(--auth-green-dark) 0%, var(--auth-green) 55%, #1d7a47 100%);
        }
        /* the illustration: multiplied into the green so its white backdrop disappears, faded out towards the top */
        .auth-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: -2;
            background-image: url('{{ asset('images/authscreen_bg.jpeg') }}');
            background-repeat: no-repeat;
            background-size: auto 70%;
            background-position: left bottom;
            mix-blend-mode: multiply;
            opacity: .55;
            -webkit-mask-image: linear-gradient(to top, #000 45%, transparent 90%);
            mask-image: linear-gradient(to top, #000 45%, transparent 90%);
        }
        /* the faint grid of the Home hero */
        .auth-hero::after {
            content: "";
            position: absolute;
            inset: 0;
            z-index: -1;
            opacity: .08;
            background-image: linear-gradient(rgba(255, 255, 255, .5) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(255, 255, 255, .5) 1px, transparent 1px);
            background-size: 46px 46px;
        }

        /* The BLOCK is centred in the column; the copy inside it keeps one left edge. */
        .hero-copy { max-width: 22rem; }
        .hero-copy .brand-logo { margin-inline: 0; width: 5rem; height: 5rem; filter: none; }
        @media (max-width: 1399.98px) {
            .auth-hero { padding: 2.5rem; }
            .hero-copy { max-width: 18rem; }
        }
        .hero-copy h1 { color: #fff; }
        .hero-copy .lead { color: rgba(255, 255, 255, .88); opacity: 1; font-weight: 400; }
        .hero-feature {
            display: flex; gap: .75rem; align-items: flex-start;
            color: rgba(255, 255, 255, .92);
            font-weight: 500;
        }
        .hero-feature i { font-size: 1.1rem; line-height: 1.45; color: var(--auth-accent); }

        /* ── Right: light panel + elevated card ──────────────────────────── */
        .auth-panel-col {
            position: relative;
            /* A light base lifted toward white behind the card, with the faintest
               brand wash at the top so the panel still themes per deployment
               without darkening it. */
            background:
                radial-gradient(65% 55% at 50% 8%, rgba(var(--amoec-brand-rgb), .07), transparent 70%),
                linear-gradient(180deg, var(--auth-panel-tint) 0%, var(--auth-page-bg) 100%);
        }

        .auth-card {
            width: 100%;
            max-width: 26rem;
            background: var(--auth-card-bg);
            border: 1px solid var(--auth-card-border);
            border-radius: 1.25rem;
            /* Softer and shorter than the dark version: a heavy shadow on a light
               panel reads as grime rather than elevation. */
            box-shadow: 0 18px 44px -20px rgba(15, 23, 42, .28), 0 2px 6px rgba(15, 23, 42, .06);
            border-top: 4px solid var(--amoec-brand);
        }

        .auth-card h2 { color: var(--auth-ink); }
        .auth-card p,
        .auth-card .form-label { color: var(--auth-ink-soft); }
        .auth-card .auth-footnote { color: var(--auth-ink-muted); }

        /*
         * The app's own mark, so the console and the phone read as one product.
         *
         * Source is the Android launcher icon's ADAPTIVE FOREGROUND layer
         * (mipmap-xxxhdpi/ic_launcher_foreground.png) — 432px, the largest available,
         * and the logo alone on transparency. The composed ic_launcher.png would have
         * dragged its own rounded-square plate in with it, which would sit as a pale
         * box on an already pale panel.
         *
         * The box is much larger than the mark looks, and that is the safe zone, not a
         * mistake: an adaptive foreground reserves transparent margin on all sides for
         * the launcher to mask, so the pin renders at only ~55-60% of whatever box it
         * is given. 8rem of box therefore reads as roughly a 4.5rem mark. Judge this by
         * the rendered pin, not the number here — and if it needs to grow again, the
         * box has to grow by ~1.7x the amount you want the pin to gain.
         *
         * The same padding supplies most of the gap to the heading below, which is why
         * the markup uses a small bottom margin rather than the usual mb-4.
         */
        .brand-logo {
            display: block;
            width: 8rem;
            height: 8rem;
            object-fit: contain;
            /* Block-level, so text-align on the parent does nothing to it — this is
               what actually centres the mark under both the hero copy and the card
               header, and leaving it out left the logo hanging at the left edge while
               the heading beneath it sat centred. */
            margin-inline: auto;
            /* Follows the artwork's silhouette; box-shadow would trace the square. */
            filter: drop-shadow(0 6px 14px rgba(15, 23, 42, .18));
        }
        .auth-card .brand-logo { width: 5.5rem; height: 5.5rem; }

        /* ── Fields ───────────────────────────────────────────────────────── */
        .auth-card .form-control {
            padding-block: .65rem;
            background: var(--auth-field-bg);
            border-color: var(--auth-field-border);
            color: var(--auth-ink);
        }
        .auth-card .form-control::placeholder { color: var(--auth-ink-muted); opacity: 1; }
        .auth-card .form-control:focus {
            background: var(--auth-field-bg);
            color: var(--auth-ink);
            border-color: var(--amoec-brand);
            box-shadow: 0 0 0 .25rem rgba(var(--amoec-brand-rgb), .22);
        }
        /* Bootstrap paints .is-invalid inputs with their own icon; ours would collide. */
        .auth-card .input-group .form-control.is-invalid { background-image: none; }
        .auth-card .input-group-text {
            background: var(--auth-field-bg);
            border-color: var(--auth-field-border);
            color: var(--auth-ink-muted);
        }
        /* Chrome/Edge tint autofilled fields pale yellow; hold our own surface. */
        .auth-card .form-control:-webkit-autofill,
        .auth-card .form-control:-webkit-autofill:focus {
            -webkit-text-fill-color: var(--auth-ink);
            -webkit-box-shadow: 0 0 0 1000px var(--auth-field-bg) inset;
            transition: background-color 9999s ease-in-out 0s;
        }

        .auth-field-error { color: var(--auth-danger); }

        .btn-primary {
            --bs-btn-bg: var(--amoec-brand);
            --bs-btn-border-color: var(--amoec-brand);
            --bs-btn-hover-bg: var(--amoec-brand);
            --bs-btn-hover-border-color: var(--amoec-brand);
            --bs-btn-active-bg: var(--amoec-brand);
            --bs-btn-active-border-color: var(--amoec-brand);
            --bs-btn-disabled-bg: var(--amoec-brand);
            --bs-btn-disabled-border-color: var(--amoec-brand);
            --bs-btn-focus-shadow-rgb: var(--amoec-brand-rgb);
            padding-block: .65rem;
            font-weight: 600;
        }
        .btn-primary:hover { filter: brightness(.93); }
        .btn-primary:active, .btn-primary:focus-visible { filter: brightness(.86); }
        .btn-primary:focus-visible { outline: 2px solid var(--auth-accent); outline-offset: 2px; }

        .auth-card .btn-reveal {
            border: 1px solid var(--auth-field-border);
            border-left: 0;
            background: var(--auth-field-bg);
            color: var(--auth-ink-muted);
        }
        .auth-card .btn-reveal:hover,
        .auth-card .btn-reveal:focus-visible { color: var(--auth-ink); }

        /* ── Alerts ───────────────────────────────────────────────────────── */
        .auth-card .alert-success {
            background: var(--auth-success-bg);
            border: 1px solid var(--auth-success-border);
            color: var(--auth-success);
        }
        .auth-card .alert-danger {
            background: var(--auth-danger-bg);
            border: 1px solid var(--auth-danger-border);
            color: var(--auth-danger);
        }

        a:focus-visible, .btn:focus-visible {
            outline: 2px solid var(--amoec-brand);
            outline-offset: 2px;
            border-radius: .25rem;
        }

        /* On small screens the hero collapses away, so the Home gradient becomes the page backdrop
           and the white card sits on it, as on the hero above. */
        @media (max-width: 991.98px) {
            body {
                background:
                    radial-gradient(600px 400px at 85% 0%, rgba(255, 193, 7, .18), transparent 60%),
                    linear-gradient(160deg, var(--auth-green-dark) 0%, var(--auth-green) 60%, #1d7a47 100%);
                background-attachment: fixed;
            }
            .auth-panel-col { background: transparent; }
        }

        @media (prefers-reduced-motion: reduce) {
            * { transition: none !important; animation: none !important; }
        }

        /* Way back to the public home page. Top-left of the artwork on wide screens;
           above the card on small screens, where the artwork is hidden. */
        .auth-back {
            display: inline-flex; align-items: center; gap: .4rem;
            color: var(--auth-ink-soft); font-size: .9rem; font-weight: 500; text-decoration: none;
            padding: .4rem .7rem; border-radius: 999px;
            background: rgba(255, 255, 255, .72); border: 1px solid rgba(15, 23, 42, .1);
        }
        .auth-back:hover, .auth-back:focus-visible { color: var(--auth-ink); background: #fff; }
        .auth-back-hero { position: absolute; top: 1.25rem; left: 1.5rem; z-index: 3;
            color: #fff; background: rgba(255, 255, 255, .12); border-color: rgba(255, 255, 255, .3); }
        .auth-back-hero:hover, .auth-back-hero:focus-visible { color: #fff; background: rgba(255, 255, 255, .22); }
        .auth-back-hero:focus-visible { outline-color: var(--auth-accent); }
        .auth-appnote { color: var(--auth-ink-muted); }
        .auth-appnote a, .auth-footnote a { color: var(--auth-ink-soft); }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row auth-shell g-0">

        {{-- ── Hero ─────────────────────────────────────────────────────── --}}
        {{-- Full-bleed artwork behind a centred copy column — see
             .auth-hero::before (image) and .auth-hero::after (light veil). --}}
        <div class="col-lg-6 auth-hero d-none d-lg-flex align-items-center justify-content-center">
            <a href="{{ route('home') }}" class="auth-back auth-back-hero"><i class="bi bi-arrow-left" aria-hidden="true"></i>Back to home</a>
            <div class="hero-copy">
                {{-- alt="" — decorative here: the h1 directly below already names the
                     product, so announcing the mark too would just repeat it. --}}
                <img src="{{ asset('images/logo-white.svg') }}" alt="" class="brand-logo mb-3"
                     width="80" height="80" decoding="async">
                <h1 class="display-6 fw-bold mb-3">Transport Monitoring</h1>
                {{-- No .opacity-75: it is an !important rule that would win over the
                     ink token and grey the copy back out against the light artwork. --}}
                <p class="lead mb-4">
                    Track your fleet in real time, manage routes and schedules, and act on
                    passenger reports — all from one console.
                </p>
                <div class="d-flex flex-column gap-3">
                    <div class="hero-feature">
                        <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                        <span>Live vehicle positions and trip progress as they happen</span>
                    </div>
                    <div class="hero-feature">
                        <i class="bi bi-calendar2-week-fill" aria-hidden="true"></i>
                        <span>Routes, timetables and driver assignments in one place</span>
                    </div>
                    <div class="hero-feature">
                        <i class="bi bi-shield-check" aria-hidden="true"></i>
                        <span>Passenger reports triaged with a full evidence trail</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Form ─────────────────────────────────────────────────────── --}}
        <div class="col-lg-6 auth-panel-col d-flex align-items-center justify-content-center p-3 p-sm-4 p-lg-4">
            <div class="auth-card p-4 p-sm-5">
                <a href="{{ route('home') }}" class="auth-back mb-3 d-lg-none"><i class="bi bi-arrow-left" aria-hidden="true"></i>Back to home</a>
                {{-- Centred over a left-aligned form: the title block is a header, not
                     part of the field flow, so centring it reads as deliberate rather
                     than as a stray indent. --}}
                <div class="mb-4 text-center">
                    {{-- Mirrors the hero badge for small screens, where the hero is hidden. --}}
                    {{-- Named here, unlike the hero copy: below `lg` the hero is hidden,
                         so this mark is the only branding on screen. --}}
                    <img src="{{ asset('images/amoec-logo.png') }}" alt="AMOEC Track"
                         class="brand-logo mb-1 d-lg-none" width="88" height="88" decoding="async">
                    {{-- "Welcome", not "Welcome back": this page greets first-time admins
                         and anyone the free instance signed out while it slept, neither of
                         whom is coming back from anywhere. The dashboard's own greeting
                         keeps "back" — by then we know who they are. --}}
                    <h2 class="h3 fw-bold mb-1">Welcome</h2>
                    <p class="mb-0">Sign in to continue to your console.</p>
                </div>

                @if (session('status'))
                    <div class="alert alert-success d-flex align-items-start gap-2 py-3 mb-3" role="status">
                        <i class="bi bi-check-circle-fill mt-1" aria-hidden="true"></i>
                        <div>{{ session('status') }}</div>
                    </div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger d-flex align-items-start gap-2 py-3 mb-3" role="alert">
                        <i class="bi bi-exclamation-triangle-fill mt-1" aria-hidden="true"></i>
                        <div>{{ session('error') }}</div>
                    </div>
                @endif

                <form method="POST" action="{{ route('login.attempt') }}" novalidate id="login-form">
                    @csrf
                    <div class="mb-3">
                        <label for="email" class="form-label fw-medium">Email or username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope" aria-hidden="true"></i></span>
                            {{-- type="text", not "email": an admin-created org admin without a
                                 real email signs in with a bare username (see
                                 CreateOrgAdminLoginRequest / LoginIdentifierPolicy), which the
                                 browser's own email-format check would otherwise block before
                                 the form ever submits. --}}
                            <input type="text" name="email" id="email" value="{{ old('email') }}"
                                   class="form-control border-start-0 ps-0 @error('email') is-invalid @enderror"
                                   placeholder="you@example.com or username"
                                   autofocus required autocomplete="username"
                                   @error('email') aria-describedby="email-error" @enderror>
                        </div>
                        @error('email')
                            <div id="email-error" class="auth-field-error small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label fw-medium">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock" aria-hidden="true"></i></span>
                            <input type="password" name="password" id="password"
                                   class="form-control border-start-0 border-end-0 ps-0 @error('password') is-invalid @enderror"
                                   placeholder="Enter your password"
                                   required autocomplete="current-password"
                                   @error('password') aria-describedby="password-error" @enderror>
                            <button class="btn btn-reveal" type="button" id="toggle-password"
                                    aria-label="Show password" aria-pressed="false" tabindex="0">
                                <i class="bi bi-eye" id="toggle-password-icon" aria-hidden="true"></i>
                            </button>
                        </div>
                        @error('password')
                            <div id="password-error" class="auth-field-error small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary w-100" id="login-submit">
                        <span class="spinner-border spinner-border-sm me-1 d-none" id="login-spinner" aria-hidden="true"></span>
                        <span id="login-submit-label"><i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>Sign in</span>
                    </button>
                </form>

                <p class="auth-footnote small text-center mt-4 mb-2">
                    Trouble signing in? Contact your system administrator.
                </p>
                {{-- Passengers and drivers sometimes arrive here by mistake: this console is for cooperative
                     administrators only, and they sign in inside the Android app. --}}
                <p class="auth-appnote small text-center mb-0">
                    Passenger or driver? Sign in on the Android app.
                    <a href="{{ route('home') }}#download">Get the app</a> &middot; <a href="{{ route('privacy') }}">Privacy</a>
                </p>
            </div>
        </div>

    </div>
</div>

<script>
    (function () {
        // Password reveal
        var toggle = document.getElementById('toggle-password');
        var field = document.getElementById('password');
        var icon = document.getElementById('toggle-password-icon');
        if (toggle && field && icon) {
            toggle.addEventListener('click', function () {
                var show = field.type === 'password';
                field.type = show ? 'text' : 'password';
                icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
                toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
            });
        }

        // Submit feedback. Never disable before submit fires — a disabled control
        // is omitted from the payload, and disabling the button mid-submit in some
        // browsers cancels the navigation. Re-enable on bfcache restore so a Back
        // navigation doesn't strand the user on a dead button.
        var form = document.getElementById('login-form');
        var button = document.getElementById('login-submit');
        var spinner = document.getElementById('login-spinner');
        var label = document.getElementById('login-submit-label');
        if (form && button && spinner && label) {
            form.addEventListener('submit', function () {
                setTimeout(function () {
                    button.disabled = true;
                    spinner.classList.remove('d-none');
                    label.textContent = 'Signing in…';
                }, 0);
            });
            window.addEventListener('pageshow', function () {
                button.disabled = false;
                spinner.classList.add('d-none');
                label.innerHTML = '<i class="bi bi-box-arrow-in-right me-1"></i>Sign in';
            });
        }
    })();
</script>
</body>
</html>
