# Wilden Manager

Private order-management module developed from scratch for PrestaShop 8.2 and
Wilden Militaria S.L.

Technical name: `wildenmanager` (folder `modules/wildenmanager/`, main file
`wildenmanager.php`, class `Wildenmanager`). Up to version 1.15.0 the module was
called `wilden_manager`; see **Migrating from wilden_manager** below.

## Version 2.1.1

Features:

- Advanced order filters with multishop scoping.
- Employee-selectable columns.
- Paginated order list and quick view.
- The real PrestaShop 8.2 Orders grid remains in use.
- Native order columns can be shown, hidden and reordered per employee and shop.
- Customer email and shipping method columns, including native-grid filters and sorting.
- Configurable CSV and XLSX exports from the native order selection.
- Independent export and diagnostics/audit permissions for each employee profile.
- Secure upgrade defaults that grant both new permissions only to SuperAdmin.
- Spreadsheet-formula injection protection for exported text values.
- Combined native invoice and delivery-slip PDFs from the selected orders.
- Optional ZIP download containing both document batches.
- Read-only order-integrity diagnostics with severity and issue filters.
- Detection of missing history, state mismatches, missing related records,
  incomplete customers and invalid document dates.
- Informational visibility of historical addresses and customers marked deleted.
- Filtered integrity-report export to CSV.
- Read-only stock, cancellation and refund diagnostics with conservative severity levels.
- Separate identification of standard products, packs and `idxrcustomproduct` clones when available.
- Direct order/product links and filtered CSV export for stock findings.
- Reviewed, Justified and Confirmed workflow with employee notes for every diagnostic finding.
- SuperAdmin-only individual repair for confirmed standard-product physical-cache mismatches.
- Separate Order integrity, Stock and refunds, Audit, and Administration tabs.
- Dedicated module control centre for diagnostics and future settings.
- Append-only audit trail for actions performed through the module, with a
  read-only, shop-scoped viewer and action, employee, order and date filters.
- Native links to the full PrestaShop order page and invoice PDF.
- SuperAdmin-only permanent deletion of individual orders without a numbered
  invoice or credit slip, from the order page or the native row-actions column.
- Transactional cleanup and verification limited to an explicit list of
  PrestaShop core tables and tables owned by this module.
- Optional stock reinjection on deletion, configurable in the Administration tab.
- Status history corrections on the order page, for profiles with the
  **Edit status history** permission: edit the date/time and employee of any
  entry, delete any entry except the most recent one. Only the record changes;
  the current status, emails, stock, invoices and payments are never affected.
- Interface translated to Spanish (`translations/es-ES/`) with PrestaShop's
  translation system, domain `Modules.Wildenmanager.Admin`.

Internal notes are not exposed in the native order list.

## Migrating from wilden_manager

Version 2.0.0 is a new module for PrestaShop, so it does not upgrade the old one
and does not migrate its data (audit, reviews, notes, column preferences).

1. In the module manager, uninstall the old **Wilden Manager** (`wilden_manager`).
   This drops its `wilden_manager_*` tables.
2. Delete the `modules/wilden_manager/` folder from the server.
3. Upload and install `wildenmanager` as described in **Installation**.
4. Review the profile permissions and the order deletion setting again.

## Explicit exclusions

The module does not modify or call `idxrcustomproduct`, `autostockpack`, Redsys,
or any other third-party module. Stock diagnostics may read the optional
`idxrcustomproduct` clone mapping solely to label custom products separately.

## Installation

1. Run `python tools/package.py`. It creates `build/wildenmanager/` (to upload
   by FTP to `modules/wildenmanager/`) and `dist/wildenmanager-<version>.zip`
   (to upload from the module manager), with runtime files only.
2. Install **Wilden Manager**, then clear the cache in **Advanced parameters →
   Performance** so the Spanish translations are loaded.
3. Open the native **Orders** page for selection-based order actions.
4. Choose and order the visible columns; the preference is saved per employee and shop.
5. Open **Configure** from the module manager for integrity diagnostics and module settings.

Reviewing or classifying an incident never changes shop data. The only repair
available is an individual SuperAdmin action for a confirmed standard-product
physical-cache mismatch. It leaves sellable and reserved quantities unchanged,
requires an exact snapshot match and reservation check, and verifies the result
inside a transaction. Orders, refunds, packs, custom products and inactive
products are never repaired automatically.

Stock diagnostics are evidence-based and intentionally conservative. An
informational result is a review candidate, not proof that stock was changed
incorrectly. In particular, historical cancellations cannot always be fully
reconstructed because PrestaShop does not preserve product-level stock movement
evidence indefinitely.

Permanent order deletion is intentionally conservative. It is disabled when an
issued, numbered invoice or a credit slip exists; an unnumbered technical
`order_invoice` row is not treated as an issued invoice and is removed with the
other relations. Orders with recorded payments but no numbered invoice can be
deleted: their `order_payment` rows are removed when no other order shares the
same reference. Only an explicit list of PrestaShop core tables and this
module's own tables is modified, resolved by exact prefixed name; third-party
module tables and `stock_mvt` history are left untouched. The operation requires
the exact order ID as confirmation and refuses to run when an affected table
cannot participate in the transaction.

Stock is not reinjected by default. When **Return ordered quantities to stock**
is enabled in the Administration tab, each line returns its ordered quantity
minus any quantity already reinjected by a refund; cancelled orders and orders
in error are skipped because PrestaShop already restored their stock. Reserved
and physical quantities of the affected products are always recalculated.
Prior order-linked audit rows are removed with the order. A new
anonymous audit event retains only the employee, store, time and the fact that a
deletion occurred; it stores no order ID, reference, customer or order content.
The main entity is removed through PrestaShop's native `Order->delete()` method
so object deletion hooks and cache clearing remain compatible with the core.

Run `bash tools/validate.sh` in an environment with PHP and Node.js before
packaging a release. It lints PHP and JavaScript and runs `tests/run.php`, a
regression suite against an in-memory fake of PrestaShop's `Db`. The repository
workflow validates PHP 7.2, 7.4 and 8.1, the versions supported by PrestaShop 8.2.
Do not upload `tests/`, `tools/`, `docs/` or `.github/` to the shop.

## Ownership

This codebase is an independent implementation. It does not contain source,
templates, translations, assets, database structures, or dependencies copied
from the previously evaluated commercial module.
