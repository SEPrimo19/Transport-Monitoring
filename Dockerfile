# Admin console image — built for Render's free tier, but plain enough to run on
# Koyeb, Fly, or any VPS later. Nothing in here is Render-specific except the
# $PORT handling in the entrypoint, and Render is not the only platform that
# assigns a port at runtime.
#
# Deliberately NOT here: any migration step. DB_CONNECTION points at the live
# Supabase database, and this repo still carries Laravel's three default
# migrations, which would create eight unused tables in the public schema
# (users, sessions, cache, cache_locks, jobs, job_batches, failed_jobs,
# password_reset_tokens). See DEPLOYMENT.md.
FROM php:8.3-apache

# pdo_pgsql is not optional: the console reads Supabase Postgres directly for
# the auth.users lookups in DriverController, on top of the PostgREST calls.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends libpq-dev; \
    docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql; \
    apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
    rm -rf /var/lib/apt/lists/*

# Laravel's public/.htaccess does the front-controller rewrite.
RUN a2enmod rewrite headers

# Serve public/ — NOT the project root, which would put .env, vendor/, and
# storage/ one URL away from the internet. Logs go to the container's streams so
# the platform captures them; LOG_CHANNEL=stderr sends Laravel's own there too.
RUN printf '%s\n' \
    'ServerName localhost' \
    'ServerTokens Prod' \
    'ServerSignature Off' \
    '<VirtualHost *:80>' \
    '    DocumentRoot /var/www/html/public' \
    '    <Directory /var/www/html/public>' \
    '        AllowOverride All' \
    '        Require all granted' \
    '    </Directory>' \
    '    ErrorLog /dev/stderr' \
    '    CustomLog /dev/stdout combined' \
    '</VirtualHost>' \
    > /etc/apache2/sites-available/000-default.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dependencies first, so a change to application code does not re-resolve them.
#
# --no-scripts throughout: composer.json's post-autoload-dump runs
# `php artisan package:discover`, which boots the framework. A Docker build has
# no access to the platform's environment variables, so booting here would run
# against an empty environment. Package discovery is deferred to the entrypoint,
# where the real environment exists.
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --prefer-dist

COPY . .

RUN composer dump-autoload --optimize --no-dev --no-interaction --no-scripts

# The repo tracks these as .gitignore stubs; make sure the full tree exists and
# is writable before Apache drops to www-data.
RUN set -eux; \
    mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache; \
    chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint"]
