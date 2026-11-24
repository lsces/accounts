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
the row's item is the line's type. That vocabulary is a site-local scheme
(`config/local/xref_schemes/accounts.php`, applied from liberty's Local Scheme page), not part of the
package install, which carries only the `CONTACT` link. For bank accounts it is the bank's own Type
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
