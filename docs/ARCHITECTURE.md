# Architecture

Wilden Manager is a standalone module for PrestaShop 8.2. It uses official
order-grid hooks and no overrides. Selection-based actions remain on the native
Orders page; diagnostics and future settings live in the module control centre.

## Components

- `WmOrderRepository`: read-only order queries, filters, pagination and shop
  scoping.
- `WmBulkOrderService`: preview and execution of native order-status changes.
- `WmOrderNote`: storage for private order notes owned by this module.
- `WmSavedView`: per-employee, per-shop filters and visible columns.
- `WmAuditLogger`: append-only record of module actions.
- `WmGridPreference`: per-employee and per-shop native-grid column settings.
- `WmExportService`: safe CSV and XLSX output for selected orders.
- `WmDocumentService`: combined native invoices and delivery slips.
- `WmIntegrityService`: read-only order consistency checks scoped by shop.
- `WmStockIntegrityService`: read-only quantity, refund, cancellation and stock-cache checks scoped by shop.
- `WmIntegrityReview`: module-owned human review states and notes; it never changes shop records.
- `WmStockRepairService`: exact-snapshot, transactional repair of one eligible physical stock cache row.
- `AdminWildenManagerOrdersController`: authorization, validation, exports and
  AJAX actions.
- `configuration.tpl` and `configuration.js`: diagnostics and future module
  settings outside the native Orders workspace.

## Safety decisions

- Every order query is restricted to the current shop context.
- Order status changes use `OrderHistory`, never direct SQL updates.
- Bulk changes require a signed preview no older than 15 minutes.
- The preview signature includes the original status of every order; execution
  stops if any selected order changed after preview.
- A single action is limited to 100 orders.
- Customer email is optional and email failure is reported separately from
  status-update failure.
- CSV text cells beginning with formula-control characters are neutralized.
- Integrity diagnostics never repair or update business data.
- Stock diagnostics classify uncertain cancellation, refund and pack evidence as informational rather than errors.
- Notes are rendered escaped and limited to 5,000 characters.
- The module contains no third-party integrations or inherited source.
