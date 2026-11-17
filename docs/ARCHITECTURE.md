# Architecture

Wilden Manager is a standalone module for PrestaShop 8.2. It uses official
order-grid hooks and no overrides. Selection-based actions remain on the native
Orders page; diagnostics and future settings live in the module control centre.

## Components

- `WmOrderRepository`: read-only order queries, filters, pagination and shop
  scoping.
- `WmOrderNote`: storage for private order notes owned by this module.
- `WmAuditLogger`: append-only record of module actions.
- `WmGridPreference`: per-employee and per-shop native-grid column settings.
- `WmExportService`: safe CSV and XLSX output for selected orders.
- `WmDocumentService`: combined native invoices and delivery slips.
- `WmIntegrityService`: read-only order consistency checks scoped by shop.
- `WmStockIntegrityService`: read-only quantity, refund, cancellation and stock-cache checks scoped by shop.
- `WmIntegrityReview`: module-owned human review states and notes; it never changes shop records.
- `WmStockRepairService`: exact-snapshot, transactional repair of one eligible physical stock cache row.
- `WmOrderDeletionService`: SuperAdmin-only transactional removal of one order
  without a numbered invoice or credit slip, limited to an explicit list of core
  and module-owned tables, with optional stock reinjection.
- `AdminWildenManagerOrdersController`: authorization, validation, exports and
  AJAX actions.
- `configuration.tpl` and `configuration.js`: diagnostics and future module
  settings outside the native Orders workspace.

## Safety decisions

- Every order query is restricted to the current shop context.
- CSV text cells beginning with formula-control characters are neutralized.
- Integrity diagnostics never repair or update business data.
- Stock diagnostics classify uncertain cancellation, refund and pack evidence as informational rather than errors.
- Notes are rendered escaped and limited to 5,000 characters.
- The module contains no third-party integrations or inherited source.
- Permanent deletion blocks numbered invoices and credit slips, rechecks that
  eligibility under lock, resolves tables by exact prefixed name (never a
  `LIKE` pattern), touches no third-party table, refuses affected
  non-transactional tables, removes the main entity through `Order->delete()`,
  verifies cleanup before commit, reinjects stock only when the
  `WM_DELETE_REINJECT_STOCK` setting is enabled and always recalculates reserved
  and physical quantities of the affected products.
