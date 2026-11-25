# accounts

A Bitweaver package: a lightweight single-entry ledger for a sole trader. It imports bank statements
(CSV and PDF), links each line to the contact it was paid to by statement-text rules, keeps a forecast
of planned payments, and is intended to report a tax year's income and expenses for a self-assessment
return. A small replacement for a full accounting package where double-entry, VAT and payroll are not needed.

**Status:** in use on a live site: three account kinds, statement import, supplier links, forecast. The
year-end report and PayPal import are not built. `MANUAL.md` is the user guide, `DEVELOPER.md` the programmer
reference.
