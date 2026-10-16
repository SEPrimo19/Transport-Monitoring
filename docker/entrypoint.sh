#!/bin/sh
# Container start-up. Everything here is deferred to runtime on purpose: a
# Docker build cannot see the platform's environment variables, so anything that
# reads configuration has to happen now rather than in the image.
set -eu

# ── 1. Listen on the port the platform assigned ──────────────────────────────
# Render (and Koyeb, and Cloud Run) hand the port over in $PORT at start-up.
PORT="${PORT:-80}"
sed -ri "s/^Listen [0-9]+/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" \
    /etc/apache2/sites-available/000-default.conf

# ── 2. APP_URL, without a two-pass deploy ────────────────────────────────────
# APP_URL has to match the real origin or the session cookie is scoped wrong and
# sign-in appears to succeed and then bounce straight back to the login screen.
# The URL is not knowable until the service exists, so rather than deploy once
# to learn it and again to set it, fall back to the one the platform injects.
# Setting APP_URL explicitly still wins — which is what a custom domain needs.
if [ -z "${APP_URL:-}" ] && [ -n "${RENDER_EXTERNAL_URL:-}" ]; then
    APP_URL="${RENDER_EXTERNAL_URL}"
    export APP_URL
    echo "entrypoint: APP_URL unset, using RENDER_EXTERNAL_URL=${APP_URL}"
fi

# ── 3. Writable runtime directories ──────────────────────────────────────────
# Re-created on every boot: the platform's filesystem is ephemeral, and file
# sessions live here. A restart therefore signs administrators out, which is
# expected on a free instance that sleeps — it is not a bug to chase.
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# ── 4. Caches that need the real environment ─────────────────────────────────
# Safe to cache config here because there are no env() calls outside config/ —
# verified, and worth re-checking if that ever changes, because a stray env()
# silently returns null once the config cache exists.
#
# route:cache is safe because routes/web.php uses Route::redirect for '/' rather
# than a closure; a single closure route action would make this step fail.
php artisan package:discover --ansi
php artisan config:cache
php artisan route:cache
php artisan view:cache

# No migrations. See the note at the top of the Dockerfile.

echo "entrypoint: serving on :${PORT} as ${APP_ENV:-unset} (APP_URL=${APP_URL:-unset})"

exec apache2-foreground
