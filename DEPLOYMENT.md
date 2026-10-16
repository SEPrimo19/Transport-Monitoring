# Deploying the admin console

Written 16 October 2026, for the final defense. Target: **Laravel Cloud**, on its
free `*.laravel.cloud` subdomain, with a custom domain attached later.

Everything in the "What this app needs" section was verified against the code on
that date, not assumed.

---

## What this app needs

| | |
|---|---|
| PHP | 8.2+ with the `pgsql` extension |
| Composer deps | `laravel/framework` ^12, `firebase/php-jwt` ^7.1, `guzzlehttp/guzzle` ^7.14 |
| Node build | **None.** `@vite` appears only in `welcome.blade.php`, and `/` redirects to `/dashboard`, so that view is unreachable. Bootstrap 5 loads from `cdn.jsdelivr.net`. |
| Its own database | **None.** `SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`. All real data lives in Supabase. |
| Queue worker | **None.** Nothing is dispatched to a queue. |
| Persistent disk | **None required.** `storage/` holds only file sessions, the config cache, and logs. Losing it logs admins out; nothing else. |
| Outbound hosts | Supabase (REST + GoTrue + Postgres), `cdn.jsdelivr.net`, `tiles.openfreemap.org`, `router.project-osrm.org`, `nominatim.openstreetmap.org` |

The CSP in `app/Http/Middleware/SecurityHeaders.php` builds the Supabase origin
from config and pins only the CDN and the tile host. **No CSP rule names this
app's own domain**, so changing the domain cannot break it.

---

## ⚠ Do NOT set a deploy command that runs migrations

Laravel Cloud's own quickstart tells you to set the deploy command to
`php artisan migrate --force`. **Do not do that here.**

`DB_CONNECTION=pgsql` points at the live Supabase database. This repo still
carries Laravel's three default migrations, and running them would create eight
tables in the Supabase `public` schema:

```
cache   cache_locks   failed_jobs   job_batches
jobs    password_reset_tokens   sessions   users
```

The application references **none** of them at runtime — zero occurrences of
each in `app/`. They are leftover scaffolding. Creating them would:

* pollute the schema that the capstone's ERD figures and data dictionary
  document, days before the defense; and
* put a `public.users` table next to `public.profiles`, which is exactly the
  kind of thing a panelist inspecting the database would ask about.

**Leave the deploy command empty.** There is nothing this app needs to run at
deploy time.

---

## Steps

### 1. Connect the repository

Laravel Cloud deploys from GitHub. The repo is
`SEPrimo19/Transport-Monitoring` (private) and `main` is current as of
commit `f405cef`.

Authorise the Laravel Cloud GitHub App against that repository, then create an
application from it. Private repos are supported.

### 2. Do not provision a Laravel Cloud database

Supabase *is* the database. If you create a Cloud Postgres, Cloud will inject
its own `DB_*` variables and the console will talk to an empty database instead
of yours. Skip that step entirely.

### 3. Set the build command

```
composer install --no-dev --optimize-autoloader && php artisan optimize
```

`php artisan optimize` is safe here: there are **no `env()` calls outside
`config/`** (verified), so config caching cannot blank a setting out. Do not add
`npm run build` — there are no assets to compile.

### 4. Leave the deploy command empty

See the warning above.

### 5. Set the environment variables

Copy the three Supabase values and the database password out of your local
`.env`. Everything else is literal.

```
APP_NAME="AMOEC Track Admin"
APP_ENV=production
APP_KEY=                      # copy the base64: line from local .env, or: php artisan key:generate --show
APP_DEBUG=false
APP_URL=https://<your-app>.laravel.cloud

LOG_CHANNEL=stderr
LOG_LEVEL=error

SESSION_DRIVER=file
SESSION_SECURE_COOKIE=true
SESSION_LIFETIME=120
CACHE_STORE=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
MAIL_MAILER=log

DB_CONNECTION=pgsql
DB_HOST=                      # Supabase pooler host
DB_PORT=5432                  # session mode — keep 5432, not 6543
DB_DATABASE=postgres
DB_USERNAME=                  # postgres.<project-ref>
DB_PASSWORD=
DB_SSLMODE=require

SUPABASE_URL=
SUPABASE_ANON_KEY=
SUPABASE_JWT_SECRET=
```

Notes:

* **`APP_ENV=production` is required**, not cosmetic.
  `AppServiceProvider` calls `URL::forceScheme('https')` only in production; on
  any other value the console will emit `http://` URLs behind Cloud's TLS and
  the browser will block them under the enforced CSP.
* **`APP_URL` must match the real URL exactly.** Redirects and asset URLs are
  built from it.
* **Keep `DB_PORT=5432`.** That is Supabase's session-mode pooler. Transaction
  mode (6543) does not reliably support the prepared statements Laravel's pgsql
  driver issues.
* `SUPABASE_JWT_ALGS` can be omitted — it defaults to `ES256,RS256`, and this
  project's JWKS publishes a single ES256 key.
* Do **not** add a `service_role` key. Nothing in this repo reads one, by
  design; see the comment in `config/supabase.php`.

### 6. Deploy, then check these four things

1. The sign-in page loads over `https://` with no mixed-content or CSP errors in
   the browser console.
2. A super-admin can sign in, and the session survives a page navigation.
3. Live Ops draws its map — that proves the tile host and the CSP agree.
4. In the Supabase SQL editor, confirm nothing was created:

   ```sql
   select tablename from pg_tables
    where schemaname = 'public'
      and tablename in ('users','sessions','cache','cache_locks',
                        'jobs','job_batches','failed_jobs',
                        'password_reset_tokens');
   -- expect: 0 rows
   ```

---

## Changing the domain later

Three steps, no rebuild:

1. Add the domain in Laravel Cloud and point the DNS records it gives you.
2. Change `APP_URL` to the new origin.
3. Redeploy so the config cache picks up the new `APP_URL`.

Nothing else references the hostname — not the CSP, not the Supabase config, not
the Android app (which talks to Supabase directly and never to this console).

Custom domains are available on the paid plans, not the free sandbox.

---

## After the defense: rotate the database password

The console authenticates to Postgres as `postgres.<project-ref>` — the
**database owner**. That credential is now sitting in a third-party platform's
environment store, which is a wider exposure than it had on localhost.

Once the defense is over:

1. Change the database password in the Supabase dashboard.
2. Update `DB_PASSWORD` in Laravel Cloud and redeploy.
3. Update your local `.env`.

The longer-term fix is a dedicated least-privilege Postgres role holding only
the grants the console needs — including the `auth.users` reads
`DriverController` performs. That was deliberately deferred so the console could
be up for the defense; it is worth doing before any real cooperative uses this.
