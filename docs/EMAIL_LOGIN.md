# Email login verification

The owner signs in with their existing username and password, then enters a six-digit code sent to their registered inbox. This is a single-owner installation: only `OWNER_USERNAME` may start an email challenge while this feature is enabled. When no owner email has been configured or verified, the password-verified owner can enter and confirm her email. The address is saved only after code verification. Once saved, browser registration cannot replace it.

## Enable on the host

For Render Free with Aiven, follow [RENDER.md](RENDER.md). That deployment uses
`MAIL_MAILER=resend`, `RESEND_KEY`, and a verified `MAIL_FROM_ADDRESS` over HTTPS;
SMTP ports are blocked on Render Free. Sessions, cache, and atomic locks use the
Aiven database. The SMTP instructions below apply to hosts that permit SMTP.

Keep SMTP credentials in the host's private environment settings or `.env`, never GitHub. Set:

```dotenv
LOGIN_CODE_ENABLED=true
OWNER_USERNAME=the_existing_owner_username
# Leave blank to let the owner register her email on first sign-in.
OWNER_EMAIL=
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=your_provider_smtp_host
MAIL_PORT=465
MAIL_USERNAME=your_provider_smtp_username
MAIL_PASSWORD=your_provider_smtp_password
MAIL_FROM_ADDRESS=your_verified_sender_address
MAIL_FROM_NAME="Lending System"
```

Use the host, port and encryption settings supplied by your mail provider. The example uses implicit TLS on port 465. For STARTTLS on port 587, use `MAIL_SCHEME=smtp` with the provider's TLS-enabled service. Configure the sender's domain verification and SPF/DKIM as required by that provider. Email is sent synchronously, so no queue worker is required.

Use a persistent server-side session driver and a persistent cache with atomic locks (the existing file drivers work on one server). Do not use cookie sessions or an in-memory array cache for production. Multiple application servers need shared sessions/cache. Serve the deployed app over HTTPS with secure session cookies.

Deploy the new email-column migration with `php artisan migrate --force`. After changing settings, run `php artisan config:cache`. Verify delivery using a controlled login to the owner's actual inbox, then verify expiry, resend and logout before handover. Existing sessions without email verification will be signed out on their next protected request. No live email delivery was tested during implementation.

## Defaults and recovery

If `LOGIN_CODE_ENABLED` is omitted, verification defaults to enabled in production and disabled in local/testing environments. When enabled, missing owner username, invalid configured email, or failed email delivery prevents login; there is no password-only fallback. Log, array, and failover mailers are rejected to prevent codes being written to logs. Development can use `Mail::fake()` in tests.

Keep the hosting account protected with its own multi-factor authentication. If the owner loses inbox access, the maintainer must verify the owner's identity outside the app, update `OWNER_EMAIL` in the host configuration, and rebuild the configuration cache. Do not introduce a public recovery bypass. A nonempty OWNER_EMAIL overrides the saved address. The next login uses the new inbox; pending challenges for the old inbox fail verification. Leave the override in place until the saved address is updated through an administrator-controlled recovery procedure.

## Controls

- Password verification occurs before sending; the user remains a guest until the code is verified.
- Cryptographically generated codes are stored as hashes, expire in ten minutes, and are consumed on use.
- Five wrong codes end a challenge. Verification routes also have IP request limits.
- Sending is limited per account to once per minute and ten times per hour, including resends and new logins.
- Resending invalidates the previous challenge. Code checks use atomic locks to prevent simultaneous reuse.
- A password change, owner configuration change, expiration, or cancellation invalidates the challenge.
- No sign-in/sign-out success notifications are added. Delivery and code errors appear on the login screens.
- Codes are excluded from validation old-input flashing. Mail failure exception contents are not logged because they may contain message content or credentials.

Email verification depends on the security and availability of the owner's inbox; it is not phishing-resistant.

Reference: https://laravel.com/docs/12.x/mail

## First-time email setup

Provide the owner with her assigned username and a strong, privately delivered initial password. She enters that password, supplies her email twice, and enters the emailed code. Until verification she cannot access app data, and no email is saved. Setup authorization expires after ten minutes or a password change. Returning to sign-in allows correction of an incorrectly typed email. Once saved, the verified address appears in Account Settings. There is no public account registration. SMTP must still be configured by the maintainer; registering a recipient does not configure email delivery.
