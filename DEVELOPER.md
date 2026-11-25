# accounts — developer reference

How the package is built. The user guide is `MANUAL.md`. No tables of its own: everything is
`liberty_content` plus `liberty_xref` rows.

## Scope

Single-entry, sole-trader bookkeeping for a self-assessment return, cash basis. Not double-entry, no
VAT, no payroll, no company accounts, no invoicing.

## Content model

An account is its own content type, a pure `liberty_content` record whose title is the account's name
(same shape as `health`'s day object). Three kinds, chosen at creation and not changeable:

| Class | guid | Holds |
|---|---|---|
| `AccountsAccount` | `accountsaccount` | bank account: the bank Type items, statement import |
| `AccountsRent` | `accountsrent` | tenancy ledger (RENT, HB, PAY); not cash in hand |
| `AccountsPaypal` | `accountspaypal` | PP_SALE, PP_PAYOUT, PP_OTHER; bank is master. Type exists, no importer yet |

`AccountsAccount::loadFor( $contentId )` returns the right subclass. Content types register themselves
when a class is first constructed; the installer's `registerContentObjects` is not consumed anywhere.
The Contact record for the bank or service is linked by a `CONTACT` xref row (xref = that contact's
content_id, one per account) and supplies sort code, number and addresses. Whether an account counts as
cash (front-page forecast) is the content preference `accounts_cash` (`n` = not cash; default `n` for rent).

### Vocabulary (`admin/schema_inc.php`)

The whole vocabulary is in the package install, so a clean install is complete. A site's own extras go
in a local scheme (`config/local/xref_schemes/`, applied from liberty's Local Scheme page).

- Groups: `general` (all three kinds), `transactions` (all three), `planned` (bank, rent).
- `CONTACT` (all three); `OPENING`, `PLAN`, `BALANCE` (bank, rent).
- Statement-line items, `multiple=1`, 16 types: bank `DD BP CR VIS CTL DR TFR ATM OBP BANK_OTHER` (the
  bank's own Type column; `CTL` is the bank's `)))`); rent `RENT HB PAY`; PayPal `PP_SALE PP_PAYOUT PP_OTHER`.
- `STMT` on `contactbusiness` (group `links`) and `contactperson` (group `contact`): a statement-text rule.
- The file begins with `unset( $gBitInstaller->mPackages[ACCOUNTS_PKG_NAME]['defaults'] )`. The installer's
  cached `$gBitSystem` carries each package's seed list back on every page view, and
  `registerSchemaDefault()` appends again, so seeds would run two or three times and die on the first unique
  key. The clean fix would be in `BitSystem::registerSchemaDefault()` (shared kernel, not done).

### Statement line row

Item = the line's type. The direction of money is the sign of the amount, never the type.

| Column | Holds |
|---|---|
| `content_id` | the account |
| `item` | type code (DD, BP, ... RENT, PP_SALE) |
| `start_date` | transaction date (epoch); the tax year is derived from it |
| `xkey` | signed amount, negative = paid out |
| `xkey_ext` | import key (below) |
| `xref` | supplier: a contact's content_id; null/0 = unset |
| `data` (json) | `description`, `source` (file), `balance` (printed running balance), `code`; also `treatment`, `claim`, `note`; PayPal adds `gross`, `fee`, `net`, `status` |

Import key: `'h'.substr( sha1( "YYYY-MM-DD|amount|type|description|nth" ), 0, 24 )`, where `type` is the raw
bank type (`)))` not `CTL`) and `nth` counts identical lines within the parsed file. A repeat import
therefore adds nothing. Descriptions are stored with HTML entities decoded
(`html_entity_decode( ..., ENT_QUOTES | ENT_HTML5 )`).

`OPENING` (xkey = balance before the first line), `BALANCE` (a balance noted by hand, `start_date` = as at),
`PLAN` (see Forecast). Rows are end-dated rather than deleted.

Writing: `LibertyXref::store( $hash )` takes the hash **by reference** (a variable, never a literal). A
partial update passes `xref_id` and only the fields to change. `isset( $hash['xref'] )` stores the xref
(0 clears). `verifyItemDefined` refuses undefined items on new rows.

## Classes

- `AccountsAccount` — account record; `getStatementLines()` (lines with running balance, tax year, supplier
  id/title via a LEFT JOIN on liberty_content), `getLine()`, `setLineSupplier()`, opening/balance/planned
  helpers, `isCash()`, `getCurrentBalance()` (newer of last line and latest noted balance).
- `AccountsImport` — CSV import and de-duplication; `store()` ends with `AccountsSupplier::fill()` for the
  account and returns the number linked. Files archived under `storage/accounts/archive/<account id>/`.
- `AccountsPdfStatement` — personal statement PDF to the same CSV the business download gives, via
  `pdftotext -bbox` (words with positions). Type, description rows and amount are placed by column; the
  amount is paid out, paid in or balance by which right-hand column it ends in. Checks opening + lines =
  closing.
- `AccountsPlan` — repeat rules, bank-holiday maths, `occurrences()`.
- `AccountsSupplier` — statement-text rules (below).

## Suppliers

`STMT` rows: xkey = statement wording, xkey_ext = note. `AccountsSupplier`:

- `suggest( $description )` — words up to the first one containing a digit or `*`, trailing ` -,:;/` stripped;
  the whole description if it starts with a number.
- `rules()` — every live STMT row on a business or person contact, longest text first.
- `addRule( $contactId, $text )` — once per contact and text, case-insensitive.
- `fill( ?$accountId, ?$rules, $replace )` — links lines whose html-decoded description contains a rule's text,
  case-insensitively, longest text wins. Only lines with no supplier unless `$replace`; a line already on the
  rule's contact is skipped. Runs after every import and from `edit_line.php`.

`edit_line.php` (permission `p_accounts_update`): the line read-only, a typeahead over contacts
(`BitComponentTypeahead` to `contact/includes/lookup_contact.php`, both business and person), the match text
prefilled from `suggest()`, and checkboxes Fill forward (default on) and Replace. Save sets the supplier;
with text and Fill forward or Replace it adds the rule and fills (Replace: `fill( null, [[ text, contactId ]], true )`)
and redirects to `view_account.php?...&filled=N`.

Gotcha: `KernelTools::detoxify( $_GET, true, false )` in `setup_inc.php` html-escapes every `$_GET` value,
so `&` reaches a lookup as `&amp;`. `lookup_contact.php` decodes it with `htmlspecialchars_decode( ..., ENT_NOQUOTES )`.
Other typeahead lookups (food, stock) likely have the same fault.

## Forecast

`PLAN`: `start_date` = next due date, `xkey` = signed amount, `xkey_ext` = what it is, JSON = `repeat`
(`weekly 2weekly 4weekly monthly 6monthly annually`), `until` (Y-m-d), `via` (paypal), `adjust` (`prev`/`next`),
`note`. Marking a repeating line done moves it to its next date; a one-off is archived. Never counted in
reports. `adjust` shifts the day shown to the working day before/after; the repeat keeps counting from the
nominal date. England and Wales bank holidays are computed (Easter, May and August Mondays, substitutes); a
one-off extra holiday is not known.

Front page (`index.php`, `list_accounts.tpl`): next 28 days across cash accounts, one row per occurrence,
overdue first, balance carried from `getCurrentBalance()`. Balances noted by hand are `BALANCE` rows.

## Pages

| Page | Permission | Does |
|---|---|---|
| `index.php` | view | accounts, forecast, balances, note a balance |
| `view_account.php` | view | statement lines, 20 per page via the standard `{pagination}` (`BitBase::postGetList` builds `listInfo`; `page` or `list_page`), tax-year filter `ty` |
| `edit_account.php` | create | create an account (kind, name, contact, opening, cash flag) |
| `import_statement.php` | create | upload, preview, import |
| `plan.php` | update | planned lines |
| `edit_line.php` | update | set a line's supplier |

Permissions `p_accounts_view/create/update/expunge/admin`, granted to `admin` only. Requires kernel 5.0.0,
liberty 5.0.2, users 5.0.0, contact 5.0.1.

## Tax year and treatment

The year runs from 5 April and is named for the year it ends in (2026 Accounts = 2025-26). A line's
`treatment` is `income`, `expense`, `transfer` or `not_claimed`, intended per counterparty and carried to its
other lines. A club's reimbursements and the purchases behind them are reported gross on a cash basis; a `claim`
reference in the JSON ties them together for tracing only. **Not built yet:** the treatment list and the
year-end report (headings only, about ten).

## Local site pieces (webstack, not in this repo)

Local scheme `accounts.php` (STMT catch-up), contact type tags B05 Business supplier and B06 Household
supplier, and the Local Imports loaders `accounts_plan_from_sheet.php` and `accounts_rent_from_sheet.php`
(read the `.ods` with ZipArchive) live in the site's `config/local/`.
