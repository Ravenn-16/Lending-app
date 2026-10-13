# Production deployment on Render

This repository includes a deployment definition; it has not yet been deployed.
`render.yaml` creates two paid services in Singapore: a 512 MB PHP/Apache web
service with a 1 GB storage disk, and a private 2 GB MySQL 8.4 service with a
10 GB database disk. Review Render's displayed total before creating resources.
The database is self-hosted: database maintenance and backups remain our responsibility.

## Account and source

Create a Render account, enable account MFA, and connect the Render plugin if
deploying with the assistant. Put the application in a private GitHub or GitLab
repository and grant Render access. This workspace is not currently a Git repository.
Never upload `.env`, `.tmp`, database exports, logs, or local session files.
The Docker build explicitly excludes these files and installs production dependencies.

Create a Blueprint from the repository's `render.yaml`. No resources are created
merely by adding this file. Automatic web deployments are disabled for controlled releases.

## Private environment settings

Render prompts for APP_KEY, OWNER_USERNAME, MAIL_HOST, MAIL_USERNAME,
MAIL_PASSWORD and MAIL_FROM_ADDRESS. Generate a new permanent production key
with `php artisan key:generate --show` and save it directly in Render; do not
replace the local key or commit the generated value. Preserve the production key
in a password manager for recovery. Never regenerate it on container startup.

For an existing installation migration, securely transfer its existing APP_KEY
when encrypted application data must remain readable.

Use a verified SMTP provider. The default is implicit TLS on port 465; for a
provider requiring STARTTLS, set MAIL_SCHEME=smtp and MAIL_PORT=587. Keep email
verification enabled. OWNER_USERNAME must exactly match the administrator created
or transferred to production. OWNER_EMAIL may be omitted so the owner can verify
her inbox on first sign-in. See EMAIL_LOGIN.md for the existing verification flow.

APP_URL defaults to Render's HTTPS URL at startup. For a custom domain, configure
it in Render and explicitly set APP_URL to that HTTPS URL. TRUST_RENDER_PROXY is
enabled only in this deployment: do not enable it on an internet-facing server
that can bypass the trusted ingress.

## First deployment and data

Wait for MySQL to finish initializing, then deploy the web service. Startup builds
the configuration cache and runs non-destructive migrations; a migration failure
stops startup. Retry the web deployment if MySQL was still initializing.

The Blueprint creates an empty production database. Local records and administrator
accounts are not automatically copied. Before cutover, decide whether to transfer
the existing database using a verified SQL backup or start empty. For a fresh
database, open the web service's Render Shell and run:

```sh
php artisan admin:create YOUR_OWNER_USERNAME
```

The command prompts privately for the password. For an existing database transfer,
freeze local writes, take and verify a backup, import into the private production
database, run migrations, and compare record counts and balances before resuming
writes. Never run migrate:fresh, db:wipe, or seed production with test accounts.

## Before using real records

- Verify `/up`, HTTPS redirects/forms, and secure session cookies on the public URL.
- Complete a real email-code login, resend, expiry, and logout check.
- Verify borrower, payment, report and CSV workflows using controlled records.
- Redeploy and confirm records, login sessions and rate limits persist.
- Configure automated daily logical MySQL backups to separate private storage,
  choose retention, and test restoring to an isolated database. This Blueprint
  does not provision backup automation. Render disk snapshots alone are not a
  database backup strategy.

The app uses one web instance with persistent file sessions and cache locks.
Keep one instance unless migrating sessions/cache to a shared store. Disk-backed
deployments can have brief downtime. Schedule maintenance and take a logical
database backup before releases or MySQL upgrades.

## Verification status

Local Laravel tests can be run with `php artisan test` (isolated SQLite).
Docker is not installed in the current workspace environment, so an actual image
build, Render deployment, SMTP delivery, backup restore and live smoke tests must
be completed before production handover.

References: https://render.com/docs/blueprint-spec,
https://render.com/docs/deploy-mysql, https://render.com/docs/disks.
