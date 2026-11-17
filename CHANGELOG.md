# Changelog

## 1.15.0

- Rewrote permanent order deletion around an explicit list of PrestaShop core tables and tables owned by this module.
- Fixed table discovery matching other installations in the same database: the `_` in the table prefix was treated as a `LIKE` wildcard (for example `ps_%` also matched `ps2_orders`). Tables are now resolved by exact name.
- Third-party module tables and `stock_mvt` history are no longer modified by order deletion.
- Orders with a credit slip are now protected, like orders with a numbered invoice.
- Orders with recorded payments but no numbered invoice remain deletable; their payment records are removed when no other order shares the reference.
- Added an Administration setting to return ordered quantities to stock on deletion (disabled by default). Refund reinjections are not counted twice and cancelled or error orders are skipped.
- Reserved and physical stock quantities are recalculated for the affected products after deletion.
- Deletion now works in an all-shops multistore context for orders of any shop in that context.
- The blocking reason (invoice or credit slip) is shown on disabled delete controls.
- Fixed the delete button on the order page doing nothing: its trigger class was passed as a second `class` attribute that browsers ignore. Disabled buttons now show their blocking reason when clicked.
- Order-grid assets, column preferences and JS strings now load only on the Orders pages instead of every back-office page.
- Module assets are cache-busted with the file modification time, so an FTP upload is picked up without a version change.
- Added `WmDb`: refuses queries ending with `LIMIT`/`FOR UPDATE` in `getRow`/`getValue`, provides `lockRow()` and an exact-name `tableExists()` (replacing `SHOW TABLES LIKE`, where `_` was a wildcard).
- Text filters now escape `%`, `_` and `\` so they match literally, in the module list and in the native grid's email and shipping-method filters.
- Unified spreadsheet-formula protection in `WmSpreadsheet` for every CSV/XLSX export; the legacy CSV export now also covers values starting with spaces, tabs or line breaks, and plain negative numbers stay numeric.
- Added `tests/run.php` (fake PrestaShop Db, 30 scenarios) to `tools/validate.sh`; CI now runs PHP 7.2, 7.4 and 8.1, the versions PrestaShop 8.2 supports.
- Fixed SQL syntax errors caused by `Db::getRow()`/`Db::getValue()` appending their own `LIMIT 1` (duplicate `LIMIT` and `FOR UPDATE LIMIT 1`).

## 1.14.2

- Distinguished issued invoices from unnumbered technical `order_invoice` rows.
- Allowed a non-invoiced order to be deleted when its only invoice rows have number `0`; those rows remain part of the transactional cleanup.
- Added visible feedback when an actually invoiced order has its delete control disabled.

## 1.14.1

- Replaced the direct deletion of the main `orders` row with PrestaShop's native `Order->delete()` method.
- Preserved the surrounding transaction, invoice lock, complete relation cleanup and final verification.
- Restored native object deletion hooks and ObjectModel cache clearing for compatibility with other modules.

## 1.14.0

- Added SuperAdmin-only permanent deletion for individual non-invoiced orders.
- Added a delete button to the order page and an icon to the native row-actions column.
- Disabled the operation visually and server-side whenever an invoice number or invoice row exists.
- Required the exact order ID as explicit confirmation and rechecked eligibility under a database lock.
- Removed direct and supported indirect order, detail, cart, payment, return, message and module relations inside one transaction.
- Added schema discovery for third-party tables carrying supported order identifiers.
- Refused deletion when an affected table is non-transactional and verified that no supported relation remains before commit.
- Kept stock unchanged and recorded only an anonymous audit event without an order identifier.

## 1.13.3

- Removed saved order views from the native Orders interface and module administration.
- Removed the create, apply, edit, default and delete workflows and their server endpoints.
- Preserved existing saved-view table rows during upgrades to avoid destructive data loss.

## 1.13.2

- Moved order exports and combined documents into PrestaShop's native bulk-actions menu.
- Moved configurable columns into the native grid settings menu.
- Kept saved-view controls in the order header and removed the three standalone action buttons.

## 1.13.1

- Removed **Safe status change** from the native and legacy order interfaces.
- Removed the status-change endpoints and service so the discontinued operation cannot be invoked manually.
- Preserved historical status-change audit records as read-only history.

## 1.13.0

- Split the module control centre into Order integrity, Stock and refunds, Audit, and Administration tabs.
- Added auditable Reviewed, Justified, and Confirmed states with employee notes for diagnostic findings.
- Added a SuperAdmin-only, one-row-at-a-time physical stock cache repair guarded by confirmation, snapshot matching, reservation verification, a transaction, and a post-write check.
- Kept sellable and reserved quantities unchanged during the controlled repair.
- Reclassified missing credit-slip evidence as informational because it can be an intentional refund option.

## 1.12.0 - 2026-09-16

- Added read-only stock, cancellation and refund diagnostics to the module control centre.
- Added high-severity checks for impossible refunded, returned, reinjected, credit-slip and return-request quantities.
- Added medium-severity checks for refund/credit-slip evidence and standard stock-cache reconciliation.
- Added informational review queues for non-reinjected refunds, cancellations without persistent restock evidence and pack stock caches.
- Added filters by issue, severity and standard/pack/custom/order-level scope.
- Added direct links to related orders and products, pagination and safe filtered CSV export.
- Kept every diagnostic endpoint read-only and protected by the existing diagnostics permission.

## 1.11.0 - 2026-09-16

- Added named views for the native PrestaShop Orders grid.
- Stored filters, sorting, page size and configurable columns per employee and store.
- Added create, apply, rename, update, delete and optional default-view workflows.
- Added strict server-side validation and a limit of 25 views per employee and store.
- Added SuperAdmin review and cleanup of native saved views under module configuration.
- Preserved incompatible historical views without exposing or deleting them automatically.

## 1.10.1 - 2026-09-16

- Fixed SuperAdmin detection by using PrestaShop's `_PS_ADMIN_PROFILE_` constant.
- Added an upgrade repair that grants both module permissions to SuperAdmin.
- Preserved permissions already assigned to every other employee profile.

## 1.10.0 - 2026-09-16

- Added independent profile permissions for CSV/XLSX exports and diagnostics/audit.
- Added a SuperAdmin-only permissions matrix to the module configuration page.
- Enforced permissions both in the interface and on every protected server endpoint.
- Granted both permissions only to SuperAdmin on installation and upgrade.
- Kept document downloads and bulk status changes outside these new restrictions.

## 1.9.1 - 2026-09-16

- Fixed related-order links opening the native Orders list instead of the order detail page.
- Centralised PrestaShop-compatible order-detail URL generation for audit, integrity and quick-view links.

## 1.9.0 - 2026-09-16

- Added a read-only audit-history viewer to the module control centre.
- Added shop-scoped filters for action, employee, order and date range.
- Added pagination and safe, expandable JSON details for every audit entry.
- Added a shop/date audit index for efficient scoped history queries.
- Removed the obsolete audit block from the retired parallel order-list template.

## 1.8.4 - 2026-09-16

- Fixed the integrity table disappearing at the legacy Bootstrap 1200-pixel breakpoint.
- Replaced the floated diagnostic filter row with a module-owned CSS grid.
- Cleared the results block defensively so it cannot move behind floated controls.

## 1.8.3 - 2026-09-15

- Added the module version to back-office JavaScript and CSS URLs.
- Prevented browsers from reusing configuration assets from an older module release.
- Applied the same cache-busting strategy to the native Orders grid assets.

## 1.8.2 - 2026-09-15

- Fixed integrity results disappearing after they were rendered on the module configuration page.
- Replaced the disposable dynamic table with a stable server-rendered result table.
- Added one-time dashboard initialization and cancellation of stale AJAX requests.
- Prevented older responses from replacing the most recent diagnostic result.

## 1.8.1 - 2026-09-15

- Removed the Integrity button and diagnostic interface from the native Orders list.
- Added a dedicated module control centre on the configuration page.
- Moved integrity filters, results, pagination and CSV export to the control centre.
- Added a clear area for future checks and module settings.
- Kept order-selection actions on the native Orders list unchanged.

## 1.8.0 - 2026-09-15

- Added a read-only integrity panel to the native Orders page.
- Added high-severity checks for missing history, state mismatches and missing related records.
- Added medium-severity checks for incomplete customers and document numbers without valid dates.
- Classified soft-deleted historical addresses and customers as informational findings.
- Added issue/severity filters, pagination, native order links and safe CSV export.
- Kept the diagnostic workflow free of automatic repairs or data mutations.

## 1.7.0 - 2026-09-15

- Added document availability preview for the native order selection.
- Added combined invoice PDF generation using PrestaShop's native PDF templates.
- Added combined delivery-slip PDF generation using PrestaShop's native PDF templates.
- Added an optional ZIP containing both PDF batches.
- Added shop-scope, permission and 100-order limits plus download auditing.

## 1.6.0 - 2026-09-15

- Added configurable CSV and XLSX export from the native order selection.
- Added 15 exportable order, customer, delivery, payment and shop fields.
- Added employee-local export column preferences.
- Added formula-injection and invalid-XML character protection.
- Added numeric Excel cells for order IDs and totals, frozen headers and autofilters.
- Added access, shop-scope and maximum-row validation plus export auditing.

## 1.5.0 - 2026-09-14

- Removed the internal-note column and editor from the native order grid.
- Added a safe bulk status-change workflow driven by the native order selection.
- Added a mandatory signed preview that expires after 15 minutes and is invalidated if an order changes.
- Added a final per-order state check immediately before each update.
- Added optional customer emails, per-order results, warnings, errors and audit records.
- Preserved existing note data without exposing the discontinued feature.

## 1.4.0 - 2026-09-14

- Added an optional internal-note column to the native order grid.
- Added native-grid filtering and sorting for internal notes.
- Added an AJAX editor with employee permission checks and a 5,000-character limit.
- Added before/after audit data for note changes.

## 1.3.0 - 2026-09-14

- Added configurable Customer email and Shipping method columns to the native order grid.
- Added filtering and sorting for both new columns through the official query-builder hook.
- Enabled both columns automatically for existing employee preferences during upgrade.

## 1.2.2 - 2026-09-14

- Fixed native column ordering by reading identifiers from PrestaShop column objects.
- Limited the selector to columns that exist for the current B2B and multistore configuration.
- Intersected configurable columns with the actual native grid definition before modifying it.

## 1.2.1 - 2026-09-13

- Fixed stale SQL caching that could discard a newly saved grid preference.
- Added server-side read-after-write verification for column settings.
- Applied visibility and ordering directly to the native grid DOM as a resilient fallback.

## 1.2.0 - 2026-09-13

- Changed the integration to customize the real PrestaShop Orders grid.
- Added per-employee and per-shop visibility and ordering preferences.
- Added a Columns dialog directly to the native Orders page.
- Kept PrestaShop's native filters, previews, row actions and bulk actions untouched.

## 1.1.0 - 2026-09-13

- Reworked the main screen as a PrestaShop 8.2 native-style order grid.
- Added the standard new-client and delivery-country columns.
- Moved searching into per-column filters in the grid header.
- Added configurable email, company, carrier, store and internal-note columns.
- Kept saved views, quick view, CSV export, audited bulk preview and activity history.

## 1.0.1 - 2026-09-13

- Fixed the order-state color query for the PrestaShop 8.2 database schema.
- Added a no-op upgrade step so existing 1.0.0 installations can update normally.

## 1.0.0 - 2026-09-13

- Initial clean-room implementation.
- Added advanced filters, visible-column selection and saved views.
- Added order quick view and private internal notes.
- Added CSV export and native order/invoice actions.
- Added preview-first bulk state changes with optional customer email.
- Added module-specific audit history and multishop restrictions.
