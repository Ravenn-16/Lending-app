# Lending validation checklist

The following rules are enforced by this Laravel application. Browser checks help users correct entries; server checks remain authoritative when JavaScript is disabled or requests are modified. Amounts display in Philippine pesos. Input amounts use digits and an optional decimal point, without a currency symbol or commas.

## Applied validations

| Area | Rules |
| --- | --- |
| Access | Authenticated administrator required for protected pages, financial writes and APIs. Session invalidation after password changes. CSRF protection for browser writes. Login throttling. Administrator identity comes from the session, not submitted data. |
| Login | Required username and password; username maximum 100 characters, no control characters; password maximum 72 characters and no null bytes; credentials verified against the stored hash. |
| Password change | Correct current password, matching confirmation, different new password, minimum 12 characters, maximum 72 bytes, no null bytes. Passwords stored as hashes. |
| Borrower name | Required, trimmed, maximum 255 characters, at least one letter; accepts Unicode letters and marks, spaces, periods, apostrophes and hyphens. Rejects arrays, markup, digits-only names and invisible control characters. Repeated spaces normalized. |
| Address/location | Optional linked Philippine province or area, city or municipality, and barangay selections. All three are required when any is provided. Server validates codes and parent relationships against the bundled dataset, rejects submitted free-text location, and generates the display address (maximum 255 characters). Existing legacy text is preserved until replaced. |
| Phone | Optional, 10 to 15 digits; allows a leading plus, spaces, balanced parentheses and hyphens; rejects letters, repeated identical digits and malformed punctuation. Stored as digits with optional leading plus. This checks syntax, not ownership or whether the number is active. |
| Borrower deletion | Refuses deletion when loans exist; borrower row is locked to coordinate with loan creation. Names and phone numbers are not forced unique because different people can share them. |
| Loan borrower | Required positive integer referencing an existing borrower; checked again under a database lock. |
| Loan principal | Positive amount, minimum PHP 0.01; at most two decimal places; rejects negative values, scientific notation, separators, symbols, arrays and floating-point JSON values. Send JSON money as decimal strings. Maximum PHP 9,090,909,090.90 reserves room for the existing 10 percent initial interest. Accrued balance must also fit storage. This is a technical maximum, not a credit approval limit. |
| Loan date | Required exact YYYY-MM-DD calendar date, January 1, 1900 through today in the application timezone. Backdated loans must also pass accrued-balance capacity checks. The lower bound is a supported date range, not an age requirement. |
| Protected loan fields | Submitted interest rate, balance, administrator ID and status are prohibited. Existing interest rules are calculated on the server. |
| Payment borrower/loan | Required existing positive integer IDs; loan must belong to selected borrower, checked again while recording the payment. |
| Payment amount | Same exact decimal format; minimum PHP 0.01 and maximum PHP 9,999,999,999.99, additionally limited to the calculated payoff on the selected date. Rejects payments when no payable balance exists. |
| Payment date | Exact supported calendar date, not in the future and not before loan origination. Backdating cannot cause a negative closing balance on a subsequent day. |
| Payment integrity | Payment and matching ledger entry commit together. Loan and ledger locks prevent simultaneous overpayments. Quotes are recalculated under the lock before writing. Browser disables payment submission until a valid payable quote is loaded. |
| Payment reversal | Existing posted payment only; required readable reason up to 500 characters, no markup or hidden controls. Multiline explanations allowed. Rejects repeated reversals and inconsistent payment/ledger amount, date or linkage. Simulates the reversal and checks balance capacity before changing status. Reversal and status change commit together. |
| Duplicate submissions | Borrower creation, loan creation, payments and reversals require a UUID submission key. Same administrator, operation and payload with the same key returns the original result. Reusing a key with different details or another account fails. Receipt and operation commit together; failed operations can be corrected and retried. Separate forms with separate keys are separate intentional transactions. |
| Ledger calculations | Integer centavo arithmetic, supported balance capacity, original monthly anniversary with end-of-month clamping, consistent same-day ordering, preserved stored interest and reversal audit history. Reading a balance does not write new interest records. |
| Filters/API parameters | Status values restricted to supported choices, page numbers from 1 to 1,000,000, bounded search input, valid existing selected IDs, consistent borrower/loan selection and validated quote dates. Missing records return not found. |
| Forms | Required fields, input length/date/amount limits, field-level server error messages, retained safe inputs, disabled submit buttons after valid submission and stale asynchronous quote protection. Invalid decimal precision is not silently rounded by the browser formatter. |

## Transaction History page

The sidebar links to `/transactions`, showing recorded ledger entries for every borrower: loan releases, payments, recorded interest, payment reversals and adjustments. Filters accept borrower name, a supported transaction type, and an inclusive date range. Dates must be valid, within the supported date range and no later than today; the end cannot precede the start. Results are paginated in descending effective-date and entry-ID order, preserving filters. Access requires administrator authentication. Viewing or filtering history never modifies records. Voided payments remain visible with their reversal entries. Calculated monthly interest is available in each loan's balance history; this page lists recorded transactions only.

## Additional validations requiring business rules or new features

These are not silently enabled: the current database does not collect the necessary information, or no policy values have been specified.

| Feature/policy | Validations to define before implementation |
| --- | --- |
| Identity and eligibility | Date of birth and minimum/maximum age, identity document type/number/expiry, verified contact ownership, duplicate identity matching, residency and required borrower consent. |
| Credit assessment | Employment/business verification, income and expense evidence, affordability or debt-to-income thresholds, credit history, delinquency restrictions, maximum active loans and total exposure per borrower. |
| Loan products | Product-specific minimum/maximum principal, approved rates, terms, payment frequency, allowed origination windows, grace periods, penalty caps, fees, rounding and early settlement rules. |
| Approvals | Separate maker/reviewer roles, approval thresholds, disbursement authorization, actor limits and restricted backdating or reversal windows. |
| Collections | Payment method, unique external transaction/reference numbers where appropriate, receipt numbering, bank reconciliation, partial allocation order, bounced payments, refunds and credit handling. |
| Guarantors/collateral | Required guarantors, relationship restrictions, guarantor exposure, ownership documents, valuation, coverage ratio, expiry and release conditions. |
| Documents | Required document lists, allowed file extensions and MIME signatures, maximum sizes, malware scanning, access control and retention rules. |
| Imports/integrations | File schema, row-level validation, duplicate external IDs, currency agreement, reconciliation totals and atomic or explicitly partial import behavior. |
| Organizational requirements | Branch/tenant record access, audit review, record retention, approved disclosure templates and applicable compliance workflows. |

No finite checklist covers every lending product. These policy-dependent rules need explicit business requirements; the implemented checks protect the fields and workflows currently present.

## Verification

Run `php artisan test` for isolated SQLite in-memory feature and calculation tests. Run `php tests/concurrency.php` for MySQL locking and duplicate-submission checks; it creates and removes a uniquely named test database without changing live lending records. `tests/browser.py` checks the payment quote interaction against the separate browser fixture server.

## Notifications

An always-visible bell in the top-right header opens the notification panel, including an empty state when there are no alerts and a link to transaction history. Current-page messages remain in this panel after their pop-ups disappear; navigation refreshes the panel.

Transaction and edit confirmations and validation errors appear in a dismissible upper-right stack, including asynchronous address/payment loading failures. Successful sign-in and sign-out do not produce notifications. Success and informational messages close after eight seconds unless hovered or focused; errors and due-date reminders remain until dismissed or the page changes. Field-level validation remains available.

Signed-in pages show a reminder linking to the Due in 3 Days list when outstanding loans are due today through three days ahead, using the existing loan calculator status. Paid and overdue loans are excluded. Reminders refresh on page navigation; they are in-app reminders, not background or email notifications.

## Reports

The authenticated `/reports` page provides Collections, Loan releases, Interest accrued, Outstanding loans, and Due and overdue loans. It defaults to the current month and supports an inclusive date range and borrower-name search. Summary cards show principal released, payments less reversals, accrued interest during the period, and outstanding balances at the end date. Amounts are aggregated in integer centavos.

Activity reports use ledger effective dates. Collections include payment reversals as negative amounts on the reversal date, so a period may have negative net collections. Interest includes the calculator's derived charges and is not collected interest or profit. Balance and due reports replay each loan through the selected end date regardless of origination date; due reports include overdue and due-within-three-days loans, excluding fully paid loans. Historical figures reflect the current ledger, including backdated entries. Reports never write interest or change lending records.

The page paginates 20 rows; totals cover every matching record. CSV export and Print view include all matching rows, filters and totals. CSV exports use PHP amounts, UTF-8 and spreadsheet-safe borrower names. Print view provides a Print report button for the browser print/save-to-PDF dialog. Invalid dates, reversed ranges, unsupported types/formats and invalid pagination are rejected. All formats require authentication.

## Report presentation and record references

Reports use a formal Lending System header, report-type code, period/as-of date, preparer, generated timestamp, borrower scope, currency, record count, financial summary, detail rows and grand total. Print view is formatted for landscape A4; CSV includes identifying metadata and references. Report codes identify the report type, not an archived or uniquely stored report run.

Display references use at least six digits: `BR-000001` for borrowers, `LN-000001` for loans, `PAY-000001` for payments, `TXN-000001` for stored ledger transactions, and `ADM-000001` for administrators. Larger IDs are not truncated. Existing database keys, relationships, routes and form/API values remain numeric; the references are a consistent display format. Calculated interest has no stored transaction ID and is explicitly labeled Calculated. References appear in lists, details, payment selection, reports and exports.

## Plain-language guidance

The interface uses Amount lent, Still owed, Interest added, and Cancel this payment instead of accounting jargon. Payment cancellation explains that the recorded payment is a mistake, adds the amount back to the loan, may affect interest, and remains in history. Stored statuses and accounting behavior are unchanged.

An empty dashboard gives three getting-started steps. Reports include a report-choice guide, numbered selection/date fields, clearer download/print actions and plain explanations of figures. Optional record-number help explains BR, LN, PAY and TXN without requiring users to enter these references. Loan rules include a PHP 1,000 example. These changes improve clarity; ease of use should still be confirmed with actual first-time users.
