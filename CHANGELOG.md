# Changelog

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
