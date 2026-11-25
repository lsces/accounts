# Accounts — user guide

A light ledger for a sole trader's self-assessment: load your bank statements, say who each payment
went to, and see what is coming up. Cash basis (money counted when it moves), no VAT, no payroll, no
invoicing. For how it is built, see `DEVELOPER.md`.

Everything needs a login. By default only an administrator can see or change anything.

## Accounts

The front page (**Accounts** in the menu) shows, from top to bottom:

- **Next 28 days** — the planned payments and receipts across your bank accounts, with a running balance.
  Anything overdue is first and shaded. The foot gives total paid out, total paid in and what is spare.
- **Balances** — each account's balance, and where it came from (last statement line, a balance you noted,
  or the opening balance). To correct it from your bank's app, type the figure in the box and **Save balances**.
- **Accounts** — every account with its kind, bank contact and number of lines.

**New account** asks for:
- *Kind* — **Bank or other account**, **Rent account (tenancy ledger)** or **PayPal**. It cannot be changed
  later, because each kind has its own types of line.
- *Name*.
- *Contact* — the bank or service holding it, picked from your contacts by typing.
- *Opening balance* — the balance before the first line you load.
- *Counts as cash* — untick for something that is not money in hand, such as a tenancy ledger, to keep it
  out of the forecast.

## Viewing an account

Three icons at the top right of an account page lead to **Planned lines** (calendar), **Import statements**
(down arrow; bank accounts only) and **Edit** (pencil). The page lists every line, newest first, with date, type, description, supplier, money out, money in
and the running balance, 20 to a page (use the page bar at the foot, or type a page number). A **tax year**
selector shows one year; the balance shown is still the true running balance. Tax years run from 5 April.

## Loading statements

Open the account, then use the **Import statements** icon (down arrow, top right).

1. Choose one or more files: business statements as **CSV**, personal statements as **PDF**.
2. **Preview** shows how many lines are new and how many are already held, and whether the printed
   balances on the statement agree with the lines. Types the package does not know are filed as
   *Other bank transaction*.
3. **Import** adds the new lines. Loading the same statement twice adds nothing, so overlapping files are safe.

Each file you import is kept (with the text read out of a PDF beside it), and listed at the foot of the
import page. After an import, lines are linked to suppliers automatically where a rule already exists
(see below), and the page tells you how many.

## Suppliers

Each line can be linked to the contact it was paid to or came from. Any contact can be chosen on any
account, because a personal account sometimes pays a business supplier and the other way round. People
count too: a transfer to a family member is a person contact. Contact tags such as *Business supplier*
and *Household supplier* are for sorting and reports; they do not limit who you can pick.

On an account's page, each line has a **Set** (or **Change**) button:

1. Start typing the contact's name and pick it from the list. **Clear** removes a link.
2. **Statement text** is filled in with a suggestion: the start of the description, before any invoice or
   phone number. Edit it to the part that stays the same every time (`Acmehost`, not `Acmehost INV123`).
3. With **Fill forward** ticked (the default), every other unlinked line containing that text is linked
   too, and the text is remembered, so future imports link the same way.
4. **Replace** also moves lines that already have a *different* supplier, including ones you set by hand.
   Use it to correct a rule that was too broad: give the instalment provider the text `PAYLATER` and tick Replace to take
   its lines off the shop they were first linked to. Lines already on that supplier are left alone.
5. **Save** returns to the same page of the statement.

How matching works: capitals do not matter; if several texts match one line the longest wins; without
Replace a line that already has a supplier is never changed, so a choice you made by hand stays.

Cash withdrawals are best given a *Cash* contact with the text that starts every cash-machine line on your statement (for example `CASH`). Anything business that is
bought with cash will not be traceable, so pay for business items by card.

Statement text is also kept on the contact itself (Statement text entries on its page).

## Planned lines (the forecast)

The **Planned lines** icon (calendar) on an account lists what is expected but has not happened. Each planned line has:
- *Due* date, *What*, direction (**Paid out** or **Paid in**) and amount;
- *Repeat* — once, weekly, every 2 or 4 weeks, monthly, every 6 months or yearly — and an optional last date;
- *Working-day rule* — leave on its date, or pay the working day before or the next working day, for payments
  that move for weekends and bank holidays (a Monday pension paid the Friday before when Monday is a bank
  holiday). England and Wales holidays are known; a one-off extra holiday is not;
- *Via* — direct from the account, or through PayPal; and a note.

**Done** marks a line paid: a repeating line moves to its next date, a one-off is put away (not deleted).
**Remove** deletes it from the forecast. Planned lines are never counted as income or expense.

## Rent accounts

A rent account is a tenancy ledger: rent charged, housing benefit and payments made, rolling on year to
year. It is kept separate from your bank accounts, whose direct debits to the landlord are the other side
of it, and by default is not counted as cash.

## Not built yet

- PayPal statement import (the PayPal account kind exists).
- Treatment of each line (income, expense, transfer, not claimed) and the year-end report for the return.
- Matching planned lines to the bank lines that fulfil them.
