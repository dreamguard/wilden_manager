# Changelog

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
