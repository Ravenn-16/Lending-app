#!/bin/sh
set -eu
cd /var/www/html
: "${APP_KEY:?Set a permanent Laravel APP_KEY in Render}"
: "${OWNER_USERNAME:?Set the owner administrator username}"
: "${MAIL_HOST:?Configure the SMTP server}"
: "${MAIL_USERNAME:?Configure SMTP credentials}"
: "${MAIL_PASSWORD:?Configure SMTP credentials}"
: "${MAIL_FROM_ADDRESS:?Configure the verified sender}"
export APP_URL="${APP_URL:-${RENDER_EXTERNAL_URL:?Set APP_URL}}"
mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
php artisan config:cache
php artisan migrate --force
php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache
exec apache2-foreground
