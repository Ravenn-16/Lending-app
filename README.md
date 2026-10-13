# Lending System ? Laravel

Laravel 12 application with Blade views, Eloquent models, MySQL, and Bootstrap.
The blue, white, and gray design is shared by login and authenticated pages.

## Run locally

Dependencies and the application key are configured in this workspace. Start MySQL,
then run:

```powershell
php artisan serve
```

Open http://127.0.0.1:8000 and sign in with the existing administrator account.
Database settings are in `.env`; the default database is `lending_system`.
No Node build is required. Bootstrap and Font Awesome currently use CDN assets.

For a fresh checkout:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate
php artisan admin:create admin
php artisan serve
```

Create the MySQL database and configure `.env` before running migrations.
`admin:create` prompts for a password; it never overwrites an existing account.

## XAMPP / Apache

For online production hosting, see [Render deployment](docs/RENDER.md).

Point the virtual host's document root to this project's `public` directory and
allow its `.htaccess` rules. If the project is placed under `htdocs/lending-system`,
the root `.htaccess` forwards requests to `/lending-system/public/` and denies
access to internal directories. Apache mod_rewrite must be enabled.
Do not serve application internals, `.env`, storage, or the old PHP backup publicly.
For production, use HTTPS, a dedicated database account, `APP_DEBUG=false`, and
set `APP_URL` to the actual application URL.

## Structure

- `routes/web.php`: named routes, authentication middleware, legacy bookmark redirects.
- `app/Http/Controllers`: authentication, dashboard, borrowers, loans, payments, JSON APIs.
- `app/Models`: Admin, Borrower, Loan, Payment, LedgerEntry.
- `app/Services/LoanCalculator.php`: exact-centavo ledger replay and monthly interest.
- `app/Services/LoanService.php`: transactional financial writes and row locking.
- `resources/views`: Blade templates with Laravel CSRF protection and validation errors.
- `public/assets`: shared CSS and JavaScript.
- `database/migrations`: creates missing tables or adopts existing financial tables.
- `tests`: isolated regression, browser, and MySQL concurrency checks.

## Migration and data

The pre-Laravel application is preserved locally in `.tmp/legacy-php` (not committed).
The conversion retained the original table names and admin password hashes.
The adoption migration does not clear, replace, or reseed existing records.
It deliberately refuses destructive rollback. Do not run `migrate:fresh` or import
the old reinstall SQL against a database containing records you want to keep.

The administrator explicitly requested clearing borrowers, loans, payments, and
ledger history after conversion; those records were removed from the local
`lending_system` database while keeping administrator accounts. This is a one-time
local operation, not a migration or automatic behavior on startup.

## Loan behavior

- 10% initial interest; 10% monthly interest on the remaining balance.
- Monthly dates retain the original day, clamped to shorter months.
- Same-day payments apply before monthly interest; full payment stops accrual.
- Monthly interest is calculated from the dated ledger, including missed months.
  Stored interest is authoritative for its date and is not counted twice.
- The full balance is due one month after borrowing. Partial payments do not
  postpone the due date. Overdue loans keep their original due date.
- Payment quotes use the chosen payment date. Future payments, payments before
  origination, excess payments, and backdated payments conflicting with later
  balances are rejected.
- Voids restore amounts on the reversal date, require a reason, and record the
  administrator ID. Concurrent writes lock the parent loan to avoid duplicate
  reversals or overpayments. Historical credits remain visible.

Authentication uses Laravel sessions and the existing `admins` table. Login is
limited to 20 attempts per client address per 15 minutes. Password changes require
the current password. Logout and all writes use POST/PUT/DELETE with CSRF tokens.

## Checks

```powershell
php artisan test
php tests/concurrency.php
```

PHPUnit forces SQLite `:memory:` and never clears the live MySQL database.
The concurrency check creates a uniquely named `lending_test_*` MySQL database
and removes only that test database afterward; it requires create/drop permissions.

Browser testing uses a separate SQLite file:

```powershell
php tests/browser_fixture.php
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = Join-Path (Get-Location) 'storage\framework\testing\browser.sqlite'
$env:LENDING_BROWSER_TEST = '1'
php -S 127.0.0.1:8765 -t public tests/browser_router.php
```

Run `python tests/browser.py` in another terminal (requires installed Chrome on
Windows). Stop the test server afterward and open a fresh terminal before starting
the real application, so test database environment overrides are not reused.
The fixture script refuses to overwrite an existing test file.

## Validation rules

See [the lending validation checklist](docs/VALIDATION.md) for enforced rules, supported limits, and policy-dependent extensions.
