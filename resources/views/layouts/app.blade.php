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
        /* Below md the sidebar is an offcanvas drawer, where a full-viewport minimum
           and a right border are both wrong: the drawer sizes itself, and the border
           would draw down the middle of the screen while it slides. */
        @media (max-width: 767.98px) {
            .sidebar.offcanvas-md { min-height: auto; border-right: 0; width: min(82vw, 20rem); }
        }
        /* Bootstrap gives .min-w-0 no utility of its own, and without it a flex child
           refuses to shrink and .text-truncate never engages. */
        .min-w-0 { min-width: 0; }
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
        /* Per-tenant logo image in the sidebar header (replaces the monogram when
           the org has a logo). A clean 52px square; contain-fit on white so any
           logo shape fills the box without distortion. */
        .brand-logo {
            width: 3.25rem; height: 3.25rem; border-radius: .65rem;
            object-fit: contain; background: #fff; flex: none;
        }
        /* Keep the no-logo monogram the same footprint as the logo so the header
           height is identical whether an org has a logo or not. */
        .brand-mark { width: 3.25rem; height: 3.25rem; border-radius: .65rem; font-size: 1rem; }
        .brand-wordmark { font-size: 1.15rem; }
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

        /* ---- Notification bell ------------------------------------------- */
        /* Cap the list so a backlog scrolls inside the dropdown instead of running
           off the bottom of the viewport. */
        .notif-items { max-height: 22rem; overflow-y: auto; }

        /* A number quietly changing is easy to miss, so the bell gives one nudge
           when the poll finds MORE pending than last time. Animation only — never
           the sole signal, since the badge and its aria-label carry the count. */
        .notif-bump { animation: notif-bump .6s ease; transform-origin: 50% 0; }
        @keyframes notif-bump {
            0%, 100% { transform: rotate(0); }
            15% { transform: rotate(-12deg); }
            30% { transform: rotate(10deg); }
            45% { transform: rotate(-7deg); }
            60% { transform: rotate(5deg); }
            75% { transform: rotate(-2deg); }
        }
        @media (prefers-reduced-motion: reduce) {
            .notif-bump { animation: none; }
        }

        /* ==========================================================
           Design refresh — depth, hierarchy, affordance
           ----------------------------------------------------------
           Additive only. Nothing above is overridden, semantic status
           colours stay Bootstrap's (see the note at the top of this
           block — status meaning must not read as branding), and every
           transition here is already covered by the global
           prefers-reduced-motion rule.

           Applies to both tiers: these are layout-level rules, so the
           org_admin screens and the super_admin platform screens get
           the same treatment from one place.
           ========================================================== */

        /* Canvas. A very soft brand wash in the top corner instead of
           flat grey, so white cards read as raised rather than pasted on. */
        body {
            background:
                radial-gradient(1100px 380px at 100% -5%,
                                rgba(var(--amoec-brand-rgb), .07), transparent 60%),
                #f6f8fa;
            background-attachment: fixed;
        }

        main h1.h4 { font-weight: 700; letter-spacing: -.02em; }

        /* ---- Form sections ---------------------------------------
           The views separate sections with <hr> plus a muted h6, which
           put all the visual weight on the rule and none on the label —
           so a long form read as one undifferentiated list of fields.
           Flip it: quieten the rule, promote the label, give its icon a
           tinted chip so sections are findable while scrolling. */
        .card-body h2.h6.text-muted {
            color: var(--amoec-brand) !important;
            text-transform: uppercase;
            letter-spacing: .06em;
            font-size: .72rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .card-body h2.h6.text-muted i {
            display: inline-flex; align-items: center; justify-content: center;
            width: 1.6rem; height: 1.6rem; flex: none;
            border-radius: .45rem;
            background: rgba(var(--amoec-brand-rgb), .12);
            font-size: .85rem;
            margin: 0 !important;
        }
        .card-body hr.my-4 {
            border-color: #e9ecef; opacity: 1;
            margin-block: 1.75rem 1.25rem !important;
        }

        /* ---- Inputs ---------------------------------------------- */
        .form-control:not(.form-control-sm),
        .form-select:not(.form-select-sm) { padding-block: .5rem; }
        .form-control, .form-select {
            border-color: #dfe3e8;
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        .form-control:hover:not(:focus):not(:disabled):not([readonly]),
        .form-select:hover:not(:focus):not(:disabled) { border-color: #c3c9d2; }
        .form-label {
            font-weight: 600; font-size: .85rem;
            color: #374151; margin-bottom: .35rem;
        }
        .form-text { font-size: .8rem; }

        /* ---- Sticky form actions ---------------------------------
           The organization form runs well past one screen, so its Save
           button sat below the fold for the whole time the form was
           being filled in. Pinning the action row keeps the primary
           action reachable without hunting for the bottom of the page. */
        .form-actions {
            /* Above Leaflet, below Bootstrap's modal. Leaflet stacks its panes
               at 400-700 and its controls at 800, so the original z-index of 3
               put the save bar UNDERNEATH the map on every form that embeds one
               (add terminal, route geometry, the org region picker) — the
               buttons were there, just painted over. 1020 clears Leaflet and
               still sits below the modal backdrop at 1050. */
            position: sticky; bottom: 0; z-index: 1020;
            margin-top: 1.75rem !important;
            padding: .85rem 0;
            background: #fff;
            border-top: 1px solid #eef1f4;
            box-shadow: 0 -10px 16px -12px rgba(15, 23, 42, .18);
        }

        /* ---- Sidebar: accent bar on the active item --------------- */
        .sidebar .nav-link { position: relative; }
        .sidebar .nav-link.active::before {
            content: ""; position: absolute; left: -.7rem; top: 50%;
            transform: translateY(-50%);
            width: .2rem; height: 1.15rem;
            border-radius: 0 .2rem .2rem 0;
            background: var(--amoec-brand); opacity: .55;
        }

        /* ---- Cards that are links behave like it ------------------ */
        a.text-reset .card, a.text-decoration-none .card {
            transition: transform .15s ease, box-shadow .15s ease;
        }
        a.text-reset:hover .card,
        a.text-decoration-none:hover .card {
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(15, 23, 42, .05),
                        0 12px 24px rgba(15, 23, 42, .10) !important;
        }

        /* ---- Tables: breathe at the edges ------------------------- */
        .card .table > :not(caption) > * > *:first-child { padding-left: 1.1rem; }
        .card .table > :not(caption) > * > *:last-child { padding-right: 1.1rem; }

        .badge { padding: .36em .6em; border-radius: .4rem; }

        /* ==========================================================
           Design refresh, pass 2 — emptiness, density, orientation
           ----------------------------------------------------------
           A newly onboarded cooperative has no vehicles, drivers,
           routes, terminals, schedules, assignments or trips, so for
           its whole first session EVERY list it owns renders its empty
           state. That path had the least design attention and gets the
           most use at exactly the moment a tenant is deciding whether
           the product looks finished, so it is treated here as a
           first-class screen rather than a fallback.
           ========================================================== */

        /* ---- Empty states ----------------------------------------
           Was: a small grey glyph, a bold line, and a text link buried
           in a muted sentence — the call to action was the least
           visible thing in the cell. Now the icon is a brand-tinted
           medallion and the action reads as the action. */
        .empty-state { padding: 3rem 1rem; }
        .empty-state i {
            width: 3.25rem; height: 3.25rem;
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 50%;
            background: rgba(var(--amoec-brand-rgb), .1);
            color: var(--amoec-brand);
            font-size: 1.4rem;
            margin: 0 0 .9rem;
        }
        .empty-state .empty-state-title {
            font-size: 1.02rem; font-weight: 650; color: #1f2937;
            margin-bottom: .35rem;
        }
        .empty-state a {
            color: var(--amoec-brand);
            font-weight: 650;
            text-decoration: none;
            border-bottom: 1px solid rgba(var(--amoec-brand-rgb), .35);
        }
        .empty-state a:hover { border-bottom-color: var(--amoec-brand); }

        /* ---- Long lists: keep the column headings in view ---------
           Several of these tables are unbounded (trips, location
           history, reports), and once the header scrolls away every
           row becomes a line of unlabelled values. */
        .card .table > thead.table-light > tr > th {
            position: sticky; top: 0; z-index: 2;
            background: #fff;
            box-shadow: inset 0 -2px 0 #e9ecef;
        }
        .card .table > thead.table-light > tr > th:first-child {
            border-top-left-radius: var(--bs-border-radius);
        }
        .card .table > thead.table-light > tr > th:last-child {
            border-top-right-radius: var(--bs-border-radius);
        }

        /* ---- Row actions -----------------------------------------
           Action links sat at the same weight as the data, so a row
           read as one undifferentiated run of text. Group them, quiet
           them, and let them come forward on hover. */
        .card .table td .btn-sm,
        .card .table td .btn-group .btn { --bs-btn-padding-y: .2rem; --bs-btn-padding-x: .5rem; }
        .card .table tbody tr > td:last-child { white-space: nowrap; }
        .card .table tbody tr a.btn-outline-secondary { border-color: transparent; }
        .card .table tbody tr:hover a.btn-outline-secondary { border-color: #dee2e6; }

        /* Numeric columns line up only if the figures are tabular. */
        .card .table .text-end { font-variant-numeric: tabular-nums; }

        /* ---- Page toolbar: survive a narrow window ---------------- */
        @media (max-width: 575.98px) {
            .page-toolbar { flex-wrap: wrap; gap: .75rem; }
            .page-toolbar > .btn, .page-toolbar > a.btn { width: 100%; }
        }

        /* ---- Alerts: calmer, and the icon carries the meaning ----- */
        .alert { border: 1px solid; border-left-width: 4px; }
        .alert-info { background: #f4f9ff; border-color: #cfe4fb; border-left-color: #6ea8fe; color: #1c3d5a; }

        /* ---- Dropdowns & modals ---------------------------------- */
        .dropdown-menu {
            border: 1px solid #e9ecef;
            box-shadow: 0 4px 10px rgba(15, 23, 42, .06), 0 12px 32px rgba(15, 23, 42, .10);
            padding-block: .35rem;
        }
        .dropdown-item { padding-block: .45rem; font-size: .925rem; }
        .dropdown-item:active { background-color: var(--amoec-brand); color: var(--amoec-on-brand); }
        .modal-content { border: 0; box-shadow: 0 24px 60px rgba(15, 23, 42, .22); }
        .modal-header, .modal-footer { border-color: #eef1f4; }

        /* ---- Scrollbars: present, not loud ----------------------- */
        .sidebar, .table-responsive, .notif-items {
            scrollbar-width: thin; scrollbar-color: #cbd2da transparent;
        }
        .sidebar::-webkit-scrollbar,
        .table-responsive::-webkit-scrollbar,
        .notif-items::-webkit-scrollbar { width: 8px; height: 8px; }
        .sidebar::-webkit-scrollbar-thumb,
        .table-responsive::-webkit-scrollbar-thumb,
        .notif-items::-webkit-scrollbar-thumb {
            background: #cbd2da; border-radius: 4px;
        }
        .sidebar::-webkit-scrollbar-track,
        .table-responsive::-webkit-scrollbar-track,
        .notif-items::-webkit-scrollbar-track { background: transparent; }

        /* ---- Selection picks up the tenant's colour -------------- */
        ::selection { background: rgba(var(--amoec-brand-rgb), .22); }

        /* ---- Show/hide password ----------------------------------- */
        .pw-wrap { position: relative; display: block; }
        .pw-wrap > .form-control { padding-right: 2.75rem; }
        /* Bootstrap paints the invalid glyph at the right edge, where the
           eye now sits. Slide it inboard so the two don't stack. */
        .pw-wrap > .form-control.is-invalid { background-position: right 2.5rem center; }
        /* The wrapper must stay EXACTLY as tall as the input it wraps.
           .pw-toggle is centred on the wrapper (top:50% + translateY), so
           anything else inside it moves the eye: the validation message used
           to be carried in here, which made the wrapper field-plus-message
           tall and pushed the eye down through the bottom border — visible
           on the driver Create-login form the moment a password was
           rejected. The message therefore stays OUTSIDE, as a sibling.

           Bootstrap reveals it with `.is-invalid ~ .invalid-feedback`, and
           that selector stops matching once the input is nested in the
           wrapper, so the toggle script tags the message instead and this
           rule does the revealing. */
        .invalid-feedback.pw-feedback { display: block; }
        .pw-toggle {
            position: absolute; top: 50%; right: .4rem; transform: translateY(-50%);
            border: 0; background: transparent; color: #6c757d;
            padding: .3rem .4rem; line-height: 1; cursor: pointer;
            border-radius: .3rem;
            transition: color .15s ease, background-color .15s ease;
        }
        .pw-toggle:hover { color: var(--amoec-brand); background: rgba(var(--amoec-brand-rgb), .1); }
        .pw-toggle:focus-visible { outline: 2px solid var(--amoec-brand); outline-offset: 1px; }

        /* ---- Type-to-filter select -------------------------------- */
        .ss-wrap { position: relative; }
        .ss-menu {
            position: absolute; z-index: 1056; left: 0; right: 0; top: calc(100% + 2px);
            max-height: 16rem; overflow-y: auto;
            background: #fff; border: 1px solid rgba(0,0,0,.15);
            border-radius: .375rem; box-shadow: 0 .5rem 1rem rgba(0,0,0,.15);
            padding: .25rem 0; margin: 0; list-style: none;
        }
        .ss-menu[hidden] { display: none; }
        .ss-opt { padding: .4rem .75rem; cursor: pointer; display: flex; gap: .5rem; align-items: baseline; }
        .ss-opt:hover, .ss-opt.active { background: rgba(var(--amoec-brand-rgb), .12); }
        .ss-opt[aria-disabled="true"] { cursor: not-allowed; opacity: .65; }
        .ss-opt[aria-disabled="true"]:hover, .ss-opt[aria-disabled="true"].active { background: #f8f9fa; }
        /* Two tones, and the difference is load-bearing. Red means the choice is
           refused; grey means it is merely busy elsewhere and still selectable. */
        .ss-note { margin-left: auto; font-size: .75rem; white-space: nowrap; color: var(--bs-danger, #dc3545); }
        .ss-note.ss-note-info { color: #6c757d; }
        .ss-group { padding: .35rem .75rem; font-size: .72rem; text-transform: uppercase;
                    letter-spacing: .04em; color: #6c757d; }
        .ss-empty { padding: .6rem .75rem; color: #6c757d; font-size: .875rem; }
    </style>
    @stack('head')
</head>
<body>
<div class="container-fluid">
    <div class="row">
        {{-- offcanvas-md: a slide-in drawer below `md`, an ordinary column at `md` and
             up. The nav used to be col-12 on small screens, so it stacked ABOVE the
             content as a full-height block and every page opened on a wall of menu —
             the dashboard was a scroll away on a phone or a narrow window. Bootstrap's
             own component, and its JS bundle is already loaded, so this costs nothing. --}}
        <nav class="offcanvas-md offcanvas-start col-12 col-md-3 col-lg-2 sidebar p-3"
             tabindex="-1" id="adminSidebar" aria-label="Main navigation">
            <div class="d-flex align-items-center gap-3 mb-4">
                @if (! empty($orgLogoUrl))
                    <img src="{{ $orgLogoUrl }}" alt="{{ $brandLabel }} logo" class="brand-logo">
                @else
                    <span class="brand-mark">{{ $brandMark ?? Str::substr(config('app.name', 'AMOEC Admin'), 0, 2) }}</span>
                @endif
                <strong class="text-truncate brand-wordmark">{{ $brandLabel ?? config('app.name', 'AMOEC Admin') }}</strong>
                {{-- Only reachable while the drawer is open, which is only below md. --}}
                <button type="button" class="btn-close ms-auto d-md-none"
                        data-bs-dismiss="offcanvas" data-bs-target="#adminSidebar"
                        aria-label="Close menu"></button>
            </div>
            <ul class="nav flex-column">
                <li class="nav-section-label">Overview</li>
                <li class="nav-item">
                    <a class="nav-link @yield('nav_dashboard')" href="{{ route('dashboard') }}">
                        <i class="bi bi-speedometer2 me-2"></i>Dashboard
                    </a>
                </li>
                @if ($ctx->isOrgAdmin())
                    <li class="nav-item">
                        <a class="nav-link @yield('nav_operations')" href="{{ route('operations.index') }}">
                            <i class="bi bi-broadcast me-2"></i>Live Ops
                        </a>
                    </li>
                @endif
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
                    <li class="nav-item">
                        <a class="nav-link @yield('nav_onboarding')" href="{{ route('onboarding.create') }}">
                            <i class="bi bi-rocket-takeoff me-2"></i>Onboard co-op
                        </a>
                    </li>
                @endif
                {{-- Tenant data. Hidden from a platform admin, and refused by the
                     tenant.admin middleware on the routes themselves, so a stale
                     bookmark does not become a back door. --}}
                @if ($ctx->isOrgAdmin())
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
                    <li class="nav-section-label">Settings</li>
                    <li class="nav-item">
                        <a class="nav-link @yield('nav_organization')" href="{{ route('organization.edit') }}">
                            <i class="bi bi-building-gear me-2"></i>Organization
                        </a>
                    </li>
                @endif
            </ul>
        </nav>

        {{-- p-3 below md: 1.5rem of padding either side of a 360px screen is most of
             a table column. --}}
        <main class="col-12 col-md-9 col-lg-10 p-3 p-md-4">
            {{-- gap-3 and flex-wrap: on a narrow window the heading and the action
                 cluster used to collide rather than wrap. --}}
            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
                <div class="d-flex align-items-center gap-2 min-w-0">
                    <button type="button" class="btn btn-light border d-md-none flex-shrink-0"
                            data-bs-toggle="offcanvas" data-bs-target="#adminSidebar"
                            aria-controls="adminSidebar" aria-label="Open navigation menu">
                        <i class="bi bi-list"></i>
                    </button>
                    <div class="min-w-0">
                        <h1 class="h4 mb-0 text-truncate">@yield('heading', 'Dashboard')</h1>
                        <small class="text-muted d-block text-truncate"><i class="bi bi-building me-1"></i>{{ $orgName ?? 'All organizations' }}</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                {{-- Notification bell: passenger reports awaiting triage. Clears itself
                     as reports move out of "Pending", so there is no read-state to manage.

                     Rendered here for first paint, then kept current by the poller at the
                     bottom of this file — an admin parked on Live Ops must not have to
                     refresh to learn a report came in. Both this markup and the poller's
                     DOM building read the same shape (App\Support\PendingReportFeed);
                     change one and you must change the other. --}}
                @php $notifCount = $notifications['count'] ?? 0; @endphp
                @if ($ctx->isOrgAdmin())
                <div class="dropdown" id="notif-bell" data-endpoint="{{ route('notifications.pending') }}">
                    <button class="btn btn-light border position-relative d-inline-flex align-items-center"
                            type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"
                            id="notif-toggle"
                            aria-label="Notifications{{ $notifCount > 0 ? ' (' . $notifCount . ' pending reports)' : '' }}">
                        <i class="bi {{ $notifCount > 0 ? 'bi-bell-fill' : 'bi-bell' }}" id="notif-icon"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger{{ $notifCount > 0 ? '' : ' d-none' }}"
                              id="notif-badge">
                            <span id="notif-badge-count">{{ $notifCount > 99 ? '99+' : $notifCount }}</span>
                            <span class="visually-hidden">pending reports</span>
                        </span>
                    </button>
                    {{-- Screen readers get told when the count GOES UP; the badge alone is a
                         silent visual change. polite, so it never interrupts. --}}
                    <span class="visually-hidden" role="status" aria-live="polite" id="notif-live"></span>
                    {{-- A <div> menu rather than <ul>/<li>: Bootstrap 5 supports both, and it
                         lets the item region be one addressable container for the poller. --}}
                    <div class="dropdown-menu dropdown-menu-end shadow p-0" style="min-width: 22rem; max-width: 95vw;">
                        <div class="d-flex justify-content-between align-items-center px-3 py-2">
                            <strong class="small">Notifications</strong>
                            <span class="badge text-bg-warning{{ $notifCount > 0 ? '' : ' d-none' }}" id="notif-pill">
                                {{ $notifCount }} pending
                            </span>
                        </div>
                        <hr class="dropdown-divider my-0">

                        <div class="notif-items" id="notif-items">
                            @forelse ($notifications['items'] ?? [] as $item)
                                <a class="dropdown-item py-2" href="{{ $item['url'] }}">
                                    <div class="d-flex gap-2">
                                        <i class="bi bi-flag-fill text-warning mt-1"></i>
                                        {{-- min-width:0 is what lets .text-truncate actually clamp inside a flex child. --}}
                                        <div class="flex-grow-1" style="min-width: 0;">
                                            <div class="d-flex justify-content-between gap-2">
                                                <span class="fw-semibold text-truncate">{{ $item['title'] }}</span>
                                                <small class="text-muted flex-shrink-0">{{ $item['age'] }}</small>
                                            </div>
                                            <div class="small text-muted text-truncate">{{ $item['subtitle'] }}</div>
                                        </div>
                                    </div>
                                </a>
                            @empty
                                <div class="px-3 py-4 text-center text-muted small">
                                    <i class="bi bi-check2-circle d-block fs-4 mb-1"></i>
                                    You're all caught up
                                </div>
                            @endforelse
                        </div>

                        <hr class="dropdown-divider my-0">
                        <a class="dropdown-item text-center small py-2" href="{{ route('reports.index') }}">
                            View all reports
                        </a>
                    </div>
                </div>
                @endif

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
                            <a class="dropdown-item" href="{{ route('account.edit') }}">
                                <i class="bi bi-person-gear me-2"></i>My account
                            </a>
                        </li>
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
            </div>

            {{-- Success auto-dismisses; the error alert below deliberately does not.
                 A confirmation has been read by the time it is understood, but an
                 error usually needs acting on, and timing a user out of reading it
                 is how a failure gets missed. --}}
            @if (session('status'))
                <div class="alert alert-success alert-dismissible fade show" role="alert" data-autodismiss="7000">
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

{{-- Keeps the notification bell current without a page refresh.

     Polling, not Realtime: the shared Supabase `supabase_realtime` publication carries
     only `vehicle_live_location` (Android migration 20260430_008), so a browser
     subscription on `reports` would connect to nothing. Reports also arrive on human
     timescales — a 20s poll is indistinguishable from live for this, and costs one
     small JSON request per idle tab. --}}
<script>
    (function () {
        var bell = document.getElementById('notif-bell');
        if (!bell || !window.fetch) return;

        var endpoint = bell.dataset.endpoint;
        var toggleEl = document.getElementById('notif-toggle');
        var iconEl = document.getElementById('notif-icon');
        var badgeEl = document.getElementById('notif-badge');
        var badgeCountEl = document.getElementById('notif-badge-count');
        var pillEl = document.getElementById('notif-pill');
        var itemsEl = document.getElementById('notif-items');
        var liveEl = document.getElementById('notif-live');

        // 3s in production, 30s under `php artisan serve`.
        //
        // This was one number, 60s, and the reason behind it was sound: the built-in
        // server `artisan serve` uses handles ONE request at a time, so every poll
        // occupies the worker the admin's next page load needs. But that is a property
        // of the DEVELOPMENT server, not of this application — the container the console
        // actually ships in runs Apache with a pool of workers and has no such problem.
        // Paying a minute of latency in production to protect a local convenience was
        // the wrong way round, so the two cases are now separated.
        //
        // 3s because the thing this bell reports — a passenger filing a misconduct
        // report — has to appear while somebody is looking at the screen, not a minute
        // later. It costs one indexed query per tick (see PendingReportFeed), and a
        // hidden tab polls not at all, so the bill is bounded by the number of admins
        // actively watching. That is the case worth spending on.
        //
        // This is a floor, not the destination. The honest answer is a push from the
        // database, because the report is inserted into Supabase by the Android app and
        // never passes through Laravel at all — so no amount of cleverness here learns
        // about it any sooner than asking. See the realtime design discussion before
        // lowering this number further; past about 3s the right move is a socket, not a
        // faster poll.
        var POLL_MS = {{ PHP_SAPI === 'cli-server' ? 30000 : 3000 }};
        var lastCount = parseInt(badgeCountEl.textContent, 10) || 0;
        var timer = null;
        var inFlight = false;
        var menuOpen = false;
        var deferred = null; // payload that arrived while the menu was open

        function render(data) {
            var count = data.count || 0;

            badgeCountEl.textContent = count > 99 ? '99+' : String(count);
            badgeEl.classList.toggle('d-none', count === 0);
            pillEl.classList.toggle('d-none', count === 0);
            pillEl.textContent = count + ' pending';
            iconEl.className = 'bi ' + (count > 0 ? 'bi-bell-fill' : 'bi-bell');
            toggleEl.setAttribute(
                'aria-label',
                count > 0 ? 'Notifications (' + count + ' pending reports)' : 'Notifications'
            );

            // Only announce/animate on an INCREASE. Triaging reports away is the admin's
            // own doing and needs no nudge.
            if (count > lastCount) {
                liveEl.textContent = count + ' passenger ' + (count === 1 ? 'report' : 'reports') + ' awaiting triage';
                iconEl.classList.remove('notif-bump');
                void iconEl.offsetWidth; // restart the animation
                iconEl.classList.add('notif-bump');
            }
            lastCount = count;

            // Rebuilding the list under an open menu moves rows beneath the cursor, so
            // the caller defers that; here we only ever redraw a closed menu.
            itemsEl.textContent = '';
            if (!data.items || data.items.length === 0) {
                itemsEl.appendChild(emptyState());
                return;
            }
            data.items.forEach(function (item) {
                itemsEl.appendChild(itemRow(item));
            });
        }

        function emptyState() {
            var wrap = el('div', 'px-3 py-4 text-center text-muted small');
            wrap.appendChild(el('i', 'bi bi-check2-circle d-block fs-4 mb-1'));
            wrap.appendChild(document.createTextNode("You're all caught up"));
            return wrap;
        }

        // Mirrors the Blade markup above. Built with textContent, never innerHTML —
        // plate numbers and route names are admin-entered strings.
        function itemRow(item) {
            var link = el('a', 'dropdown-item py-2');
            link.href = item.url;

            var row = el('div', 'd-flex gap-2');
            row.appendChild(el('i', 'bi bi-flag-fill text-warning mt-1'));

            var body = el('div', 'flex-grow-1');
            body.style.minWidth = '0';

            var head = el('div', 'd-flex justify-content-between gap-2');
            var title = el('span', 'fw-semibold text-truncate');
            title.textContent = item.title || 'Report';
            var age = el('small', 'text-muted flex-shrink-0');
            age.textContent = item.age || '';
            head.appendChild(title);
            head.appendChild(age);

            var sub = el('div', 'small text-muted text-truncate');
            sub.textContent = item.subtitle || 'Unassigned';

            body.appendChild(head);
            body.appendChild(sub);
            row.appendChild(body);
            link.appendChild(row);
            return link;
        }

        function el(tag, className) {
            var node = document.createElement(tag);
            node.className = className;
            return node;
        }

        function poll() {
            if (inFlight || document.hidden) return;
            inFlight = true;

            fetch(endpoint, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (res) {
                // The session lapsed (supabase.admin denies JSON requests with a 401).
                // Stop polling rather than hammering the endpoint until the tab is
                // reloaded — the next navigation bounces to the login screen anyway.
                if (res.status === 401) { stop(); return null; }
                if (!res.ok) return null;
                return res.json();
            }).then(function (data) {
                if (!data) return;
                if (menuOpen) { deferred = data; return; }
                render(data);
            }).catch(function () {
                // Offline or a dropped request: keep the last known state and try again
                // on the next tick. A transient failure must not blank the bell.
            }).then(function () {
                inFlight = false;
            });
        }

        function start() {
            if (timer === null) timer = setInterval(poll, POLL_MS);
        }

        function stop() {
            if (timer !== null) { clearInterval(timer); timer = null; }
        }

        bell.addEventListener('show.bs.dropdown', function () { menuOpen = true; });
        bell.addEventListener('hidden.bs.dropdown', function () {
            menuOpen = false;
            if (deferred) { render(deferred); deferred = null; }
        });

        // A background tab neither polls nor accrues ticks; coming back to the tab
        // refreshes immediately, which is when staleness is actually visible.
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) { stop(); return; }
            start();
            poll();
        });

        start();
    })();
</script>
<script>
    /* Show/hide for every password field in the console.
       ----------------------------------------------------------------
       A super admin types a password here that they must then pass on to
       the person it belongs to, so "what did I just type?" is the normal
       case, not an edge case. Done once at the layout level rather than
       per form, so create-admin, create-driver, both reset dialogs and
       the account page all get it from one place.

       The input is wrapped rather than rebuilt as a Bootstrap
       .input-group: the group markup would change validation styling and
       these fields rely on .is-invalid / .invalid-feedback. Wrapping does
       break the CSS sibling link those two need, so the message is tagged
       for a rule of our own — see .invalid-feedback.pw-feedback in the
       stylesheet, and the note there explaining why it must NOT simply be
       moved inside the wrapper. */
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('input[type="password"]').forEach(function (input) {
            if (input.dataset.pwToggle || input.closest('.input-group')) return;
            input.dataset.pwToggle = '1';

            var wrap = document.createElement('div');
            wrap.className = 'pw-wrap';
            input.parentNode.insertBefore(wrap, input);
            wrap.appendChild(input);

            // Leave .invalid-feedback where it is and tag it, rather than moving it
            // into the wrapper: the wrapper has to stay exactly as tall as the input
            // or the absolutely-centred eye drifts below the field. See the CSS.
            var next = wrap.nextElementSibling;
            if (next && next.classList.contains('invalid-feedback')) next.classList.add('pw-feedback');

            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'pw-toggle';
            btn.setAttribute('aria-label', 'Show password');
            btn.setAttribute('aria-pressed', 'false');
            btn.innerHTML = '<i class="bi bi-eye" aria-hidden="true"></i>';
            wrap.appendChild(btn);

            btn.addEventListener('click', function () {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.setAttribute('aria-pressed', show ? 'true' : 'false');
                btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                btn.querySelector('i').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
                input.focus();
            });
        });
    });
</script>
<script>
    /* Type-to-filter for long <select> lists.
       ----------------------------------------------------------------
       Opt in with data-searchable on the select. The native element stays
       in the DOM and keeps its name, value, required flag and .is-invalid
       styling -- it is still the control that submits. All this adds is a
       text input in front of it and a filtered list, so an admin with
       thirty drivers types three letters instead of scrolling.

       No library. Tom Select and friends would each be another CDN
       dependency on a console whose styling already fails to a blank page
       when the network blocks jsdelivr, and the whole behaviour here is a
       filter over options we already have.

       Options may be marked unavailable from outside by setting
       data-ss-note on the <option>: the row then shows that note, is not
       selectable, and sorts no differently. The assignment form uses it
       for a vehicle or driver already taken on the chosen route. Nothing
       is removed, because "why is this bus missing?" is a worse question
       than "why is this bus greyed out?".

       Degrades honestly: if the script never runs, the native select is
       untouched and fully usable. */
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('select[data-searchable]').forEach(function (select) {
            if (select.dataset.ssReady) return;
            select.dataset.ssReady = '1';

            var wrap = document.createElement('div');
            wrap.className = 'ss-wrap';
            select.parentNode.insertBefore(wrap, select);
            wrap.appendChild(select);
            select.classList.add('visually-hidden');
            select.setAttribute('tabindex', '-1');
            select.setAttribute('aria-hidden', 'true');

            var input = document.createElement('input');
            input.type = 'text';
            input.className = select.className.replace('visually-hidden', '').trim();
            input.setAttribute('role', 'combobox');
            input.setAttribute('aria-expanded', 'false');
            input.setAttribute('aria-autocomplete', 'list');
            input.autocomplete = 'off';
            input.placeholder = select.dataset.searchPlaceholder || 'Type to search…';
            if (select.id) input.id = select.id + '_search';
            wrap.insertBefore(input, select);

            // The label points at the hidden select; move it to the box people type in.
            if (select.id) {
                var lbl = document.querySelector('label[for="' + select.id + '"]');
                if (lbl && input.id) lbl.setAttribute('for', input.id);
            }

            var menu = document.createElement('ul');
            menu.className = 'ss-menu';
            menu.hidden = true;
            menu.setAttribute('role', 'listbox');
            wrap.appendChild(menu);

            var activeIndex = -1;
            var rows = [];

            function selectedLabel() {
                var o = select.options[select.selectedIndex];
                return o && o.value !== '' ? o.textContent.trim() : '';
            }

            function commit(option) {
                if (!option || option.disabled) return;
                if (option.dataset.ssNote && option.dataset.ssNoteTone !== 'info') return;
                select.value = option.value;
                select.dispatchEvent(new Event('change', { bubbles: true }));
                input.value = option.textContent.trim();
                close();
            }

            function close() {
                menu.hidden = true;
                input.setAttribute('aria-expanded', 'false');
                activeIndex = -1;
            }

            function paintActive() {
                rows.forEach(function (r, i) { r.el.classList.toggle('active', i === activeIndex); });
                if (activeIndex >= 0 && rows[activeIndex]) {
                    rows[activeIndex].el.scrollIntoView({ block: 'nearest' });
                }
            }

            function open(filter) {
                var q = (filter || '').trim().toLowerCase();
                menu.textContent = '';
                rows = [];
                var lastGroup = null;

                Array.prototype.forEach.call(select.options, function (opt) {
                    if (opt.value === '') return;                 // the placeholder row
                    var text = opt.textContent.trim();
                    if (q && text.toLowerCase().indexOf(q) === -1) return;

                    var groupLabel = opt.parentElement && opt.parentElement.tagName === 'OPTGROUP'
                        ? opt.parentElement.label : null;
                    if (groupLabel && groupLabel !== lastGroup) {
                        var g = document.createElement('li');
                        g.className = 'ss-group';
                        g.textContent = groupLabel;
                        menu.appendChild(g);
                        lastGroup = groupLabel;
                    }

                    var li = document.createElement('li');
                    li.className = 'ss-opt';
                    li.setAttribute('role', 'option');

                    var name = document.createElement('span');
                    name.textContent = text;
                    li.appendChild(name);

                    // data-ss-note-tone="info" annotates WITHOUT refusing: the option is
                    // busy somewhere else but still a legal choice here. Anything else
                    // means blocked, and the row stops being selectable.
                    var note = opt.dataset.ssNote;
                    var advisory = opt.dataset.ssNoteTone === 'info';
                    if (note) {
                        var n = document.createElement('span');
                        n.className = 'ss-note' + (advisory ? ' ss-note-info' : '');
                        n.textContent = note;
                        li.appendChild(n);
                        if (!advisory) li.setAttribute('aria-disabled', 'true');
                    }
                    if (!note || advisory) {
                        li.addEventListener('mousedown', function (e) { e.preventDefault(); commit(opt); });
                    }

                    menu.appendChild(li);
                    rows.push({ el: li, option: opt, selectable: !note || advisory });
                });

                if (!rows.length) {
                    var empty = document.createElement('li');
                    empty.className = 'ss-empty';
                    empty.textContent = 'No matches';
                    menu.appendChild(empty);
                }

                menu.hidden = false;
                input.setAttribute('aria-expanded', 'true');
                activeIndex = rows.findIndex(function (r) { return r.selectable; });
                paintActive();
            }

            function step(delta) {
                if (menu.hidden) { open(input.value); return; }
                if (!rows.length) return;
                var i = activeIndex;
                for (var n = 0; n < rows.length; n++) {
                    i = (i + delta + rows.length) % rows.length;
                    if (rows[i].selectable) { activeIndex = i; break; }
                }
                paintActive();
            }

            input.addEventListener('focus', function () { open(''); input.select(); });
            input.addEventListener('input', function () { open(input.value); });
            input.addEventListener('blur', function () {
                // Restore the committed label: a half-typed query left in the box reads
                // as a selection that was never made.
                window.setTimeout(function () { input.value = selectedLabel(); close(); }, 120);
            });
            input.addEventListener('keydown', function (e) {
                if (e.key === 'ArrowDown') { e.preventDefault(); step(1); }
                else if (e.key === 'ArrowUp') { e.preventDefault(); step(-1); }
                else if (e.key === 'Enter') {
                    if (!menu.hidden && activeIndex >= 0) { e.preventDefault(); commit(rows[activeIndex].option); }
                } else if (e.key === 'Escape') { input.value = selectedLabel(); close(); }
            });

            // Something else changed the select (the route-aware relabelling, or a
            // browser restoring form state) -- keep the visible box in step.
            select.addEventListener('change', function () { input.value = selectedLabel(); });
            input.value = selectedLabel();
        });
    });
</script>
<script>
    /* Auto-dismiss for flash confirmations.
       ----------------------------------------------------------------
       Opt-in via data-autodismiss="<ms>", so only the success alert takes
       it. The countdown PAUSES on hover and on keyboard focus: these
       messages carry details worth reading and sometimes copying (the
       username a new admin signs in with, for one), and snatching that
       away mid-read is worse than leaving the close button to do the job.
       The button stays, so dismissing early still works, and a reader who
       never moves the mouse still gets the full seven seconds. */
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-autodismiss]').forEach(function (el) {
            var ms = parseInt(el.dataset.autodismiss, 10) || 7000;
            var timer = null;

            function close() {
                if (window.bootstrap && bootstrap.Alert) {
                    bootstrap.Alert.getOrCreateInstance(el).close();
                } else {
                    el.remove();
                }
            }
            function start() { timer = window.setTimeout(close, ms); }
            function stop() { window.clearTimeout(timer); timer = null; }

            el.addEventListener('mouseenter', stop);
            el.addEventListener('mouseleave', start);
            el.addEventListener('focusin', stop);
            el.addEventListener('focusout', start);
            start();
        });
    });
</script>
@stack('scripts')
</body>
</html>
