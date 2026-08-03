# Transport Monitoring — Web Admin

A web-based administration console for a public-transport (cooperative bus) tracking
platform. Cooperative staff use it to manage their fleet and routes, monitor live
vehicle positions, review passenger reports, and schedule trips. It shares one
Supabase backend with the companion **Android app** (drivers publish location and
occupancy; passengers track vehicles and file reports).

The console is **multi-tenant**: one platform can host many transport cooperatives.
Each cooperative sees only its own data; a platform administrator manages the
cooperatives themselves.

---

## Key features

- **Role-based access**
  - **Org admin** — runs one cooperative's day-to-day operations.
  - **Super admin** — runs the platform: creates cooperatives and assigns their admins.
- **Live Operations map** — near-real-time vehicle positions (auto-polling) with a
  freshness classification (Live / Recent / Delayed / Stale / Offline); shows only
  vehicles currently on an active trip.
- **Dashboard** — organization-scoped KPIs and recent activity.
- **Fleet & network management** — Vehicles, Drivers, Terminals, and Routes, with a
  **polyline map editor** (draw or auto-generate a road-following route via OSRM).
- **Scheduling** — trip schedules (interval or fixed times) and driver/vehicle
  **assignments**.
- **Trip history** — past and current trips with a GPS trace map and occupancy timeline.
- **Passenger reports** — view and triage reports with an **evidence viewer**
  (secure signed URLs) and support for **anonymous** reports (reporter masked).
- **Organization self-service** — each cooperative edits its **profile, branding
  (logo upload + colors that re-theme both the app and this console), region map
  pin, and data-retention** setting.
- **Onboarding wizard** — a super admin creates a new cooperative and promotes its
  first admin in one step.
- **My Account** — change your display name and password.

## Tech stack

- **Laravel 12** (PHP 8.2) + **Blade** views + **Bootstrap 5** (CDN)
- **Supabase** (PostgreSQL + GoTrue auth) as the shared backend
- Maps via **Leaflet** + OpenStreetMap; geocoding via Nominatim; routing via OSRM
- No local database is created — the schema lives on Supabase; sessions/cache/queue
  use the local file driver.

---

## Run it from scratch (for exploration)

These steps take a fresh machine to a running console at `http://127.0.0.1:8000`.

### 1. Install the prerequisites

You need **Git**, **PHP 8.2+** (with the `pgsql` and `pdo_pgsql` extensions), and
**Composer**.

- **Windows (easiest):** install [XAMPP](https://www.apachefriends.org/) (bundles
  PHP), then [Composer](https://getcomposer.org/download/), then
  [Git](https://git-scm.com/download/win). In `C:\xampp\php\php.ini`, make sure these
  two lines are **un-commented** (remove the leading `;`):
  ```
  extension=pgsql
  extension=pdo_pgsql
  ```
- **macOS:** `brew install php composer git` (PHP includes pgsql).
- Verify:
  ```bash
  php -v          # 8.2 or newer
  composer -V
  php -m | grep pgsql   # should list pgsql and pdo_pgsql
  ```

### 2. Clone the repository

```bash
git clone https://github.com/SEPrimo19/Transport-Monitoring.git
cd Transport-Monitoring
```

### 3. Install PHP dependencies

```bash
composer install
```

### 4. Create your environment file

```bash
cp .env.example .env      # Windows PowerShell: copy .env.example .env
```

Open `.env` and fill in the Supabase values. **These are secret and are NOT in the
repository — ask the team lead for them:**

```
SUPABASE_URL=...
SUPABASE_ANON_KEY=...
SUPABASE_JWT_SECRET=...

DB_CONNECTION=pgsql
DB_HOST=...            # Supabase session pooler host
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=...        # postgres.<project-ref>
DB_PASSWORD=...
DB_SSLMODE=require
```

Leave `SESSION_DRIVER`, `CACHE_STORE`, and `QUEUE_CONNECTION` as they are in the
example (`file` / `file` / `sync`) so no framework tables are created in the shared
database.

### 5. Generate the app key

```bash
php artisan key:generate
```

> **Do not run `php artisan migrate`.** The database schema is managed separately and
> is already deployed to Supabase. This app only reads/writes existing tables.

### 6. Start the server

```bash
php artisan serve
```

Open **http://127.0.0.1:8000** and sign in.

### 7. Sign in

Use the **org-admin or super-admin account the team lead created for you** (email +
password). Passenger/driver accounts from the mobile app **cannot** sign in here —
the console is admin-only by design.

---

## The mobile app

The driver/passenger Android app is a separate project. Install the **APK** shared by
the team lead; it connects to the same Supabase backend. Create a passenger account
right in the app to explore the rider experience.

## Troubleshooting

- **`could not find driver` / PDO pgsql errors** — the `pgsql`/`pdo_pgsql` PHP
  extensions aren't enabled (see step 1). Restart the terminal after enabling them.
- **Login says the account isn't permitted** — that account is a passenger/driver,
  not an admin. Ask the team lead to grant an admin role.
- **A blank Live Ops map / no vehicles** — there is no active trip right now; Live Ops
  only shows vehicles currently on a trip.
- **Ports** — if `8000` is taken, run `php artisan serve --port=8080`.

## Reporting bugs

When you find an issue, note: the page/URL, your role (org-admin/super-admin), what
you did, what you expected, what happened, and a screenshot if you can. Share it with
the team lead.
