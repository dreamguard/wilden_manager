# Test plan for www3

## Installation

1. Back up the staging database and files.
2. Install the current `wilden_manager` ZIP.
3. Confirm the native **Orders** page shows the module action buttons.
4. Confirm the `wilden_manager_*` tables were created.
5. Test with an administrator and a restricted employee profile.

## Read-only behavior

- Open the default list and paginate through orders.
- Combine the native ID, reference, customer, state, date, total and the added
  customer-email and shipping-method filters.
- Test ascending and descending ordering.
- Change visible columns, save, reload and verify the employee/shop preference.
- Confirm **Internal note** is no longer offered or displayed.
- Download an existing native invoice.

## Export

- Export filtered orders and open the UTF-8 CSV in Excel or LibreOffice.

## Bulk state changes

- Select one order in the native grid, open **Safe status change**, preview a
  harmless state change and execute without email.
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
