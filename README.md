# Wilden Manager

Private order-management module developed from scratch for PrestaShop 8.2 and
Wilden Militaria S.L.

## Version 1.1.0

The initial version provides:

- Advanced order filters with multishop scoping.
- Employee-selectable columns.
- Personal saved views and an optional default view.
- Paginated order list and quick view.
- Native-style PrestaShop 8.2 order grid with per-column filters.
- Configurable native and extended order columns.
- Internal order notes.
- CSV export protected against spreadsheet-formula injection.
- Bulk order-state changes with a mandatory preview, signed confirmation,
  15-minute expiry and a maximum of 100 orders per operation.
- Optional customer email when applying a bulk state.
- Append-only audit trail for actions performed through the module.
- Native links to the full PrestaShop order page and invoice PDF.

## Explicit exclusions

Version 1.0.0 does not integrate with `idxrcustomproduct`, `autostockpack`,
Redsys, or any other third-party module.

## Installation

1. Upload `wilden_manager.zip` from the PrestaShop module manager.
2. Install **Wilden Manager**.
3. Open **Orders → Wilden Manager**.
4. Grant view/edit permissions to the appropriate employee profiles.

Always test state changes and customer emails in staging before deploying a
new release to production.

Run `bash tools/validate.sh` in an environment with PHP and Node.js before
packaging a release. The repository workflow validates PHP 8.1, 8.2 and 8.3.

## Ownership

This codebase is an independent implementation. It does not contain source,
templates, translations, assets, database structures, or dependencies copied
from the previously evaluated commercial module.
