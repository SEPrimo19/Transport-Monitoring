<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
        $loginBrand = preg_match('/^[0-9a-f]{6}$/', $rawLoginBrand) ? '#' . $rawLoginBrand : '#0d6efd';
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
            --auth-page-bg: #eef2f9;
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

        /* ── Left: imagery + value proposition (lg and up) ───────────────── */
        .auth-hero {
            position: relative;
            isolation: isolate;
            /* Base paint shown for the instant before the PNG decodes. The artwork
               (::before) and the veil (::after) fully cover this once loaded. */
            background: linear-gradient(160deg,
                #ffffff 0%,
                var(--auth-panel-tint) 45%,
                var(--auth-page-bg) 100%);
            padding: 3rem;
        }

        /*
         * The artwork layer — full-bleed, edge to edge, top to bottom.
         *
         * Every subject in the asset (map, monitor, bus, van, jeepney, shelter, sign)
         * lives inside its left ~845px of 1536; the right ~45% is deliberately empty
         * space. The hero panel is TALLER than it is wide (width/height ≈ 0.89 at both
         * 1366 and 1920 — it's driven by the row's 100vh, not the viewport width),
         * while the asset is landscape (1536×1024, ratio 1.5). Those two facts do the
         * framing for us: on a panel narrower (relatively) than the image, `cover` can
         * only ever be scaled by HEIGHT, so it always reveals a fixed ~911px-wide
         * window of the 1536px-wide asset — regardless of the panel's absolute size.
         * Anchor that window at the image's own left edge (`background-position:
         * left`) and it covers native x 0–911: every subject (they end at ~885) plus
         * a small margin, with exactly the empty right portion cropped away. No band,
         * no oversized percentage hack, and — because it falls out of the *ratio*
         * between panel and image, not a magic number — it holds at both viewports.
         *
         * Because the scale is height-bound, the image fills the panel's full height
         * with zero overflow. The old banded version was sized by width and anchored
         * bottom, which left blank space above it — that blank strip against plain
         * gradient was the hard top edge. There's no unfilled strip left to seam here.
         */
        .auth-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: -2;
            background-image: url('{{ asset('images/authscreen_bg.png') }}');
            background-repeat: no-repeat;
            background-size: cover;
            background-position: left center;
        }

        /*
         * The veil — directional and LIGHT, not a uniform scrim.
         *
         * The copy now sits over the busiest part of the illustration: the western
         * half of the map, and the dashboard-monitor icon, which is a dark navy
         * bezel — the one genuinely low-contrast pocket in an otherwise pale asset.
         * A dark veil would fix that too, but a dark veil is what buried the map and
         * the bus before: at the ~78% strength AA would need over near-white art, the
         * artwork all but disappears. White lifts local luminance instead of crushing
         * it toward black, so shapes underneath read as soft silhouettes rather than
         * vanishing — and because the asset's own background is already light, a much
         * lower peak (0.74) is enough to clear AA even over that dark bezel.
         *
         * It fades to fully transparent by 56% of the panel width, well before the
         * van, jeepney, shelter and sign — those render at full, unveiled strength.
         * Measured contrast at the worst point (dark-navy monitor bezel, peak 0.74
         * veil, the single lowest-contrast pocket in the panel):
         *   --auth-ink (h1)              ~10.6 : 1
         *   --auth-ink-soft (lead/list)   ~6.1 : 1  (clears the 4.5:1 floor; short of
         *                                            the 7:1 aim only in this pocket)
         * Everywhere else the copy sits — the map's pale-blue fields, the near-white
         * background — the artwork already reads ~10–15:1 unveiled, so the veil only
         * ever adds headroom there, it never has to fight the artwork for contrast.
         */
        .auth-hero::after {
            content: "";
            position: absolute;
            inset: 0;
            z-index: -1;
            /* Raised across the copy column specifically. The copy runs to ~42% of the
               panel, where the old ramp had already decayed to .22 — so the ENDS of the
               longer feature lines were sitting on essentially bare artwork while their
               beginnings had .74. That unevenness is what made the text look patchy
               rather than merely light. Hold the veil closer to full strength for the
               copy's whole width, then drop it quickly so the vehicles keep theirs. */
            background: linear-gradient(to right,
                rgba(255, 255, 255, .88) 0%,
                rgba(255, 255, 255, .82) 26%,
                rgba(255, 255, 255, .62) 42%,
                rgba(255, 255, 255, .24) 54%,
                rgba(255, 255, 255, 0) 64%);
        }

        /* Base measure — full-bleed frees the width the old band used to hold
           hostage, so the copy column gets some of it back (was 20rem) while
           staying well inside the veil's 56%-of-panel safe zone at 1920 (~30rem
           of budget there). */
        .hero-copy { max-width: 22rem; }
        /* This has to come AFTER the unconditional rule above, not before it: a
           plain selector beats a media-scoped one of equal specificity if it's
           later in source order, so putting the override first would make it a
           no-op below 1400px — the panel narrows enough there that 22rem starts
           crowding the veil's safe zone, so give it back down to 18rem instead. */
        @media (max-width: 1399.98px) {
            .auth-hero { padding: 2.5rem; }
            .hero-copy { max-width: 18rem; }
        }
        /* Dark ink: the artwork is a near-white illustration (luminance ~.85). White
           copy over it would need a ~78% DARK scrim to clear AA — exactly the overlay
           that used to bury the bus and the map. Dark copy needs only the light veil
           above, which never lowers contrast, so the artwork keeps full strength
           everywhere the copy isn't. */
        .hero-copy h1 { color: var(--auth-ink); }
        /* Full-strength ink, not the softer tone. Body copy on the FORM side sits on
           flat white and can afford to be muted; this copy sits on an illustration and
           cannot. Bootstrap's .lead also ships font-weight 300 — hairline strokes at
           1.25rem over artwork were most of why this read as washed out. */
        .hero-copy .lead {
            color: var(--auth-ink);
            opacity: 1;
            font-weight: 400;
        }
        .hero-feature {
            display: flex; gap: .75rem; align-items: flex-start;
            color: var(--auth-ink);
            font-weight: 500;
        }
        .hero-feature i { font-size: 1.1rem; line-height: 1.45; color: var(--amoec-brand); }

        /*
         * Local halo, instead of a heavier veil.
         *
         * The remaining problem is positional, not chromatic: the feature rows cross
         * the dashboard-monitor vignette, the one dark object in a pale asset. The
         * obvious fix — raise the veil — punishes the whole panel to solve a collision
         * that happens in one small pocket, and washing the bus back out is exactly
         * what we spent the last few rounds undoing.
         *
         * A text-shadow only paints immediately around the glyphs, so the copy lifts
         * off whatever is behind it while the artwork a few pixels away is untouched.
         * Three stacked white shadows: a tight one for edge definition, a wider one for
         * the halo, and a 1px drop so the baseline doesn't merge into busy detail.
         */
        .hero-copy h1,
        .hero-copy .lead,
        .hero-feature {
            text-shadow:
                0 0 4px rgba(255, 255, 255, .95),
                0 0 12px rgba(255, 255, 255, .9),
                0 1px 0 rgba(255, 255, 255, .85);
        }

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

        /* On small screens the hero collapses away, so the photo becomes the page
           backdrop instead of being lost entirely. A LIGHT scrim now, matching the
           desktop panel — the white card sits on it the same way at every width. */
        @media (max-width: 991.98px) {
            body {
                background-image: linear-gradient(rgba(238, 242, 249, .90), rgba(238, 242, 249, .94)),
                                  url('{{ asset('images/authscreen_bg.png') }}');
                background-size: cover;
                background-position: center;
                background-attachment: fixed;
            }
            .auth-panel-col { background: transparent; }
        }

        @media (prefers-reduced-motion: reduce) {
            * { transition: none !important; animation: none !important; }
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row auth-shell g-0">

        {{-- ── Hero ─────────────────────────────────────────────────────── --}}
        {{-- Full-bleed artwork behind a left-anchored copy column — see
             .auth-hero::before (image) and .auth-hero::after (light veil). --}}
        <div class="col-lg-6 auth-hero d-none d-lg-flex align-items-center">
            <div class="hero-copy">
                {{-- alt="" — decorative here: the h1 directly below already names the
                     product, so announcing the mark too would just repeat it. --}}
                <img src="{{ asset('images/amoec-logo.png') }}" alt="" class="brand-logo mb-1"
                     width="128" height="128" decoding="async">
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
                <div class="mb-4">
                    {{-- Mirrors the hero badge for small screens, where the hero is hidden. --}}
                    {{-- Named here, unlike the hero copy: below `lg` the hero is hidden,
                         so this mark is the only branding on screen. --}}
                    <img src="{{ asset('images/amoec-logo.png') }}" alt="AMOEC Track"
                         class="brand-logo mb-1 d-lg-none" width="88" height="88" decoding="async">
                    <h2 class="h3 fw-bold mb-1">Welcome back</h2>
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
                        <label for="email" class="form-label fw-medium">Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope" aria-hidden="true"></i></span>
                            <input type="email" name="email" id="email" value="{{ old('email') }}"
                                   class="form-control border-start-0 ps-0 @error('email') is-invalid @enderror"
                                   placeholder="you@example.com"
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

                <p class="auth-footnote small text-center mt-4 mb-0">
                    Trouble signing in? Contact your system administrator.
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
