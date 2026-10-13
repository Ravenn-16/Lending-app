#!/bin/sh
set -eu
cd /var/www/html
: "${APP_KEY:?Set a permanent Laravel APP_KEY in Render}"
: "${OWNER_USERNAME:?Set the owner administrator username}"
: "${RESEND_KEY:?Configure the Resend API key}"
: "${AIVEN_CA_CERT:?Paste the Aiven CA certificate into Render}"
: "${MAIL_FROM_ADDRESS:?Configure the verified sender}"
export APP_URL="${APP_URL:-${RENDER_EXTERNAL_URL:?Set APP_URL}}"
mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
: "${MYSQL_ATTR_SSL_CA:?Set the Aiven CA file path}"
printf '%s\n' "$AIVEN_CA_CERT" > "$MYSQL_ATTR_SSL_CA"
chmod 600 "$MYSQL_ATTR_SSL_CA"
chown -R www-data:www-data storage bootstrap/cache
php artisan config:cache
php artisan migrate --force
php artisan admin:bootstrap --no-interaction
php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache
exec apache2-foreground
