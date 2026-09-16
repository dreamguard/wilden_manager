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

- Select one and several orders in the native grid and export CSV and XLSX.
- Choose a reduced set of columns, repeat the export and confirm it is remembered.
- Confirm CSV accents and separators display correctly in Excel or LibreOffice.
- Confirm XLSX IDs and totals are numeric, and that header filters work.
- Confirm a restricted employee cannot export orders outside the active shop context.

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
