# CLAUDE.md — AMOEC Track Web Admin

**Act as a Senior Full-Stack Web Developer, Mobile Programmer, and Software Engineer.**
Read this file at the start of **every phase/session** before writing code. Prefer correctness,
security, and **mobile↔web alignment** over speed. Verify claims against the actual files; don't
assume. When a change touches the shared Supabase schema or an enum, confirm the mobile app stays
aligned before you finish.

## What this is
The **Web Admin** for AMOEC Track — a multi-tenant bus-tracking platform (AMOEC = organization #1;
the system is generic for any transport cooperative). Two clients share **ONE Supabase backend**: the
Android app (`C:\Users\Admin\AndroidStudioProjects\AMOECTrack`) and this Laravel web admin. The full
plan, schema-reconciliation table, and phase order live in the Android repo's
**`MULTI_TENANT_ROADMAP.md`** (Phase 4 section). This project delivers Phase 4.

- **Stack:** Laravel 12 + PHP 8.2 + Bootstrap 5 (Blade). Local dev: XAMPP PHP + `php artisan serve`.
- **Backend:** Supabase (Postgres + GoTrue) — the SAME project the mobile app uses.

## Golden rules
1. **Security first.** NEVER commit `.env`. NEVER put the Supabase **service_role** key in this app or
   in git. `.env.example` holds placeholders only. The app reaches Postgres with the **session-pooler**
   DB credentials (data-access option A) and validates admin JWTs with `SUPABASE_JWT_SECRET`.
2. **Schema is single-sourced in the Android repo's `supabase/migrations/`.** Do NOT create Laravel
   migrations for shared tenant tables — model over the existing tables (`$incrementing = false`,
   `$keyType = 'string'`, UUID keys). Any schema change is a new numbered SQL migration in that folder
   (which mobile also reads). Laravel migrations are OK only for web-only helper tables.
3. **Tenant isolation is enforced IN CODE (option A bypasses RLS).** Every tenant query MUST be
   org-scoped via the global `OrganizationScope`. `super_admin` is cross-org (bypass); `org_admin` is
   scoped to its `organization_id`. Mirror the RLS model from migration 016 exactly — the web path has
   its own isolation test obligation (roadmap Phase 4 step 5).

## Roles (`app_role` enum — must match mobile `UserRole.kt`)
- `super_admin` — global platform admin (`organization_id = NULL`); cross-org; onboards cooperatives.
- `org_admin` — manages ONE organization (scoped by `organization_id`). The old `admin` was migrated here.
- `driver`, `passenger` — mobile only; NOT allowed into the web admin.

## Auth (GoTrue — one shared identity, no Laravel users table)
- Admins sign in via Supabase GoTrue. This app validates the JWT (HS256, `SUPABASE_JWT_SECRET`), reads
  `sub`, then loads the **app role + organization_id from `public.profiles`** (the default GoTrue JWT
  does NOT carry them — same source of truth as RLS `current_app_role()` / `current_org_id()`).
- Web admin is **admin-only**: reject any profile whose role isn't `org_admin` / `super_admin`,
  or that is inactive.

## Schema / enum alignment cheat-sheet (MUST match the mobile app)
- **report categories** — store the enum, display mobile's labels: `safety`→"Safety",
  `driver_behavior`→"Driver Behavior", `vehicle_condition`→"Vehicle Condition",
  `route_issue`→"Route Issue", `other`→"Other". (Mockup's "Misconduct/Reckless Driving" → `driver_behavior`.)
- **report status** — `submitted`→Pending, `reviewing`→Reviewed, `resolved`→Resolved, plus surface `dismissed`.
- **occupancy_status** has **4** states incl. `full`→"Full" (the mockup showed 3 — show all 4).
- **vehicles.status** (`active`/`maintenance`/`inactive`): keep `is_active = (status == 'active')` in
  sync on every write (NO DB trigger) so RLS visibility stays correct for passengers.
- **route_geometry.points** is **`[lat, lng]`** (NOT GeoJSON `[lng, lat]`); `point_count >= 2`. Swap
  each pair on GeoJSON import or the mobile overlay/snap will be transposed.
- **driver record** = join `profiles` (full_name, role, is_active) + `drivers` (employee_code,
  license_number, phone) + `auth.users` (email).
- **anonymous reports** = dropped from the web UI (`reports.passenger_profile_id` is NOT NULL).

## Commands
- `php artisan serve` → http://127.0.0.1:8000
- `php artisan route:list` — sanity-check routing/boot.
- Do NOT run `php artisan migrate` against Supabase for shared tables (see rule 2).
