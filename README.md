# Wilden Manager

Private order-management module developed from scratch for PrestaShop 8.2 and
Wilden Militaria S.L.

## Version 1.9.0

The initial version provides:

- Advanced order filters with multishop scoping.
- Employee-selectable columns.
- Personal saved views and an optional default view.
- Paginated order list and quick view.
- The real PrestaShop 8.2 Orders grid remains in use.
- Native order columns can be shown, hidden and reordered per employee and shop.
- Customer email and shipping method columns, including native-grid filters and sorting.
- Configurable CSV and XLSX exports from the native order selection.
- Spreadsheet-formula injection protection for exported text values.
- Combined native invoice and delivery-slip PDFs from the selected orders.
- Optional ZIP download containing both document batches.
- Bulk order-state changes with a mandatory preview, signed confirmation,
  15-minute expiry and a maximum of 100 orders per operation.
- Optional customer email when applying a bulk state.
- Read-only order-integrity diagnostics with severity and issue filters.
- Detection of missing history, state mismatches, missing related records,
  incomplete customers and invalid document dates.
- Informational visibility of historical addresses and customers marked deleted.
- Filtered integrity-report export to CSV.
- Dedicated module control centre for diagnostics and future settings.
- Append-only audit trail for actions performed through the module, with a
  read-only, shop-scoped viewer and action, employee, order and date filters.
- Native links to the full PrestaShop order page and invoice PDF.

Internal notes are not exposed in the native order list as of version 1.5.0.
Existing stored notes are preserved during upgrades to avoid destructive data loss.

## Explicit exclusions

Version 1.0.0 does not integrate with `idxrcustomproduct`, `autostockpack`,
Redsys, or any other third-party module.

## Installation

1. Upload `wilden_manager.zip` from the PrestaShop module manager.
2. Install **Wilden Manager**.
3. Open the native **Orders** page for selection-based order actions.
4. Choose and order the visible columns; the preference is saved per employee and shop.
5. Open **Configure** from the module manager for integrity diagnostics and module settings.

Always test state changes and customer emails in staging before deploying a
new release to production.

The integrity panel on the module configuration page never repairs or updates
data. Review each reported order before deciding whether a manual correction
is appropriate.

Run `bash tools/validate.sh` in an environment with PHP and Node.js before
packaging a release. The repository workflow validates PHP 8.1, 8.2 and 8.3.

## Ownership

This codebase is an independent implementation. It does not contain source,
templates, translations, assets, database structures, or dependencies copied
from the previously evaluated commercial module.
