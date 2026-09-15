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
- Confirm high, medium and information totals are displayed independently.
- Filter by each severity and by a specific issue type, then paginate the results.
- Open a reported order using its link and confirm the order ID and reference match.
- Export a filtered CSV and confirm it contains the same issue class and severity.
- Confirm deleted historical addresses/customers are informational rather than errors.
- Confirm opening, filtering and exporting the report does not change orders, histories,
  addresses, customers, invoices or delivery slips.

## Isolation

- Confirm installation and ordinary navigation do not read or write tables or
  configuration belonging to `idxrcustomproduct`, `autostockpack`, Redsys or
  any other module.
