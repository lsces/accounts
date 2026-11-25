# accounts — intended shape

Skeleton stage: nothing below is built yet. This records the design the package is being built to,
so the first real commits have something to be checked against.

## Scope

Single-entry, sole-trader bookkeeping for a self-assessment return. Not double-entry, no VAT, no
payroll, no company accounts. Invoicing is out of scope for the first build.

## Content model

**An account is its own content type** (`AccountsAccount`, a pure `liberty_content` record whose
title is the account's name — the same shape as `health`'s day object). The **Contact** record for
the bank or service holding the account is linked to it with a `CONTACT` xref row and supplies the
extra detail (sort code, number, addresses); none of that is kept on the account. `edit_account.php`
creates an account and sets the link.

Every statement line is a `liberty_xref` row on the account (`multiple=1`, many rows, editable), and
the row's item is the line's type. That vocabulary is part of the package
install (`admin/schema_inc.php`), so a clean install is complete with no further steps; a site's own
extra types go in a local scheme (`config/local/xref_schemes/`, applied from liberty's Local Scheme page). For bank accounts it is the bank's own Type
column, shared by business and personal accounts: `DD` direct debit, `BP` bill payment, `CR` credit,
`VIS` Visa debit, `CTL` contactless, `DR` debit/charges, `TFR` transfer, `ATM` cash, `BANK_OTHER` for
anything new. PayPal is its own account type (`AccountsPaypal`, guid `accountspaypal`, same record shape) so it carries its own tabs and tools and a bank account never shows them; the kind is chosen when the account is created. The bank is the master record: PayPal holds only the few sales (`PP_SALE`) and where that
money went (`PP_PAYOUT`), not the purchases it funded from the bank. The direction of money is the sign
of the amount, never the type. The generic xref tab lists by item, so statement views give date order
and a running balance. No tables of the package's own.

| Column | Holds |
|---|---|
| `content_id` | the account |
| `start_date` | the transaction date; the tax year is derived from it |
| `xkey` | signed amount, negative = paid out |
| `xkey_ext` | import key: the payment service's transaction id, or a hash of date, amount, type and description |
| `xref` | the counterparty, as a contact's content_id; null until matched |
| `data` (json) | original type text, description, running balance, treatment, claim reference, source file, note; for a payment service also gross, fee, net and status |

An account is anything with a running balance, so a bank account, a payment-service account and a
rent account are the same kind of thing; the balance simply rolls on across years.

A main business account holds most eligible lines. A personal account contributes only lines that
are entered because they are business items.

**Treatment** says how a line counts: `income`, `expense`, `transfer` (between own accounts) or
`not_claimed`. It is set per counterparty and carried onto that counterparty's other lines; a
counterparty seen for the first time goes to a "needs a treatment" list. Reimbursements and the
purchases behind them are reported gross (receipts as income, costs as expenses), on a cash basis.

## Pages

- `index.php` — the accounts, with contact and transaction count.
- `view_account.php` — one account: the contact link and the transactions, as liberty xref tabs.
- `edit_account.php` — create or rename an account and pick its contact.

Permissions `p_accounts_view/create/update/expunge/admin` are granted to `admin` only by default.

## Tax year

The year runs from 5 April, matching the tax year, and is named for the year it ends in.

## Import

- One importer per source layout (bank CSV; payment-service CSV, which has a transaction id).
- Re-importing a statement must never duplicate a line. Where the source has no id, match on
  date, amount, type and description.
- A dry-run preview reports "N new, M already there" before anything is written.
- A source that only comes as PDF is entered or keyed in, not parsed automatically.

## Source material

Every statement file (CSV and PDF) is kept, filed in a per-year folder in a fisheye gallery, so the
ledger lines can always be traced back to the document they came from.

## Reports

A tax year's income and expenses, by counterparty and total, printable. It also lists every line
still without a treatment, so nothing is dropped silently.

## Checking it

Import a year already done by hand and compare the totals against that year's own working before
relying on the package for the current year.

## Importing statements

`import_statement.php` takes the business account's CSV downloads and the personal account's statement PDFs, previews
them (new lines, lines already held, whether the printed balances reconcile) and imports on confirmation. A PDF has
no table, only words at positions and one to three description rows per transaction, so `AccountsPdfStatement` reads
it into the same CSV the business download gives: the type code, description rows and amount are placed by their
columns, and the amount is paid out, paid in or balance by which right-hand column it ends in. It also checks that the
statement's opening balance plus its lines gives its closing balance. Each imported file is kept under
`storage/accounts/archive/<account id>/` (a PDF with the text read from it beside it). A repeat import adds nothing.

## Planned lines (the forecast)

What is expected but has not happened yet is a `PLAN` row on the account it will hit: `start_date` is the next due date,
`xkey` the signed amount, `xkey_ext` what it is, and the JSON holds the repeat rule (`weekly`, `2weekly`, `4weekly`,
`monthly`), an optional last date, `via` (PayPal) and a note. `plan.php` adds, changes, marks done and removes them.
Marking a repeating line done moves it to its next date; a one-off is archived. The rows are kept (end-dated), not
deleted. Planned lines are never counted as income or expense. The front page lists the next 28 days across the bank
accounts, a repeating line once per occurrence, with the combined balance carried on from the statements, and shows
anything overdue first.

Between statements the only live figure is the bank's own app, so a balance can be noted by hand from the front page
(`BALANCE` rows: the amount as at a date). The forecast starts from the newer of the last statement line and the latest
noted balance, per account, and the front page shows the total paid out, total paid in and what is spare for the window.

A planned line can carry a weekend / bank-holiday rule: `prev` pays it on the working day before (a pension that is
always a Monday moves to the Friday, or earlier, when the Monday is a bank holiday), `next` on the working day after
(a monthly payment that shuffles to Monday). The repeat keeps counting from the nominal date; only the day shown moves.
England and Wales bank holidays are worked out from the rules (Easter, the Mondays in May and August, and the
substitute days), so a one-off extra holiday is not known.

## Account kinds

An account is one of three content types, chosen when it is created and not changeable: a bank account
(`accountsaccount`: the bank's Type items, statement import), a PayPal account (`accountspaypal`: its own sales and
payout items) and a rent account (`accountsrent`: the tenancy ledger items RENT, HB and PAY, not cash in hand). Each kind
has only its own items, so none is offered another's. The rent account's payments are the other side of a bank
account's direct debits to the landlord, and the two are kept as separate ledgers that run in parallel.

### Suppliers

A statement line's `xref` column holds the business contact it was paid to or received from. On the statement
view each line has a Set / Change button (needs update permission) opening `edit_line.php`: a search over business
contacts, with Clear to remove it. Any business can be picked on any account, since personal accounts pay business
suppliers and the other way round; the contact's own types (Business supplier, Household supplier, Bank) are for
filtering and reports, not for restricting the picker. Only the `xref` column is written.

Tick **Replace** beside Fill forward to also move lines that already have a different supplier (including ones set by hand) when their description contains the text: for example, to take a pay-later provider instalments off a shop, give the instalment provider's contact the text `PAYLATER` and tick Replace. Lines already on that supplier are left alone.
