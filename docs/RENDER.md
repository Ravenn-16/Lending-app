# Render Free + Aiven MySQL

The current `render.yaml` creates ONE free Render web service. It does not create
paid compute, disks, or a database. Aiven is configured separately. Application
records, encrypted sessions, cache and login-code locks live in MySQL so Render
restarts do not erase them. The app keeps using MySQL; no PostgreSQL conversion.

## 1. Aiven

Create an Aiven for MySQL service on the Free plan (not a paid trial). The free
plan currently includes 1 GB storage and backups, without a time limit. Only one
free MySQL service is allowed per organization. If another project uses it,
create a SEPARATE database named `lending_system` in that service; do not point
this app at the other project's existing database. Storage and connection limits
will be shared. Prefer a separate database user restricted to lending_system.
Do not alter or replace another project's records or credentials.

Copy the host, service port, database name, username and password from Aiven to
Render's private environment fields. Download the service's CA certificate.
Use the actual Aiven port (it may not be 3306). Set `AIVEN_CA_CERT` to the full
PEM certificate, including BEGIN/END lines and actual line breaks. Startup writes
it to MYSQL_ATTR_SSL_CA. PDO verifies the server certificate; do not disable TLS
verification. If restricting allowed IPs in Aiven, allow the Render service's
listed outbound IP ranges.

## 2. Email API

Render Free blocks SMTP. This configuration uses Laravel's built-in Resend
transport and the official resend/resend-php dependency, sending over HTTPS.
Create a Resend API key and store it as RESEND_KEY in Render. Set MAIL_FROM_ADDRESS
to an authorized sender from a verified domain. Free email quotas still apply.
An existing verified domain can be reused with the owner's authorization.

Resend's onboarding@resend.dev sender is for testing to the Resend account owner's
own inbox only. Do not assume it will deliver verification codes to a different
owner. Verify a production sender before handover; do not disable login-code
verification to work around delivery failures. See EMAIL_LOGIN.md.

## 3. Render Blueprint

Connect Ravenn-16/Lending-app, branch main, Blueprint path render.yaml. Refresh
any earlier setup screen so it loads the new Free configuration. The preview
must show just lending-app on the Free plan, with no disks or lending-mysql.
If any earlier paid services were already created, this file does not guarantee
that they are deleted or stop billing; inspect them separately before removal.

Fill the prompted environment values privately:

| Setting | Value |
| --- | --- |
| APP_KEY | Permanent Laravel application key |
| DB_HOST / DB_PORT | Aiven host and service port |
| DB_DATABASE | Separate lending database, e.g. lending_system |
| DB_USERNAME / DB_PASSWORD | Aiven database credentials |
| AIVEN_CA_CERT | Full PEM certificate downloaded from Aiven |
| OWNER_USERNAME | Intended owner username |
| OWNER_PASSWORD_HASH | Hash generated locally for first deployment |
| RESEND_KEY | Resend API key |
| MAIL_FROM_ADDRESS | Verified sender address |

Generate a fresh production APP_KEY locally with `php artisan key:generate --show`.
Save it in Render and a password manager; do not change the local .env key, publish
it, or regenerate it on every deploy. Existing encrypted data may require securely
transferring its original key instead.

Render Free has no interactive shell. For a new empty database, run
`php artisan admin:password-hash` locally. It prompts for a 12-72 byte password
without echoing it, then prints a bcrypt hash. Paste the entire hash into Render's
OWNER_PASSWORD_HASH field, without extra quotes. Do not paste it into chat or Git.
Startup creates the owner only when no administrators exist. Existing credentials
are never reset. Remove OWNER_PASSWORD_HASH after the first successful login.
If importing existing accounts, OWNER_USERNAME must match the intended account.

OWNER_EMAIL can remain unset so the owner registers and verifies her inbox on
first sign-in. APP_URL defaults to the provided HTTPS onrender.com URL. For a
custom domain set APP_URL explicitly. TRUST_RENDER_PROXY is only for deployments
behind Render's ingress.

## Startup and data transfer

Startup validates configuration, writes the CA certificate, caches configuration,
runs migrations, initializes the owner if needed, and starts Apache. Migration or
owner-initialization failure stops startup. Automatic web deployments are off;
use a manual deployment after reviewing a release.

Local lending data is not uploaded automatically. Before transferring existing
records, freeze local writes, back up, import to the separate lending database,
run migrations, and compare counts and balances. Never use migrate:fresh or
seed test accounts in the production database. Keep exports outside Git.

## Free-plan limits and handover checks

Render sleeps after 15 minutes without traffic and may take about a minute to
wake. Its 750 free instance hours are shared by the workspace: another project
can consume the same allowance. Build, bandwidth and external traffic limits
also apply. Do not use keep-alive pings to try to work around free-plan limits.
Aiven can power off inactive services and its free plan has no uptime SLA.

Before real use, verify HTTPS, actual inbox delivery, code expiry/resend/logout,
reports and payments, and persistence after redeployment. Check database storage
and account quotas. Test restoring a backup into an isolated database. Aiven's
free backup is limited; retain independent logical exports in private storage.
No local filesystem uploads can be retained on Render Free.

Local automated tests cover account initialization, database-backed challenges,
cache locks, session persistence and transport construction. They do not verify
live Aiven connectivity, real email delivery, or Render's Docker build. Docker is
not installed in the current workspace; those live checks remain required.

References:
- https://render.com/docs/free
- https://aiven.io/docs/products/mysql/concepts/mysql-free-tier
- https://aiven.io/docs/products/mysql/howto/connect-with-php
- https://laravel.com/docs/12.x/mail
- https://resend.com/docs/knowledge-base/403-error-resend-dev-domain
