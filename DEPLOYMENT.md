# Deploying the admin console

Written 16 October 2026, for the final defense. Target: **Render's free tier**,
on a `*.onrender.com` subdomain, with a custom domain attached later.

Laravel Cloud was the first choice and is the better platform, but its signup
needs a payment card. Free shared PHP hosting (InfinityFree, GoogieHost and
that class) was ruled out on technical grounds rather than price: this console
has no database of its own, so every page needs an outbound HTTPS call to
Supabase *and* an outbound Postgres connection on port 5432, and those hosts
restrict outbound ports and filter scripted outbound requests.

Everything in "What this app needs" was verified against the code on that date.

---

## What this app needs

| | |
|---|---|
| PHP | 8.2+ with the `pgsql` extension |
| Composer deps | `laravel/framework` ^12, `firebase/php-jwt` ^7.1, `guzzlehttp/guzzle` ^7.14 |
| Node build | **None.** `@vite` appears only in `welcome.blade.php`, and `/` redirects to `/dashboard`, so that view is unreachable. Bootstrap 5 loads from `cdn.jsdelivr.net`. |
| Its own database | **None.** `SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`. All real data lives in Supabase. |
| Queue worker | **None.** Nothing is dispatched to a queue. |
| Persistent disk | **None required.** `storage/` holds only file sessions, the caches, and logs. |
| Outbound hosts | Supabase (REST + GoTrue + Postgres 5432), `cdn.jsdelivr.net`, `tiles.openfreemap.org`, `router.project-osrm.org`, `nominatim.openstreetmap.org` |

The CSP in `app/Http/Middleware/SecurityHeaders.php` builds the Supabase origin
from config and pins only the CDN and the tile host. **No CSP rule names this
app's own domain**, so changing the domain cannot break it.

---

## ⚠ Never add a deploy command that runs migrations

`DB_CONNECTION=pgsql` points at the live Supabase database, and this repo still
carries Laravel's three default migrations. Running them creates eight tables in
the Supabase `public` schema:

```
cache   cache_locks   failed_jobs   job_batches
jobs    password_reset_tokens   sessions   users
```

The application references **none** of them at runtime — zero occurrences of
each in `app/`. They are leftover scaffolding. Creating them would pollute the
schema the capstone's ERD figures and data dictionary document, and would put a
`public.users` next to `public.profiles`, which is exactly what a panelist
inspecting the database would ask about.

`render.yaml` declares no deploy or pre-deploy command, and the Dockerfile and
entrypoint both carry this note. Keep it that way.

---

## The files that do the work

| File | Does |
|---|---|
| `Dockerfile` | `php:8.3-apache`, adds `pdo_pgsql`/`pgsql`, serves `public/`, installs Composer deps with `--no-scripts` |
| `docker/entrypoint.sh` | Runtime setup: binds `$PORT`, derives `APP_URL`, creates writable dirs, builds the caches |
| `.dockerignore` | Keeps `.env`, `vendor/`, `node_modules/` and tests out of the image |
| `render.yaml` | Declares the service and every env var *name*; the seven secrets are prompted for |

Two decisions worth knowing about, because they look like omissions:

**Composer runs with `--no-scripts`.** `composer.json`'s `post-autoload-dump`
runs `php artisan package:discover`, which boots the framework. A Docker build
has no access to Render's environment, so booting there would run against an
empty environment. Discovery is deferred to the entrypoint.

**Config, route and view caches are built at container start, not at build.**
Same reason: caching config at build time would bake in empty values. This is
safe because there are **no `env()` calls outside `config/`** (verified) — a
stray one would silently read `null` once the config cache exists.

---

## Steps

### 1. Create the service

[render.com](https://render.com) → **New** → **Blueprint**, and point it at
`SEPrimo19/Transport-Monitoring` on `main`. Render reads `render.yaml` and
proposes the service already configured.

If you'd rather click it in: **New → Web Service**, same repo, runtime
**Docker**, plan **Free**, region **Singapore**, health check path **`/login`**.

### 2. Supply the seven secrets

Render prompts for every variable marked `sync: false`. Copy these out of your
local `.env`:

```
APP_KEY                (the whole base64:... string)
DB_HOST                (the Supabase pooler host)
DB_USERNAME            (postgres.<project-ref>)
DB_PASSWORD
SUPABASE_URL
SUPABASE_ANON_KEY
SUPABASE_JWT_SECRET
```

Everything else — `APP_ENV=production`, the drivers, `DB_PORT=5432` — is already
in `render.yaml` and needs no typing.

### 3. Do NOT add a Render Postgres

Supabase is the database. A Render Postgres would inject its own `DB_*`
variables and the console would talk to an empty database.

### 4. Deploy

The first build takes a few minutes: it compiles two PHP extensions and resolves
Composer. Watch for `entrypoint: serving on :10000 as production` in the logs —
that line means the caches built and Apache came up.

`APP_URL` is left unset on purpose. The entrypoint falls back to
`RENDER_EXTERNAL_URL`, so there is no need to deploy once just to learn the URL
and again to set it.

### 5. Check these four things

1. The sign-in page loads over `https://` with no CSP or mixed-content errors in
   the browser console.
2. A super-admin can sign in, and the session survives navigating to another
   page.
3. **Live Ops draws its map** — that proves the tile host and the enforced CSP
   agree.
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

## Living with the free tier

A free instance **sleeps after 15 minutes of inactivity** and takes roughly a
minute to answer the next request. Two consequences:

* **Load the console a few minutes before you present.** A cold start during a
  live demo is a minute of dead air.
* **A restart signs administrators out.** Sessions are files on an ephemeral
  disk. This is expected, not a bug to chase — you will be logging in during the
  demo anyway.

### Defense-day fallback

If Render misbehaves, serve the app from the laptop and tunnel it:

```bash
php artisan serve                                  # terminal 1
cloudflared tunnel --url http://localhost:8000     # terminal 2
```

That gives a public HTTPS URL in seconds, with no account, no card and no cold
start, running the local `.env` that already works. The URL is three random
words and changes each run, so generate it the morning of the defense.

---

## Changing the domain later

1. Add the domain in Render (Settings → Custom Domains) and point the DNS
   records it gives you. Free instances include two custom domains with managed
   TLS.
2. Set `APP_URL` explicitly to the new origin — this overrides the
   `RENDER_EXTERNAL_URL` fallback.
3. Redeploy so the config cache picks it up.

Nothing else references the hostname: not the CSP, not the Supabase config, and
not the Android app, which talks to Supabase directly and never to this console.

---

## After the defense: rotate the database password

The console authenticates to Postgres as `postgres.<project-ref>` — the
**database owner**. That credential now sits in a third-party platform's
environment store, a wider exposure than it had on localhost.

1. Change the database password in the Supabase dashboard.
2. Update `DB_PASSWORD` in Render and redeploy.
3. Update your local `.env`.

The longer-term fix is a dedicated least-privilege Postgres role holding only
the grants the console needs, including the `auth.users` reads
`DriverController` performs. Deferred so the console could be up for the
defense; worth doing before any real cooperative uses this.
