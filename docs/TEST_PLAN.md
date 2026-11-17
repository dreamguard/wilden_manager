# Test plan for www3

## Installation

1. Back up the staging database and files.
2. Uninstall the old `wilden_manager` module if present, delete its folder and
   install `dist/wildenmanager-<version>.zip` (or upload `build/wildenmanager/`).
   Confirm the module shows its logo and the interface is in Spanish.
3. Confirm the native **Orders** page shows the module controls.
   **Columns** must be inside the settings gear; **Export selection** and
   **Documents** must be inside **Bulk actions**.
   There must be no saved-view selector or save/edit controls.
4. Confirm the `wildenmanager_*` tables were created.
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

- Select one and several orders in the native grid and export CSV and XLSX.
- Choose a reduced set of columns, repeat the export and confirm it is remembered.
- Confirm CSV accents and separators display correctly in Excel or LibreOffice.
- Confirm XLSX IDs and totals are numeric, and that header filters work.
- Confirm a restricted employee cannot export orders outside the active shop context.

## Combined documents

- Select orders with and without invoices and delivery slips.
- Confirm the preview marks each document type independently.
- Download the invoice PDF and verify all eligible invoices are present once.
- Download the delivery-slip PDF and verify all eligible slips are present once.
- Download both as ZIP and verify it contains the two valid PDF files.
- Confirm orders without the requested document are skipped and displayed as missing.

## Integrity diagnostics

- Confirm there is no **Integrity** button on the native Orders list.
- Open the module **Configure** page and confirm the integrity scan starts automatically.
- Confirm the scan uses the active shop context and does not require selected orders.
- Wait at least 15 seconds after results appear and confirm the table remains visible.
- Click refresh repeatedly and change filters quickly; only the latest response must remain visible.
- Confirm high, medium and information totals are displayed independently.
- Filter by each severity and by a specific issue type, then paginate the results.
- Open a reported order using its link and confirm the order ID and reference match.
- Export a filtered CSV and confirm it contains the same issue class and severity.
- Confirm deleted historical addresses/customers are informational rather than errors.
- Confirm opening, filtering and exporting the report does not change orders, histories,
  addresses, customers, invoices or delivery slips.

## Isolation

- Confirm installation and ordinary navigation do not write tables or
  configuration belonging to `idxrcustomproduct`, `autostockpack`, Redsys or
  any other module. The stock diagnostic may read the clone mapping only to
  classify custom products.

## Stock, cancellation and refund diagnostics

- Open **Configure → Stock and refunds** and confirm the stock scan starts automatically.
- Confirm the default filter shows only high severity and that zero results remain visible as a valid result.
- Switch to medium and information, filter by issue and product type, and paginate.
- Confirm order-level cancellation rows link to the correct order.
- Confirm product findings link to the correct product editor and show the attribute ID when applicable.
- Confirm standard products, packs and mapped custom clones are labelled separately.
- Export each filtered severity to CSV and confirm the exported rows match the active filters.
- Confirm formula-control characters in product names or references are neutralized in CSV.
- Confirm scanning, filtering and exporting do not change orders, order details, returns,
  credit slips, stock movements, stock availability, packs or custom-product data.

## Diagnostic review and controlled repair

- Mark findings as Reviewed, Justified and Confirmed and verify employee, note and date persist after refresh.
- Verify Justified and Confirmed require a non-empty note.
- Verify review changes appear in the audit history and do not change order, product or stock records.
- Verify only SuperAdmin sees and can call the physical-cache repair action.
- Verify repair is offered only for active standard-product `stock_cache_mismatch` rows marked Confirmed.
- Change the stock snapshot before repair and verify the action refuses to write.
- For an eligible test row, verify `quantity` and `reserved_quantity` remain unchanged while only `physical_quantity` becomes their sum.
- Verify the repaired incident disappears after refresh and the before/after values are recorded in audit.
- Verify packs, virtual products, custom clones, inactive products and orphan combinations are rejected server-side.
- Verify the four configuration areas are independent tabs and a direct URL hash reopens the selected tab.

## Permanent deletion of non-invoiced orders

- Use only a disposable staging order and take a database backup first.
- Confirm only SuperAdmin sees the delete control on the order page and in the native actions column.
- Confirm an order with either `invoice_number > 0` or a numbered `order_invoice` row shows a disabled control and clicking it displays the reason.
- Confirm an order whose only `order_invoice` rows have number `0` remains eligible and those technical rows are removed transactionally.
- Attempt the delete endpoint manually for an invoiced order and confirm the server rejects it.
- Confirm an order with a credit slip shows a disabled control with the credit-slip reason and the server rejects it.
- Confirm a paid order (with `order_payment` rows) without a numbered invoice is eligible and its payment rows are removed.
- Confirm the modal requires the exact order ID and states whether stock will be reinjected according to the setting.
- Delete one disposable non-invoiced order and confirm it disappears from the list and direct URL.
- Confirm no row remains for the deleted ID in the core order tables and module tables listed in `WmOrderDeletionService::knownTables()`.
- Confirm rows in third-party module tables and `stock_mvt` are left untouched.
- Create a second installation in the same database with a prefix such as `ps2_` holding an order with the same ID and confirm it is not modified.
- In an all-shops multistore context, confirm an order from a non-default shop can be deleted.
- For an exclusive cart and payment reference, confirm cart relations, customizations and payment rows are gone.
- Confirm shared carts or payment references belonging to another order are preserved.
- Confirm related order-detail tax, return, slip, message, module-note, diagnostic-review and historical audit rows are gone.
- Attach temporary listeners to the native Order delete-before and delete-after hooks and confirm both execute once.
- Confirm the new audit row contains employee, store and action but no order ID, reference, customer or order content.
- Add a related row to a non-transactional test table and confirm deletion is refused without changing any row.
- With the stock option disabled, confirm sellable stock is unchanged and reserved/physical quantities are recalculated.
- With the stock option enabled, delete an unshipped order and confirm sellable stock increases by the ordered quantity while physical stock stays equal.
- With the stock option enabled, delete a shipped order and confirm sellable and physical stock both increase.
- With the stock option enabled, delete a cancelled order and confirm stock is unchanged.
- With a partial refund that reinjected units, confirm only the remaining units are returned.
- Confirm packs follow the shop's pack stock type when reinjected.
- Confirm changing the option is SuperAdmin-only and recorded in the audit log.

## Status history corrections

- Confirm a profile without **Edit status history** sees no Edit/Delete buttons and the endpoints answer "Access denied".
- With the permission, confirm every history row has **Edit** and every row except the most recent has **Delete**.
- Edit the date and employee of an older entry and confirm the row, the order's current status and the stock are unchanged otherwise.
- Try to move an older entry after the most recent one, the most recent before an older one, a date before the order and a future date; all must be refused.
- Delete an older entry and confirm the current status, emails, stock, invoices and payments are unchanged.
- Confirm each change appears in the module audit with the previous and new values.
