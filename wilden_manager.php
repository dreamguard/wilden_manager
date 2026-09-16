<?php
/**
 * Wilden Manager
 *
 * @author    Wilden Militaria S.L.
 * @copyright 2026 Wilden Militaria S.L.
 * @license   Proprietary
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/WmAuditLogger.php';
require_once __DIR__ . '/classes/WmOrderNote.php';
require_once __DIR__ . '/classes/WmSavedView.php';
require_once __DIR__ . '/classes/WmGridPreference.php';
require_once __DIR__ . '/classes/WmOrderRepository.php';
require_once __DIR__ . '/classes/WmBulkOrderService.php';
require_once __DIR__ . '/classes/WmExportService.php';
require_once __DIR__ . '/classes/WmDocumentService.php';
require_once __DIR__ . '/classes/WmIntegrityService.php';
require_once __DIR__ . '/classes/WmProfilePermission.php';

class Wilden_manager extends Module
{
    const VERSION = '1.10.1';
    const TAB_CLASS = 'AdminWildenManagerOrders';
    const MAX_BULK_ORDERS = 100;
    const MAX_EXPORT_ORDERS = 1000;
    const MAX_DOCUMENT_ORDERS = 100;
    const MAX_INTEGRITY_EXPORT = 10000;

    public function getIntegrityIssueTypes()
    {
        return array(
            'missing_history' => $this->l('Order without status history'),
            'state_history_mismatch' => $this->l('Current status differs from history'),
            'delivery_address_missing' => $this->l('Delivery address missing'),
            'invoice_address_missing' => $this->l('Invoice address missing'),
            'customer_missing' => $this->l('Customer missing'),
            'customer_incomplete' => $this->l('Customer data incomplete'),
            'invoice_number_without_date' => $this->l('Invoice number without date'),
            'delivery_number_without_date' => $this->l('Delivery number without date'),
            'delivery_address_deleted' => $this->l('Historical delivery address marked deleted'),
            'invoice_address_deleted' => $this->l('Historical invoice address marked deleted'),
            'customer_deleted' => $this->l('Historical customer marked deleted'),
        );
    }

    public function getAuditActionLabels()
    {
        return array(
            'native_order_columns_updated' => $this->l('Order columns updated'),
            'bulk_status_change' => $this->l('Order status changed'),
            'bulk_status_error' => $this->l('Order status change failed'),
            'orders_exported' => $this->l('Orders exported'),
            'documents_download_requested' => $this->l('Order documents downloaded'),
            'integrity_report_exported' => $this->l('Integrity report exported'),
            'profile_permissions_updated' => $this->l('Profile permissions updated'),
            'note_updated' => $this->l('Internal note updated (historical)'),
            'saved_view_created' => $this->l('Saved view created (historical)'),
            'saved_view_deleted' => $this->l('Saved view deleted (historical)'),
        );
    }

    public function __construct()
    {
        $this->name = 'wilden_manager';
        $this->tab = 'administration';
        $this->version = self::VERSION;
        $this->author = 'Wilden Militaria S.L.';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = array('min' => '8.0.0', 'max' => '8.99.99');

        parent::__construct();

        $this->displayName = $this->l('Wilden Manager');
        $this->description = $this->l('Advanced and auditable order management for PrestaShop.');
        $this->confirmUninstall = $this->l('Remove Wilden Manager and all of its notes, saved views and audit records?');
    }

    public function install()
    {
        return parent::install()
            && $this->installDatabase()
            && $this->installTab()
            && $this->registerHook('actionOrderGridDefinitionModifier')
            && $this->registerHook('actionOrderGridQueryBuilderModifier')
            && $this->registerHook('displayBackOfficeHeader');
    }

    public function uninstall()
    {
        return parent::uninstall()
            && $this->uninstallTab()
            && $this->uninstallDatabase();
    }

    public function getContent()
    {
        $isSuperAdmin = $this->isCurrentEmployeeSuperAdmin();
        $canViewDiagnostics = $this->canCurrentEmployeeViewDiagnostics();
        $messages = '';

        if (Tools::isSubmit('submitWmProfilePermissions')) {
            if (!$isSuperAdmin) {
                $messages .= $this->displayError($this->l('Only SuperAdmin can change profile permissions.'));
            } elseif (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
                $messages .= $this->displayError($this->l('This action requires a POST request.'));
            } elseif (WmProfilePermission::save(
                (array) Tools::getValue('wm_export_profiles', array()),
                (array) Tools::getValue('wm_diagnostic_profiles', array()),
                (int) $this->context->language->id
            )) {
                WmAuditLogger::log('profile_permissions_updated');
                $messages .= $this->displayConfirmation($this->l('Profile permissions have been saved.'));
            } else {
                $messages .= $this->displayError($this->l('Profile permissions could not be saved.'));
            }
        }

        $this->context->controller->addCSS($this->getVersionedAssetUrl('views/css/configuration.css'));
        if ($canViewDiagnostics) {
            $this->context->controller->addJS($this->getVersionedAssetUrl('views/js/configuration.js'));
            $jsConfiguration = $this->getIntegrityJsConfiguration();
            $jsConfiguration['audit'] = $this->getAuditJsConfiguration();
            Media::addJsDef(array('wildenManagerConfiguration' => $jsConfiguration));
        }

        $this->context->smarty->assign(array(
            'wm_module_version' => self::VERSION,
            'wm_orders_url' => $this->context->link->getAdminLink('AdminOrders'),
            'wm_can_view_diagnostics' => $canViewDiagnostics,
            'wm_is_super_admin' => $isSuperAdmin,
            'wm_profile_permissions' => $isSuperAdmin
                ? WmProfilePermission::getProfiles((int) $this->context->language->id)
                : array(),
            'wm_permissions_action' => $this->context->link->getAdminLink('AdminModules', true, array(), array(
                'configure' => $this->name,
            )),
        ));

        return $messages . $this->display(__FILE__, 'views/templates/admin/configuration.tpl');
    }

    public function isCurrentEmployeeSuperAdmin()
    {
        return isset($this->context->employee)
            && WmProfilePermission::isSuperAdmin((int) $this->context->employee->id_profile);
    }

    public function canCurrentEmployeeExport()
    {
        return isset($this->context->employee)
            && WmProfilePermission::canExport((int) $this->context->employee->id_profile);
    }

    public function canCurrentEmployeeViewDiagnostics()
    {
        return isset($this->context->employee)
            && WmProfilePermission::canViewDiagnostics((int) $this->context->employee->id_profile);
    }

    public function getNativeOrderColumns()
    {
        $columns = array(
            'id_order' => $this->l('ID'),
            'reference' => $this->l('Reference'),
            'new' => $this->l('New client'),
            'country_name' => $this->l('Delivery'),
            'customer' => $this->l('Customer'),
            'customer_email' => $this->l('Customer email'),
        );

        if ((bool) Configuration::get('PS_B2B_ENABLE')) {
            $columns['company'] = $this->l('Company');
        }

        $columns['total_paid_tax_incl'] = $this->l('Total');
        $columns['payment'] = $this->l('Payment');
        $columns['shipping_method'] = $this->l('Shipping method');
        $columns['osname'] = $this->l('Status');
        $columns['date_add'] = $this->l('Date');

        if (Shop::isFeatureActive()) {
            $columns['shop_name'] = $this->l('Store');
        }

        return $columns;
    }

    public function getExportColumns()
    {
        return array(
            'id_order' => $this->l('ID'),
            'reference' => $this->l('Reference'),
            'date_add' => $this->l('Date'),
            'customer' => $this->l('Customer'),
            'email' => $this->l('Customer email'),
            'company' => $this->l('Company'),
            'country_name' => $this->l('Delivery country'),
            'delivery_postcode' => $this->l('Delivery postcode'),
            'delivery_city' => $this->l('Delivery city'),
            'total_paid_tax_incl' => $this->l('Total paid tax included'),
            'currency_iso' => $this->l('Currency'),
            'payment' => $this->l('Payment'),
            'state_name' => $this->l('Status'),
            'carrier_name' => $this->l('Shipping method'),
            'shop_name' => $this->l('Store'),
        );
    }

    public function hookActionOrderGridDefinitionModifier(array $params)
    {
        if (empty($params['definition'])) {
            return;
        }

        $definition = $params['definition'];
        $columns = $definition->getColumns();
        $filters = $definition->getFilters();

        $columns->addAfter(
            'customer',
            (new \PrestaShop\PrestaShop\Core\Grid\Column\Type\DataColumn('customer_email'))
                ->setName($this->l('Customer email'))
                ->setOptions(array('field' => 'customer_email'))
        );
        $columns->addAfter(
            'payment',
            (new \PrestaShop\PrestaShop\Core\Grid\Column\Type\DataColumn('shipping_method'))
                ->setName($this->l('Shipping method'))
                ->setOptions(array('field' => 'shipping_method'))
        );

        $filters->add(
            (new \PrestaShop\PrestaShop\Core\Grid\Filter\Filter(
                'customer_email',
                \Symfony\Component\Form\Extension\Core\Type\TextType::class
            ))
                ->setTypeOptions(array(
                    'required' => false,
                    'attr' => array('placeholder' => $this->l('Search email')),
                ))
                ->setAssociatedColumn('customer_email')
        );
        $filters->add(
            (new \PrestaShop\PrestaShop\Core\Grid\Filter\Filter(
                'shipping_method',
                \Symfony\Component\Form\Extension\Core\Type\TextType::class
            ))
                ->setTypeOptions(array(
                    'required' => false,
                    'attr' => array('placeholder' => $this->l('Search shipping method')),
                ))
                ->setAssociatedColumn('shipping_method')
        );

        $presentColumns = $this->getGridCollectionIds($columns);
        $available = array_intersect_key(
            $this->getNativeOrderColumns(),
            array_flip($presentColumns)
        );
        $selected = WmGridPreference::get(
            (int) $this->context->employee->id,
            (int) $this->context->shop->id
        );

        if (!$selected) {
            $selected = array_keys($available);
        }

        $selected = array_values(array_intersect($selected, array_keys($available)));
        foreach (array_keys($available) as $columnId) {
            if (!in_array($columnId, $selected, true)) {
                $columns->remove($columnId);
                $filters->remove($columnId);
            }
        }

        if (method_exists($columns, 'move')) {
            $position = in_array('orders_bulk', $presentColumns, true) ? 1 : 0;
            foreach ($selected as $columnId) {
                if (in_array($columnId, $presentColumns, true)) {
                    $columns->move($columnId, $position++);
                }
            }
        }
    }

    public function hookActionOrderGridQueryBuilderModifier(array $params)
    {
        if (empty($params['search_query_builder']) || empty($params['count_query_builder'])) {
            return;
        }

        $searchQueryBuilder = $params['search_query_builder'];
        $countQueryBuilder = $params['count_query_builder'];
        $searchCriteria = isset($params['search_criteria']) ? $params['search_criteria'] : null;

        $searchQueryBuilder
            ->addSelect('cu.email AS customer_email')
            ->leftJoin(
                'o',
                _DB_PREFIX_ . 'carrier',
                'wm_shipping_carrier',
                'o.id_carrier = wm_shipping_carrier.id_carrier'
            )
            ->addSelect('wm_shipping_carrier.name AS shipping_method');
        $countQueryBuilder->leftJoin(
            'o',
            _DB_PREFIX_ . 'carrier',
            'wm_shipping_carrier',
            'o.id_carrier = wm_shipping_carrier.id_carrier'
        );

        if (!$searchCriteria) {
            return;
        }

        $filters = $searchCriteria->getFilters();
        $this->applyNativeTextFilter(
            $searchQueryBuilder,
            $countQueryBuilder,
            $filters,
            'customer_email',
            'cu.email'
        );
        $this->applyNativeTextFilter(
            $searchQueryBuilder,
            $countQueryBuilder,
            $filters,
            'shipping_method',
            'wm_shipping_carrier.name'
        );

        if ($searchCriteria->getOrderBy() === 'customer_email') {
            $searchQueryBuilder->orderBy('cu.email', $searchCriteria->getOrderWay());
        } elseif ($searchCriteria->getOrderBy() === 'shipping_method') {
            $searchQueryBuilder->orderBy('wm_shipping_carrier.name', $searchCriteria->getOrderWay());
        }
    }

    private function applyNativeTextFilter(
        $searchQueryBuilder,
        $countQueryBuilder,
        array $filters,
        $filterName,
        $field
    ) {
        if (!isset($filters[$filterName]) || trim((string) $filters[$filterName]) === '') {
            return;
        }

        $parameter = 'wm_' . $filterName;
        $value = '%' . trim((string) $filters[$filterName]) . '%';
        foreach (array($searchQueryBuilder, $countQueryBuilder) as $queryBuilder) {
            $queryBuilder
                ->andWhere($field . ' LIKE :' . $parameter)
                ->setParameter($parameter, $value);
        }
    }

    private function getGridCollectionIds($collection)
    {
        $ids = array();
        foreach ($collection->toArray() as $key => $item) {
            if (is_object($item) && method_exists($item, 'getId')) {
                $ids[] = (string) $item->getId();
            } elseif (is_array($item) && isset($item['id'])) {
                $ids[] = (string) $item['id'];
            } elseif (is_string($key)) {
                $ids[] = $key;
            }
        }

        return array_values(array_unique($ids));
    }

    public function hookDisplayBackOfficeHeader()
    {
        $available = $this->getNativeOrderColumns();
        $selected = WmGridPreference::get(
            (int) $this->context->employee->id,
            (int) $this->context->shop->id
        );
        if (!$selected) {
            $selected = array_keys($available);
        }

        $columnOptions = array();
        foreach ($available as $id => $label) {
            $columnOptions[] = array('id' => $id, 'label' => $label);
        }
        $exportColumnOptions = array();
        $canExport = $this->canCurrentEmployeeExport();
        if ($canExport) {
            foreach ($this->getExportColumns() as $id => $label) {
                $exportColumnOptions[] = array('id' => $id, 'label' => $label);
            }
        }

        $this->context->controller->addJS($this->getVersionedAssetUrl('views/js/native-order-columns.js'));
        $this->context->controller->addCSS($this->getVersionedAssetUrl('views/css/native-order-columns.css'));
        Media::addJsDef(array(
            'wildenManagerNativeColumns' => array(
                'columns' => $columnOptions,
                'selected' => array_values($selected),
                'storageKey' => 'wilden_manager_order_columns_' . self::VERSION . '_' .
                    (int) $this->context->employee->id . '_' . (int) $this->context->shop->id,
                'saveUrl' => $this->context->link->getAdminLink(self::TAB_CLASS) . '&ajax=1&action=saveNativeColumns',
                'bulkPreviewUrl' => $this->context->link->getAdminLink(self::TAB_CLASS) . '&ajax=1&action=previewBulkStatus',
                'bulkExecuteUrl' => $this->context->link->getAdminLink(self::TAB_CLASS) . '&ajax=1&action=executeBulkStatus',
                'orderStates' => OrderState::getOrderStates((int) $this->context->language->id),
                'maxBulk' => self::MAX_BULK_ORDERS,
                'canExport' => $canExport,
                'exportUrl' => $this->context->link->getAdminLink(self::TAB_CLASS) . '&ajax=1&action=exportSelectedOrders',
                'exportColumns' => $exportColumnOptions,
                'xlsxAvailable' => class_exists('ZipArchive'),
                'exportStorageKey' => 'wilden_manager_export_columns_' . (int) $this->context->employee->id,
                'documentPreviewUrl' => $this->context->link->getAdminLink(self::TAB_CLASS) . '&ajax=1&action=previewDocuments',
                'documentDownloadUrl' => $this->context->link->getAdminLink(self::TAB_CLASS) . '&ajax=1&action=downloadDocuments',
                'documentZipAvailable' => class_exists('ZipArchive'),
                'title' => $this->l('Configure order columns'),
                'button' => $this->l('Columns'),
                'save' => $this->l('Save and reload'),
                'reset' => $this->l('Restore defaults'),
                'cancel' => $this->l('Cancel'),
                'error' => $this->l('The column preference could not be saved.'),
                'bulkButton' => $this->l('Safe status change'),
                'bulkTitle' => $this->l('Bulk order status change'),
                'bulkState' => $this->l('New status'),
                'bulkSelectState' => $this->l('Select a status'),
                'bulkSendEmail' => $this->l('Send the status email to customers'),
                'bulkPreview' => $this->l('Preview changes'),
                'bulkExecute' => $this->l('Apply changes'),
                'bulkReload' => $this->l('Reload orders'),
                'bulkSelected' => $this->l('selected orders'),
                'bulkNoSelection' => $this->l('Select at least one order in the native list.'),
                'bulkTooMany' => $this->l('The maximum number of orders per operation is'),
                'bulkPreviewHelp' => $this->l('No changes are made during preview. Execution is enabled only for the exact previewed selection.'),
                'bulkPreviewReady' => $this->l('Preview ready. Review every order before applying the change.'),
                'bulkSuccess' => $this->l('Orders updated successfully'),
                'bulkSkipped' => $this->l('Already in the selected status'),
                'bulkWarnings' => $this->l('Warnings'),
                'bulkErrors' => $this->l('Errors'),
                'bulkError' => $this->l('The bulk operation could not be completed.'),
                'exportButton' => $this->l('Export selection'),
                'exportTitle' => $this->l('Export selected orders'),
                'exportFormat' => $this->l('File format'),
                'exportFields' => $this->l('Columns to export'),
                'exportDownload' => $this->l('Download export'),
                'exportNoColumns' => $this->l('Select at least one export column.'),
                'exportCsv' => $this->l('CSV (UTF-8, semicolon separated)'),
                'exportXlsx' => $this->l('Excel XLSX'),
                'documentButton' => $this->l('Documents'),
                'documentTitle' => $this->l('Combined order documents'),
                'documentType' => $this->l('Document type'),
                'documentPreview' => $this->l('Check documents'),
                'documentInvoice' => $this->l('Invoices PDF'),
                'documentDelivery' => $this->l('Delivery slips PDF'),
                'documentBoth' => $this->l('Both in ZIP'),
                'documentDownload' => $this->l('Download'),
                'documentAvailable' => $this->l('Available'),
                'documentMissing' => $this->l('Missing'),
                'documentReference' => $this->l('Reference'),
                'documentInvoicesCount' => $this->l('Invoices available'),
                'documentDeliveriesCount' => $this->l('Delivery slips available'),
                'documentError' => $this->l('The documents could not be checked or generated.'),
            ),
        ));
    }

    private function getVersionedAssetUrl($relativePath)
    {
        return $this->_path . ltrim((string) $relativePath, '/') . '?v=' . rawurlencode(self::VERSION);
    }

    private function getIntegrityJsConfiguration()
    {
        return array(
            'scanUrl' => $this->context->link->getAdminLink(self::TAB_CLASS) . '&ajax=1&action=integrityScan',
            'exportUrl' => $this->context->link->getAdminLink(self::TAB_CLASS) . '&ajax=1&action=exportIntegrity',
            'issueTypes' => $this->getIntegrityIssueTypes(),
            'issueSeverities' => array(
                'missing_history' => 'high',
                'state_history_mismatch' => 'high',
                'delivery_address_missing' => 'high',
                'invoice_address_missing' => 'high',
                'customer_missing' => 'high',
                'customer_incomplete' => 'medium',
                'invoice_number_without_date' => 'medium',
                'delivery_number_without_date' => 'medium',
                'delivery_address_deleted' => 'info',
                'invoice_address_deleted' => 'info',
                'customer_deleted' => 'info',
            ),
            'allIssues' => $this->l('All issue types'),
            'allSeverities' => $this->l('All severities'),
            'issue' => $this->l('Issue'),
            'severity' => $this->l('Severity'),
            'high' => $this->l('High'),
            'medium' => $this->l('Medium'),
            'info' => $this->l('Information'),
            'order' => $this->l('Order'),
            'reference' => $this->l('Reference'),
            'date' => $this->l('Date'),
            'detail' => $this->l('Detail'),
            'noIssues' => $this->l('No issues match the selected filters.'),
            'loading' => $this->l('Analysing orders...'),
            'error' => $this->l('The integrity analysis could not be completed.'),
            'previous' => $this->l('Previous'),
            'next' => $this->l('Next'),
            'page' => $this->l('Page'),
            'of' => $this->l('of'),
        );
    }

    private function getAuditJsConfiguration()
    {
        $idShop = (int) $this->context->shop->id;
        $labels = $this->getAuditActionLabels();
        $actions = array();
        foreach (WmAuditLogger::getActions($idShop) as $action) {
            $actions[] = array(
                'id' => $action,
                'label' => isset($labels[$action]) ? $labels[$action] : str_replace('_', ' ', $action),
            );
        }

        $employees = array();
        foreach (WmAuditLogger::getEmployees($idShop) as $employee) {
            $idEmployee = isset($employee['id_employee']) ? (int) $employee['id_employee'] : 0;
            $name = isset($employee['employee_name']) ? trim((string) $employee['employee_name']) : '';
            $employees[] = array(
                'id' => $idEmployee,
                'label' => $name !== '' ? $name : ($idEmployee ? $this->l('Employee') . ' #' . $idEmployee : $this->l('System')),
            );
        }

        return array(
            'url' => $this->context->link->getAdminLink(self::TAB_CLASS) . '&ajax=1&action=auditLog',
            'actions' => $actions,
            'employees' => $employees,
            'allActions' => $this->l('All actions'),
            'allEmployees' => $this->l('All employees'),
            'loading' => $this->l('Loading audit history...'),
            'empty' => $this->l('No audit records match the selected filters.'),
            'error' => $this->l('The audit history could not be loaded.'),
            'previous' => $this->l('Previous'),
            'next' => $this->l('Next'),
            'page' => $this->l('Page'),
            'of' => $this->l('of'),
            'system' => $this->l('System'),
            'details' => $this->l('Details'),
        );
    }

    private function installDatabase()
    {
        $queries = include __DIR__ . '/sql/install.php';

        foreach ($queries as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return WmProfilePermission::installDefaults((int) $this->context->language->id);
    }

    private function uninstallDatabase()
    {
        $queries = include __DIR__ . '/sql/uninstall.php';

        foreach ($queries as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    private function installTab()
    {
        if ((int) Tab::getIdFromClassName(self::TAB_CLASS)) {
            return true;
        }

        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = self::TAB_CLASS;
        $tab->module = $this->name;
        $tab->id_parent = (int) Tab::getIdFromClassName('AdminParentOrders');
        $tab->icon = 'manage_search';

        foreach (Language::getLanguages(false) as $language) {
            $tab->name[(int) $language['id_lang']] = 'Wilden Manager';
        }

        return (bool) $tab->add();
    }

    private function uninstallTab()
    {
        $idTab = (int) Tab::getIdFromClassName(self::TAB_CLASS);
        if (!$idTab) {
            return true;
        }

        return (bool) (new Tab($idTab))->delete();
    }
}
