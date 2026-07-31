<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ config('app.name', 'AMOEC Admin') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    @php
        // Normalise the tenant brand colour to a VALID css hex, whatever shape the
        // DB stores it in ("#165935", bare "165935", upper-case, or malformed).
        // This is load-bearing: a bare or invalid value makes `--amoec-brand`
        // resolve to nothing, so every white-on-brand element (primary/brand
        // buttons, the active nav item, the brand monogram, the avatar) renders
        // white-on-transparent = invisible. The rgb triplet below always matches
        // the resolved colour so the tinted overlays can't drift from the solids.
        $rawBrand = strtolower(ltrim(trim((string) ($brandColor ?? '')), '#'));
        $brand = preg_match('/^[0-9a-f]{6}$/', $rawBrand) ? '#' . $rawBrand : '#165935';
        $hex = ltrim($brand, '#');
        $br = hexdec(substr($hex, 0, 2));
        $bg = hexdec(substr($hex, 2, 2));
        $bb = hexdec(substr($hex, 4, 2));
        $brandRgb = $br . ', ' . $bg . ', ' . $bb;
        // Foreground that stays legible ON the brand fill: dark text for a light
        // brand colour, white for a dark one (YIQ perceived brightness). Without
        // this a light tenant brand renders white-on-light = invisible on every
        // solid brand element (buttons, active nav item, monogram, avatar).
        $brandOn = ((($br * 299) + ($bg * 587) + ($bb * 114)) / 1000) >= 150 ? '#212529' : '#ffffff';
    @endphp
    <style>
        /* ==========================================================
           AMOEC Track — admin console design layer
           Everything here is plain CSS over CDN Bootstrap 5.3 (no
           build step). Primary actions/accents are re-themed through
           Bootstrap's own --bs-btn-* and --bs-border-radius-* CSS variable
           API rather than blanket colour overrides, so hover/active/
           disabled/focus states stay framework-correct per component.
           Semantic status colours (success/warning/danger badges for
           active/pending/maintenance/etc.) are left as Bootstrap
           defaults on purpose — status meaning must never be
           confused with tenant branding.
           ========================================================== */
        :root {
            --amoec-brand: {{ $brand }};
            --amoec-brand-rgb: {{ $brandRgb }};
            --amoec-on-brand: {{ $brandOn }};
            --bs-border-radius: .5rem;
            --bs-border-radius-sm: .35rem;
            --bs-border-radius-lg: .65rem;
            --bs-border-radius-xl: .85rem;
            --bs-border-radius-2xl: 1.1rem;
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                transition-duration: .001ms !important;
                animation-duration: .001ms !important;
                scroll-behavior: auto !important;
            }
        }

        body { background: #f6f8fa; }

        h1, h2, h3, .h1, .h2, .h3, .h4, .h5, .h6 { letter-spacing: -.01em; }
        .display-6, .card .h3.fw-semibold { font-variant-numeric: tabular-nums; letter-spacing: -.02em; }

        /* ---- Sidebar --------------------------------------------- */
        .sidebar { min-height: 100vh; background: #fff; border-right: 1px solid #e5e7eb; }
        /* Desktop: sidebar stays put while only the main panel scrolls; it
           scrolls internally if the nav ever outgrows the viewport. On narrow
           screens it stacks on top and scrolls with the page as normal. */
        @media (min-width: 768px) {
            .sidebar { position: sticky; top: 0; height: 100vh; overflow-y: auto; }
        }

        .brand-mark {
            width: 2rem; height: 2rem; border-radius: .5rem;
            background: var(--amoec-brand); color: var(--amoec-on-brand);
            display: inline-flex; align-items: center; justify-content: center;
            font-size: .72rem; font-weight: 700; letter-spacing: .02em; flex: none;
        }
        .brand-wordmark { font-size: 1rem; letter-spacing: -.01em; }

        .nav-section-label {
            font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em;
            color: #6c757d; padding: .9rem .75rem .3rem;
        }
        .nav-section-label:first-of-type { padding-top: 0; }

        .sidebar .nav-link {
            color: #374151; border-radius: var(--bs-border-radius-sm);
            margin-bottom: .125rem; font-size: .925rem; font-weight: 500;
            padding: .5rem .7rem; display: flex; align-items: center;
            transition: background-color .15s ease, color .15s ease;
        }
        .sidebar .nav-link i { width: 1.1rem; text-align: center; }
        .sidebar .nav-link:hover { background: rgba(var(--amoec-brand-rgb), .14); color: var(--amoec-brand); }
        .sidebar .nav-link.active {
            background: var(--amoec-brand); color: var(--amoec-on-brand); font-weight: 600;
            box-shadow: 0 2px 6px rgba(var(--amoec-brand-rgb), .3);
        }
        /* #9ca3af measured ~2.5:1 on white — fails legibility even for a
           "muted" state. #6c757d (the same gray already used by
           .nav-section-label above) reads ~4.7:1, so a disabled item is
           unmistakably muted but still readable, not washed to nothing. */
        .sidebar .nav-link.disabled { color: #6c757d; }

        /* ---- Topbar / user menu ------------------------------------ */
        .user-avatar {
            width: 1.85rem; height: 1.85rem; border-radius: 50%;
            background: var(--amoec-brand); color: var(--amoec-on-brand);
            display: inline-flex; align-items: center; justify-content: center;
            font-size: .8rem; font-weight: 700; flex: none;
        }
        .dropdown-menu {
            border-radius: var(--bs-border-radius-lg);
            border-color: rgba(15, 23, 42, .08);
            box-shadow: 0 16px 32px -8px rgba(15, 23, 42, .18), 0 4px 10px -6px rgba(15, 23, 42, .1);
            padding: .4rem;
        }
        .dropdown-item { border-radius: var(--bs-border-radius-sm); }

        /* ---- Buttons: brand mapping ---------------------------------
           .btn-primary / .btn-brand carry primary-CTA weight across the
           app; both resolve to the org's brand colour via Bootstrap's
           own --bs-btn-* variables (documented re-theming API) instead
           of a flat colour override, so hover/active/disabled/focus
           stay correct for free. */
        .btn { font-weight: 500; }
        .btn-primary, .btn-brand {
            --bs-btn-color: var(--amoec-on-brand);
            --bs-btn-bg: var(--amoec-brand);
            --bs-btn-border-color: var(--amoec-brand);
            --bs-btn-hover-color: var(--amoec-on-brand);
            --bs-btn-hover-bg: var(--amoec-brand);
            --bs-btn-hover-border-color: var(--amoec-brand);
            --bs-btn-active-color: var(--amoec-on-brand);
            --bs-btn-active-bg: var(--amoec-brand);
            --bs-btn-active-border-color: var(--amoec-brand);
            --bs-btn-disabled-color: var(--amoec-on-brand);
            --bs-btn-disabled-bg: var(--amoec-brand);
            --bs-btn-disabled-border-color: var(--amoec-brand);
            --bs-btn-focus-shadow-rgb: var(--amoec-brand-rgb);
            color: var(--amoec-on-brand); background-color: var(--amoec-brand); border-color: var(--amoec-brand);
        }
        .btn-primary:hover, .btn-brand:hover { filter: brightness(.93); }
        .btn-primary:active, .btn-brand:active,
        .btn-primary:focus-visible, .btn-brand:focus-visible { filter: brightness(.86); }

        .btn-outline-primary {
            --bs-btn-color: var(--amoec-brand);
            --bs-btn-border-color: var(--amoec-brand);
            --bs-btn-hover-color: var(--amoec-on-brand);
            --bs-btn-hover-bg: var(--amoec-brand);
            --bs-btn-hover-border-color: var(--amoec-brand);
            --bs-btn-active-color: var(--amoec-on-brand);
            --bs-btn-active-bg: var(--amoec-brand);
            --bs-btn-active-border-color: var(--amoec-brand);
            --bs-btn-disabled-color: var(--amoec-brand);
            --bs-btn-disabled-border-color: var(--amoec-brand);
            --bs-btn-focus-shadow-rgb: var(--amoec-brand-rgb);
        }

        /* Pagination (trip history) picks up the same brand mapping. */
        .page-link { color: var(--amoec-brand); }
        .page-link:hover { color: var(--amoec-brand); background-color: rgba(var(--amoec-brand-rgb), .08); }
        .page-link:focus { box-shadow: 0 0 0 .25rem rgba(var(--amoec-brand-rgb), .25); }
        .page-item.active .page-link { background-color: var(--amoec-brand); border-color: var(--amoec-brand); color: var(--amoec-on-brand); }

        /* ---- Form controls: brand-coloured focus, not stock Bootstrap blue */
        .form-control:focus, .form-select:focus {
            border-color: var(--amoec-brand);
            box-shadow: 0 0 0 .25rem rgba(var(--amoec-brand-rgb), .2);
        }
        .form-check-input:checked { background-color: var(--amoec-brand); border-color: var(--amoec-brand); }
        .form-check-input:focus { border-color: var(--amoec-brand); box-shadow: 0 0 0 .2rem rgba(var(--amoec-brand-rgb), .2); }

        /* Visible, on-brand keyboard focus everywhere else. This only
           styles the focus ring — it never removes the native outline. */
        a:focus-visible, .nav-link:focus-visible, .dropdown-item:focus-visible,
        .list-group-item-action:focus-visible, .btn:focus-visible {
            outline: 2px solid var(--amoec-brand);
            outline-offset: 2px;
            border-radius: .25rem;
        }

        /* ---- Cards, alerts, tables ----------------------------------- */
        .card.shadow-sm {
            box-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 6px 16px rgba(15, 23, 42, .06) !important;
        }
        .alert { border-left-width: 4px; }

        .page-toolbar { padding-bottom: 1rem; margin-bottom: 1.25rem; border-bottom: 1px solid #e9ecef; }
        .page-toolbar p { font-size: .925rem; }

        .card .table > thead.table-light {
            --bs-table-bg: transparent;
            --bs-table-color: #6c757d;
        }
        .card .table > thead.table-light > tr > th {
            border-bottom: 2px solid #e9ecef;
            font-size: .7rem;
            text-transform: uppercase;
            letter-spacing: .05em;
            font-weight: 700;
            padding-block: .8rem;
        }
        .card .table > :not(caption) > * > * { padding-block: .65rem; }
        .table-hover > tbody > tr:hover > * { background-color: rgba(var(--amoec-brand-rgb), .05); }

        .badge { font-weight: 600; letter-spacing: .01em; }

        /* ---- Stat chips (dashboard) ------------------------------------ */
        .stat-icon {
            width: 3rem; height: 3rem; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center; flex: none;
        }
        .stat-icon--sm { width: 2.5rem; height: 2.5rem; }
        .stat-icon--brand { background: rgba(var(--amoec-brand-rgb), .12); color: var(--amoec-brand); }
        .stat-icon--warning { background: rgba(var(--bs-warning-rgb), .18); color: var(--bs-warning-text-emphasis, #997404); }
        .stat-icon--muted { background: var(--bs-secondary-bg, #e9ecef); color: var(--bs-secondary-color, #6c757d); }

        /* ---- Empty states ------------------------------------------------ */
        .empty-state { padding: 2.25rem 1rem; }
        .empty-state i { font-size: 1.75rem; color: #adb5bd; display: block; margin-bottom: .5rem; }
        .empty-state .empty-state-title { font-weight: 600; color: #495057; }
    </style>
    @stack('head')
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <nav class="col-12 col-md-3 col-lg-2 sidebar p-3">
            <div class="d-flex align-items-center gap-2 mb-4">
                <span class="brand-mark">{{ Str::substr(config('app.name', 'AMOEC Admin'), 0, 2) }}</span>
                <strong class="text-truncate brand-wordmark">{{ config('app.name', 'AMOEC Admin') }}</strong>
            </div>
            <ul class="nav flex-column">
                <li class="nav-section-label">Overview</li>
                <li class="nav-item">
                    <a class="nav-link @yield('nav_dashboard')" href="{{ route('dashboard') }}">
                        <i class="bi bi-speedometer2 me-2"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link @yield('nav_operations')" href="{{ route('operations.index') }}">
                        <i class="bi bi-broadcast me-2"></i>Live Ops
                    </a>
                </li>
                @if ($ctx->isSuperAdmin())
                    <li class="nav-section-label">Platform</li>
                    <li class="nav-item">
                        <a class="nav-link @yield('nav_organizations')" href="{{ route('organizations.index') }}">
                            <i class="bi bi-buildings me-2"></i>Organizations
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @yield('nav_org_admins')" href="{{ route('org-admins.index') }}">
                            <i class="bi bi-person-gear me-2"></i>Org Admins
                        </a>
                    </li>
                @endif
                <li class="nav-section-label">Fleet</li>
                <li class="nav-item">
                    <a class="nav-link @yield('nav_vehicles')" href="{{ route('vehicles.index') }}">
                        <i class="bi bi-bus-front me-2"></i>Vehicles
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link @yield('nav_drivers')" href="{{ route('drivers.index') }}">
                        <i class="bi bi-person-badge me-2"></i>Drivers
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link @yield('nav_terminals')" href="{{ route('terminals.index') }}">
                        <i class="bi bi-pin-map me-2"></i>Terminals
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link @yield('nav_routes')" href="{{ route('routes.index') }}">
                        <i class="bi bi-signpost-2 me-2"></i>Routes
                    </a>
                </li>
                <li class="nav-section-label">Scheduling</li>
                <li class="nav-item">
                    <a class="nav-link @yield('nav_schedules')" href="{{ route('schedules.index') }}">
                        <i class="bi bi-calendar-week me-2"></i>Schedules
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link @yield('nav_assignments')" href="{{ route('assignments.index') }}">
                        <i class="bi bi-clipboard-check me-2"></i>Assignments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link @yield('nav_trips')" href="{{ route('trips.index') }}">
                        <i class="bi bi-clock-history me-2"></i>Trips
                    </a>
                </li>
                <li class="nav-section-label">Support</li>
                <li class="nav-item">
                    <a class="nav-link @yield('nav_reports')" href="{{ route('reports.index') }}">
                        <i class="bi bi-flag me-2"></i>Reports
                    </a>
                </li>
            </ul>
        </nav>

        <main class="col-12 col-md-9 col-lg-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h4 mb-0">@yield('heading', 'Dashboard')</h1>
                    <small class="text-muted"><i class="bi bi-building me-1"></i>{{ $orgName ?? 'All organizations' }}</small>
                </div>
                <div class="dropdown">
                    <button class="btn btn-light border dropdown-toggle d-inline-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="user-avatar">{{ strtoupper(substr($ctx->fullName ?? $ctx->email ?? '?', 0, 1)) }}</span>
                        <span class="d-none d-sm-inline">{{ $ctx->fullName ?? $ctx->email }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text small text-muted">{{ $ctx->email }}</span></li>
                        <li><span class="dropdown-item-text small"><span class="badge text-bg-secondary">{{ $ctx->role }}</span></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}" class="m-0">
                                @csrf
                                <button type="submit" class="dropdown-item text-danger">
                                    <i class="bi bi-box-arrow-right me-2"></i>Sign out
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>

            @if (session('status'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-1"></i>{{ session('status') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-1"></i>{{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

{{-- Reusable confirmation dialog. Any <form data-confirm="…"> opens this styled
     modal instead of the native browser confirm(). Optional data-confirm-title
     sets the heading and data-confirm-action sets the confirm button label. --}}
<div class="modal fade" id="appConfirmModal" tabindex="-1" aria-labelledby="appConfirmTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="appConfirmTitle">Please confirm</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="appConfirmBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="appConfirmAccept">Confirm</button>
            </div>
        </div>
    </div>
</div>
<script>
    (function () {
        var modalEl = document.getElementById('appConfirmModal');
        if (!modalEl || typeof bootstrap === 'undefined') return;
        var modal = new bootstrap.Modal(modalEl);
        var titleEl = document.getElementById('appConfirmTitle');
        var bodyEl = document.getElementById('appConfirmBody');
        var acceptEl = document.getElementById('appConfirmAccept');
        var pending = null;

        // Intercept any form that opts in with data-confirm; show the modal
        // instead of letting the browser's native confirm() fire.
        document.addEventListener('submit', function (e) {
            var form = e.target.closest && e.target.closest('form[data-confirm]');
            if (!form) return;
            e.preventDefault();
            pending = form;
            titleEl.textContent = form.dataset.confirmTitle || 'Please confirm';
            bodyEl.textContent = form.dataset.confirm || 'Are you sure?';
            acceptEl.textContent = form.dataset.confirmAction || 'Confirm';
            modal.show();
        });

        acceptEl.addEventListener('click', function () {
            if (!pending) return;
            var form = pending;
            pending = null;
            modal.hide();
            form.submit(); // native submit() does not re-fire the submit handler
        });

        modalEl.addEventListener('hidden.bs.modal', function () { pending = null; });
    })();
</script>
@stack('scripts')
</body>
</html>
