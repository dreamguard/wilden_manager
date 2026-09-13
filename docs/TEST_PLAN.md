# Test plan for www3

## Installation

1. Back up the staging database and files.
2. Install `wilden_manager-1.0.0.zip`.
3. Confirm the **Orders → Wilden Manager** tab appears.
4. Confirm the three `wilden_manager_*` tables were created.
5. Test with an administrator and a restricted employee profile.

## Read-only behavior

- Open the default list and paginate through orders.
- Combine ID, reference, customer, state, date, total, payment, carrier and
  note filters.
- Test ascending and descending ordering.
- Change visible columns and save a personal view.
- Mark a view as default, reload the page and delete the view.
- Open quick view for orders with and without invoices and deleted addresses.
- Download an existing native invoice.

## Notes and export

- Create, edit, clear and reload a 5,000-character internal note.
- Confirm another employee can read the note and the audit records the editor.
- Export filtered orders and open the UTF-8 CSV in Excel or LibreOffice.
- Put `=1+1` in a test note and confirm the exported cell is treated as text.

## Bulk state changes

- Select one order, preview a harmless state change and execute without email.
- Repeat with customer email enabled and verify receipt.
- Preview multiple orders and confirm the displayed current and target states.
- Change one selected order in another tab before execution; the signed preview
  must be rejected.
- Wait more than 15 minutes after a preview; execution must be rejected.
- Try zero orders, no target state and more than 100 orders.
- Verify stock and other normal PrestaShop side effects for the tested states.
- Verify a failed email is shown as a warning while the applied state remains
  recorded as successful.

## Isolation

- Confirm installation and ordinary navigation do not read or write tables or
  configuration belonging to `idxrcustomproduct`, `autostockpack`, Redsys or
  any other module.
