# Wilden Manager

Private order-management module developed from scratch for PrestaShop 8.2 and
Wilden Militaria S.L.

## Version 1.6.0

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
- Bulk order-state changes with a mandatory preview, signed confirmation,
  15-minute expiry and a maximum of 100 orders per operation.
- Optional customer email when applying a bulk state.
- Append-only audit trail for actions performed through the module.
- Native links to the full PrestaShop order page and invoice PDF.

Internal notes are not exposed in the native order list as of version 1.5.0.
Existing stored notes are preserved during upgrades to avoid destructive data loss.

## Explicit exclusions

Version 1.0.0 does not integrate with `idxrcustomproduct`, `autostockpack`,
Redsys, or any other third-party module.

## Installation

1. Upload `wilden_manager.zip` from the PrestaShop module manager.
2. Install **Wilden Manager**.
3. Open the native **Orders** page and use the **Columns** button.
4. Choose and order the visible columns; the preference is saved per employee and shop.

Always test state changes and customer emails in staging before deploying a
new release to production.

Run `bash tools/validate.sh` in an environment with PHP and Node.js before
packaging a release. The repository workflow validates PHP 8.1, 8.2 and 8.3.

## Ownership

This codebase is an independent implementation. It does not contain source,
templates, translations, assets, database structures, or dependencies copied
from the previously evaluated commercial module.
