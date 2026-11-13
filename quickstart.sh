#!/usr/bin/env bash
# ShopKeeprr 1.1 local demo: download the release, use SQLite, seed data, serve.
# Usage: curl -fsSL https://raw.githubusercontent.com/DevByStephan/ShopKeeprr/main/quickstart.sh | bash
set -euo pipefail

VERSION="1.1.0"
ZIP_URL="${SHOPKEEPRR_ZIP_URL:-https://github.com/DevByStephan/ShopKeeprr/releases/download/v${VERSION}/shopkeeprr-${VERSION}.zip}"
TARGET="${SHOPKEEPRR_QUICKSTART_DIR:-$PWD/shopkeeprr}"
if [ -n "${SHOPKEEPRR_PORT:-}" ]; then
    PORT="$SHOPKEEPRR_PORT"
elif [ -n "${PORT:-}" ] && [ "$PORT" != "0" ]; then
    PORT="$PORT"
else
    PORT=8000
fi
MODE="${SHOPKEEPRR_MODE:-retail}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@shopkeeprr.test}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-ShopKeeprr2026!}"

for command in php curl unzip; do
    if ! command -v "$command" >/dev/null 2>&1; then
        echo "Missing required command: $command" >&2
        exit 1
    fi
done

if ! php -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);'; then
    echo "ShopKeeprr requires PHP 8.3 or newer. Found: $(php -r 'echo PHP_VERSION;')" >&2
    exit 1
fi
if ! php -m | grep -qi '^pdo_sqlite$'; then
    echo "PHP's pdo_sqlite extension is required for the local demo." >&2
    exit 1
fi

case "$TARGET" in
    /*|[A-Za-z]:*) ;;
    *) TARGET="$PWD/$TARGET" ;;
esac

if [ ! -f "$TARGET/vendor/autoload.php" ]; then
    echo "Downloading ShopKeeprr $VERSION..."
    mkdir -p "$TARGET"
    tmp_zip="${TMPDIR:-/tmp}/shopkeeprr-${VERSION}-$$.zip"
    trap 'rm -f "$tmp_zip"' EXIT
    curl --fail --location --retry 3 --output "$tmp_zip" "$ZIP_URL"
    unzip -q "$tmp_zip" -d "$TARGET"
    rm -f "$tmp_zip"
    trap - EXIT
fi

cd "$TARGET"

if [ ! -f ".env" ]; then
    sed '/^\[TEMPLATE\]$/d' .env.example > .env

    # The demo needs no MySQL, queue worker or mail server.
    tmp_env="${TMPDIR:-/tmp}/shopkeeprr-env-$$"
    grep -vE '^(APP_ENV|APP_URL|DB_CONNECTION|DB_DATABASE|SESSION_DRIVER|CACHE_STORE|QUEUE_CONNECTION|MAIL_MAILER|SHOPKEEPRR_TRACKING_ENABLED|SHOPKEEPRR_UPDATES_ENABLED)=' .env > "$tmp_env"
    {
        echo "APP_ENV=local"
        echo "APP_URL=http://127.0.0.1:${PORT}"
        echo "DB_CONNECTION=sqlite"
        db_file="$TARGET/database/database.sqlite"
        if command -v cygpath >/dev/null 2>&1; then
            db_file="$(cygpath -m "$db_file")"
        fi
        printf 'DB_DATABASE="%s"\n' "$db_file"
        echo "SESSION_DRIVER=file"
        echo "CACHE_STORE=file"
        echo "QUEUE_CONNECTION=sync"
        echo "MAIL_MAILER=log"
        echo "SHOPKEEPRR_TRACKING_ENABLED=false"
        echo "SHOPKEEPRR_UPDATES_ENABLED=false"
    } >> "$tmp_env"
    mv "$tmp_env" .env
fi

mkdir -p database bootstrap/cache storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
touch database/database.sqlite

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi
php artisan migrate --force

if [ ! -f storage/app/installed.lock ]; then
    php artisan shopkeeprr:install \
        --store-name="ShopKeeprr Demo" \
        --store-email="$ADMIN_EMAIL" \
        --admin-name="Demo Administrator" \
        --admin-email="$ADMIN_EMAIL" \
        --admin-password="$ADMIN_PASSWORD" \
        --mode="$MODE" \
        --live \
        --demo \
        --force
fi

printf '\nShopKeeprr is ready: http://127.0.0.1:%s\n' "$PORT"
printf 'Admin:    %s\n' "$ADMIN_EMAIL"
printf 'Password: %s\n\n' "$ADMIN_PASSWORD"

if [ "${SHOPKEEPRR_QUICKSTART_NO_SERVE:-0}" = "1" ]; then
    exit 0
fi

exec php artisan serve --host=127.0.0.1 --port="$PORT"
