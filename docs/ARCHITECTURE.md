# Architecture

Wilden Manager 1.0.0 is a standalone legacy-admin module for PrestaShop 8.2.
It intentionally uses no overrides and registers no runtime hooks.

## Components

- `WmOrderRepository`: read-only order queries, filters, pagination and shop
  scoping.
- `WmBulkOrderService`: preview and execution of native order-status changes.
- `WmOrderNote`: storage for private order notes owned by this module.
- `WmSavedView`: per-employee, per-shop filters and visible columns.
- `WmAuditLogger`: append-only record of module actions.
- `AdminWildenManagerOrdersController`: authorization, validation, exports and
  view composition.

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
- Notes are rendered escaped and limited to 5,000 characters.
- The module contains no third-party integrations or inherited source.
