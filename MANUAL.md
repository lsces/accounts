# accounts — intended shape

Skeleton stage: nothing below is built yet. This records the design the package is being built to,
so the first real commits have something to be checked against.

## Scope

Single-entry, sole-trader bookkeeping for a self-assessment return. Not double-entry, no VAT, no
payroll, no company accounts. Invoicing is out of scope for the first build.

## Content model

One **ledger** per account per tax year is the base object (the same shape as the `health`
package's day object). Every statement line is a `liberty_xref` row on its ledger, not a
`liberty_content` row of its own, which keeps a year of statements to a handful of content objects.

Each line carries: date, signed amount, bank type code, description, running balance where the
source gives one, a category, and a flag for "transfer between own accounts" (left out of income
and expenses).

An account is anything with a running balance, so a bank account, a payment-service account or a
rent account are all the same kind of thing; a ledger just rolls its balance on into the next year.

A main business account holds most eligible lines. A personal account contributes only the lines
that are picked and flagged as business; the rest stay out of the totals.

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

## Categories and rules

- A configurable category list, grouped to line up with the expense boxes of the return.
- Rules of the form "description contains X → category". Imports apply them automatically.
- Unmatched lines go to a "needs a category" queue; categorising one line can offer to make a rule
  and apply it to the rest.

## Reports

A tax year's income and expenses by category, printable. It also lists every uncategorised line,
so nothing is dropped silently.

## Checking it

Import a year already done by hand and compare the totals against that year's own working before
relying on the package for the current year.
