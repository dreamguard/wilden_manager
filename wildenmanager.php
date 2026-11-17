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

require_once __DIR__ . '/classes/WmDb.php';
require_once __DIR__ . '/classes/WmSpreadsheet.php';
require_once __DIR__ . '/classes/WmAuditLogger.php';
require_once __DIR__ . '/classes/WmOrderNote.php';
require_once __DIR__ . '/classes/WmGridPreference.php';
require_once __DIR__ . '/classes/WmOrderRepository.php';
require_once __DIR__ . '/classes/WmExportService.php';
require_once __DIR__ . '/classes/WmDocumentService.php';
require_once __DIR__ . '/classes/WmIntegrityService.php';
require_once __DIR__ . '/classes/WmStockIntegrityService.php';
require_once __DIR__ . '/classes/WmIntegrityReview.php';
require_once __DIR__ . '/classes/WmStockRepairService.php';
require_once __DIR__ . '/classes/WmProfilePermission.php';
require_once __DIR__ . '/classes/WmOrderDeletionService.php';
require_once __DIR__ . '/classes/WmOrderHistoryService.php';

class Wildenmanager extends Module
{
    const VERSION = '2.1.1';
    const TAB_CLASS = 'AdminWildenManagerOrders';
    const MAX_EXPORT_ORDERS = 1000;
    const MAX_DOCUMENT_ORDERS = 100;
    const MAX_INTEGRITY_EXPORT = 10000;
    const MAX_STOCK_INTEGRITY_EXPORT = 10000;

    public function getIntegrityIssueTypes()
    {
        return array(
            'missing_history' => $this->trans('Order without status history', array(), 'Modules.Wildenmanager.Admin'),
            'state_history_mismatch' => $this->trans('Current status differs from history', array(), 'Modules.Wildenmanager.Admin'),
            'delivery_address_missing' => $this->trans('Delivery address missing', array(), 'Modules.Wildenmanager.Admin'),
            'invoice_address_missing' => $this->trans('Invoice address missing', array(), 'Modules.Wildenmanager.Admin'),
            'customer_missing' => $this->trans('Customer missing', array(), 'Modules.Wildenmanager.Admin'),
            'customer_incomplete' => $this->trans('Customer data incomplete', array(), 'Modules.Wildenmanager.Admin'),
            'invoice_number_without_date' => $this->trans('Invoice number without date', array(), 'Modules.Wildenmanager.Admin'),
            'delivery_number_without_date' => $this->trans('Delivery number without date', array(), 'Modules.Wildenmanager.Admin'),
            'delivery_address_deleted' => $this->trans('Historical delivery address marked deleted', array(), 'Modules.Wildenmanager.Admin'),
            'invoice_address_deleted' => $this->trans('Historical invoice address marked deleted', array(), 'Modules.Wildenmanager.Admin'),
            'customer_deleted' => $this->trans('Historical customer marked deleted', array(), 'Modules.Wildenmanager.Admin'),
        );
    }

    public function getAuditActionLabels()
    {
        return array(
            'native_order_columns_updated' => $this->trans('Order columns updated', array(), 'Modules.Wildenmanager.Admin'),
            'orders_exported' => $this->trans('Orders exported', array(), 'Modules.Wildenmanager.Admin'),
            'documents_download_requested' => $this->trans('Order documents downloaded', array(), 'Modules.Wildenmanager.Admin'),
            'integrity_report_exported' => $this->trans('Integrity report exported', array(), 'Modules.Wildenmanager.Admin'),
            'stock_integrity_report_exported' => $this->trans('Stock diagnostic report exported', array(), 'Modules.Wildenmanager.Admin'),
            'integrity_review_updated' => $this->trans('Diagnostic review updated', array(), 'Modules.Wildenmanager.Admin'),
            'stock_integrity_cache_repaired' => $this->trans('Physical stock cache repaired', array(), 'Modules.Wildenmanager.Admin'),
            'profile_permissions_updated' => $this->trans('Profile permissions updated', array(), 'Modules.Wildenmanager.Admin'),
            'order_deleted_anonymously' => $this->trans('Order permanently deleted', array(), 'Modules.Wildenmanager.Admin'),
            'order_deletion_settings_updated' => $this->trans('Order deletion settings updated', array(), 'Modules.Wildenmanager.Admin'),
            'note_updated' => $this->trans('Internal note updated', array(), 'Modules.Wildenmanager.Admin'),
            'order_history_updated' => $this->trans('Order status entry edited', array(), 'Modules.Wildenmanager.Admin'),
            'order_history_deleted' => $this->trans('Order status entry deleted', array(), 'Modules.Wildenmanager.Admin'),
        );
    }

    public function getStockIntegrityIssueTypes()
    {
        return array(
            'refunded_exceeds_ordered' => $this->trans('Refunded quantity exceeds ordered quantity', array(), 'Modules.Wildenmanager.Admin'),
            'returned_exceeds_ordered' => $this->trans('Returned quantity exceeds ordered quantity', array(), 'Modules.Wildenmanager.Admin'),
            'reinjected_exceeds_ordered' => $this->trans('Reinjected quantity exceeds ordered quantity', array(), 'Modules.Wildenmanager.Admin'),
            'credit_slip_exceeds_ordered' => $this->trans('Credit-slip quantity exceeds ordered quantity', array(), 'Modules.Wildenmanager.Admin'),
            'return_request_exceeds_ordered' => $this->trans('Return-request quantity exceeds ordered quantity', array(), 'Modules.Wildenmanager.Admin'),
            'refund_without_credit_slip' => $this->trans('Refund counter is not covered by credit slips', array(), 'Modules.Wildenmanager.Admin'),
            'refund_not_reinjected' => $this->trans('Refund or return without equal stock reinjection', array(), 'Modules.Wildenmanager.Admin'),
            'cancelled_restock_evidence_missing' => $this->trans('Cancellation has no persistent restock evidence', array(), 'Modules.Wildenmanager.Admin'),
            'stock_cache_mismatch' => $this->trans('Available, reserved and physical stock do not reconcile', array(), 'Modules.Wildenmanager.Admin'),
            'pack_stock_cache_mismatch' => $this->trans('Pack stock cache does not reconcile', array(), 'Modules.Wildenmanager.Admin'),
        );
    }

    public function __construct()
    {
        $this->name = 'wildenmanager';
        $this->tab = 'administration';
        $this->version = self::VERSION;
        $this->author = 'Wilden Militaria S.L.';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = array('min' => '8.0.0', 'max' => '8.99.99');

        parent::__construct();

        $this->displayName = $this->trans('Wilden Manager', array(), 'Modules.Wildenmanager.Admin');
        $this->description = $this->trans('Advanced and auditable order management for PrestaShop.', array(), 'Modules.Wildenmanager.Admin');
        $this->confirmUninstall = $this->trans('Remove Wilden Manager and all of its module data and audit records?', array(), 'Modules.Wildenmanager.Admin');
    }

    /**
     * Texts use the Modules.Wildenmanager.Admin domain; translations live in translations/<locale>/.
     */
    public function isUsingNewTranslationSystem()
    {
        return true;
    }

    public function install()
    {
        return parent::install()
            && $this->installDatabase()
            && $this->installTab()
            && $this->registerHook('actionOrderGridDefinitionModifier')
            && $this->registerHook('actionOrderGridQueryBuilderModifier')
            && $this->registerHook('actionGetAdminOrderButtons')
            && $this->registerHook('displayBackOfficeHeader')
            && Configuration::updateGlobalValue(WmOrderDeletionService::CONFIG_REINJECT_STOCK, 0);
    }

    public function uninstall()
    {
        return parent::uninstall()
            && $this->uninstallTab()
            && $this->uninstallDatabase()
            && Configuration::deleteByName(WmOrderDeletionService::CONFIG_REINJECT_STOCK);
    }

    public function getContent()
    {
        $isSuperAdmin = $this->isCurrentEmployeeSuperAdmin();
        $canViewDiagnostics = $this->canCurrentEmployeeViewDiagnostics();
        $messages = '';

        if (Tools::isSubmit('submitWmProfilePermissions')) {
            if (!$isSuperAdmin) {
                $messages .= $this->displayError($this->trans('Only SuperAdmin can change profile permissions.', array(), 'Modules.Wildenmanager.Admin'));
            } elseif (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
                $messages .= $this->displayError($this->trans('This action requires a POST request.', array(), 'Modules.Wildenmanager.Admin'));
            } elseif (WmProfilePermission::save(
                array(
                    'can_export' => (array) Tools::getValue('wm_export_profiles', array()),
                    'can_view_diagnostics' => (array) Tools::getValue('wm_diagnostic_profiles', array()),
                    'can_edit_history' => (array) Tools::getValue('wm_history_profiles', array()),
                ),
                (int) $this->context->language->id
            )) {
                WmAuditLogger::log('profile_permissions_updated');
                $messages .= $this->displayConfirmation($this->trans('Profile permissions have been saved.', array(), 'Modules.Wildenmanager.Admin'));
            } else {
                $messages .= $this->displayError($this->trans('Profile permissions could not be saved.', array(), 'Modules.Wildenmanager.Admin'));
            }
        }
        if (Tools::isSubmit('submitWmDeletionSettings')) {
            if (!$isSuperAdmin) {
                $messages .= $this->displayError($this->trans('Only SuperAdmin can change order deletion settings.', array(), 'Modules.Wildenmanager.Admin'));
            } elseif (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
                $messages .= $this->displayError($this->trans('This action requires a POST request.', array(), 'Modules.Wildenmanager.Admin'));
            } else {
                $previous = WmOrderDeletionService::isStockReinjectionEnabled();
                $enabled = (bool) Tools::getValue('wm_delete_reinject_stock');
                if (Configuration::updateGlobalValue(WmOrderDeletionService::CONFIG_REINJECT_STOCK, $enabled ? 1 : 0)) {
                    if ($previous !== $enabled) {
                        WmAuditLogger::log('order_deletion_settings_updated', array(
                            'reinject_stock_before' => $previous,
                            'reinject_stock_after' => $enabled,
                        ));
                    }
                    $messages .= $this->displayConfirmation($this->trans('Order deletion settings have been saved.', array(), 'Modules.Wildenmanager.Admin'));
                } else {
                    $messages .= $this->displayError($this->trans('Order deletion settings could not be saved.', array(), 'Modules.Wildenmanager.Admin'));
                }
            }
        }
        $this->context->controller->addCSS($this->getVersionedAssetUrl('views/css/configuration.css'));
        if ($canViewDiagnostics) {
            $this->context->controller->addJS($this->getVersionedAssetUrl('views/js/configuration.js'));
            $jsConfiguration = $this->getIntegrityJsConfiguration();
            $jsConfiguration['audit'] = $this->getAuditJsConfiguration();
            $jsConfiguration['stock'] = $this->getStockIntegrityJsConfiguration();
            Media::addJsDef(array('wildenManagerConfiguration' => $jsConfiguration));
        }

        $this->context->smarty->assign(array(
            'wm_module_version' => self::VERSION,
            'wm_orders_url' => $this->context->link->getAdminLink('AdminOrders'),
            'wm_can_view_diagnostics' => $canViewDiagnostics,
            'wm_is_super_admin' => $isSuperAdmin,
            'wm_delete_reinject_stock' => WmOrderDeletionService::isStockReinjectionEnabled(),
            'wm_stock_management_enabled' => (bool) Configuration::get('PS_STOCK_MANAGEMENT'),
            'wm_profile_permissions' => $isSuperAdmin
                ? WmProfilePermission::getProfiles((int) $this->context->language->id)
                : array(),
            'wm_permissions_action' => $this->context->link->getAdminLink('AdminModules', true, array(), array(
                'configure' => $this->name,
            )) . '#wm-admin',
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

    public function canCurrentEmployeeEditHistory()
    {
        return isset($this->context->employee)
            && WmProfilePermission::canEditHistory((int) $this->context->employee->id_profile);
    }

    public function canCurrentEmployeeViewDiagnostics()
    {
        return isset($this->context->employee)
            && WmProfilePermission::canViewDiagnostics((int) $this->context->employee->id_profile);
    }

    public function getNativeOrderColumns()
    {
        $columns = array(
            'id_order' => $this->trans('ID', array(), 'Modules.Wildenmanager.Admin'),
            'reference' => $this->trans('Reference', array(), 'Modules.Wildenmanager.Admin'),
            'new' => $this->trans('New client', array(), 'Modules.Wildenmanager.Admin'),
            'country_name' => $this->trans('Delivery', array(), 'Modules.Wildenmanager.Admin'),
            'customer' => $this->trans('Customer', array(), 'Modules.Wildenmanager.Admin'),
            'customer_email' => $this->trans('Customer email', array(), 'Modules.Wildenmanager.Admin'),
        );

        if ((bool) Configuration::get('PS_B2B_ENABLE')) {
            $columns['company'] = $this->trans('Company', array(), 'Modules.Wildenmanager.Admin');
        }

        $columns['total_paid_tax_incl'] = $this->trans('Total', array(), 'Modules.Wildenmanager.Admin');
        $columns['payment'] = $this->trans('Payment', array(), 'Modules.Wildenmanager.Admin');
        $columns['shipping_method'] = $this->trans('Shipping method', array(), 'Modules.Wildenmanager.Admin');
        $columns['osname'] = $this->trans('Status', array(), 'Modules.Wildenmanager.Admin');
        $columns['date_add'] = $this->trans('Date', array(), 'Modules.Wildenmanager.Admin');

        if (Shop::isFeatureActive()) {
            $columns['shop_name'] = $this->trans('Store', array(), 'Modules.Wildenmanager.Admin');
        }

        return $columns;
    }

    public function getExportColumns()
    {
        return array(
            'id_order' => $this->trans('ID', array(), 'Modules.Wildenmanager.Admin'),
            'reference' => $this->trans('Reference', array(), 'Modules.Wildenmanager.Admin'),
            'date_add' => $this->trans('Date', array(), 'Modules.Wildenmanager.Admin'),
            'customer' => $this->trans('Customer', array(), 'Modules.Wildenmanager.Admin'),
            'email' => $this->trans('Customer email', array(), 'Modules.Wildenmanager.Admin'),
            'company' => $this->trans('Company', array(), 'Modules.Wildenmanager.Admin'),
            'country_name' => $this->trans('Delivery country', array(), 'Modules.Wildenmanager.Admin'),
            'delivery_postcode' => $this->trans('Delivery postcode', array(), 'Modules.Wildenmanager.Admin'),
            'delivery_city' => $this->trans('Delivery city', array(), 'Modules.Wildenmanager.Admin'),
            'total_paid_tax_incl' => $this->trans('Total paid tax included', array(), 'Modules.Wildenmanager.Admin'),
            'currency_iso' => $this->trans('Currency', array(), 'Modules.Wildenmanager.Admin'),
            'payment' => $this->trans('Payment', array(), 'Modules.Wildenmanager.Admin'),
            'state_name' => $this->trans('Status', array(), 'Modules.Wildenmanager.Admin'),
            'carrier_name' => $this->trans('Shipping method', array(), 'Modules.Wildenmanager.Admin'),
            'shop_name' => $this->trans('Store', array(), 'Modules.Wildenmanager.Admin'),
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

        $definition->getGridActions()->add(
            (new \PrestaShop\PrestaShop\Core\Grid\Action\Type\SimpleGridAction('wm_columns'))
                ->setName($this->trans('Columns', array(), 'Modules.Wildenmanager.Admin'))
                ->setIcon('view_column')
        );

        $bulkActions = $definition->getBulkActions();
        if ($this->canCurrentEmployeeExport()) {
            $bulkActions->add(
                (new \PrestaShop\PrestaShop\Core\Grid\Action\Bulk\Type\ButtonBulkAction('wm_export'))
                    ->setName($this->trans('Export selection', array(), 'Modules.Wildenmanager.Admin'))
                    ->setIcon('download')
                    ->setOptions(array('class' => 'wm-export-button'))
            );
        }
        $bulkActions->add(
            (new \PrestaShop\PrestaShop\Core\Grid\Action\Bulk\Type\ButtonBulkAction('wm_documents'))
                ->setName($this->trans('Documents', array(), 'Modules.Wildenmanager.Admin'))
                ->setIcon('picture_as_pdf')
                ->setOptions(array('class' => 'wm-document-button'))
        );

        $columns->addAfter(
            'customer',
            (new \PrestaShop\PrestaShop\Core\Grid\Column\Type\DataColumn('customer_email'))
                ->setName($this->trans('Customer email', array(), 'Modules.Wildenmanager.Admin'))
                ->setOptions(array('field' => 'customer_email'))
        );
        $columns->addAfter(
            'payment',
            (new \PrestaShop\PrestaShop\Core\Grid\Column\Type\DataColumn('shipping_method'))
                ->setName($this->trans('Shipping method', array(), 'Modules.Wildenmanager.Admin'))
                ->setOptions(array('field' => 'shipping_method'))
        );

        $filters->add(
            (new \PrestaShop\PrestaShop\Core\Grid\Filter\Filter(
                'customer_email',
                \Symfony\Component\Form\Extension\Core\Type\TextType::class
            ))
                ->setTypeOptions(array(
                    'required' => false,
                    'attr' => array('placeholder' => $this->trans('Search email', array(), 'Modules.Wildenmanager.Admin')),
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
                    'attr' => array('placeholder' => $this->trans('Search shipping method', array(), 'Modules.Wildenmanager.Admin')),
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

    public function hookActionGetAdminOrderButtons(array $params)
    {
        if (!$this->isCurrentEmployeeSuperAdmin()
            || empty($params['id_order'])
            || empty($params['actions_bar_buttons_collection'])
        ) {
            return;
        }

        $idOrder = (int) $params['id_order'];
        $inspection = (new WmOrderDeletionService())->inspect($idOrder, Shop::getContextListShopID());
        if (empty($inspection['exists'])) {
            return;
        }

        // The core template renders <a class="btn {class}" {attributes}>. A "class" key in the
        // attributes would be a second class attribute that browsers ignore, so every CSS class
        // goes in the first argument and the JS also binds to data-wm-delete-order.
        $attributes = array(
            'href' => '#',
            'data-wm-delete-order' => '1',
            'data-order-id' => $idOrder,
            'data-order-reference' => (string) $inspection['reference'],
        );
        $class = 'btn-danger wm-delete-order-trigger';
        if (empty($inspection['eligible'])) {
            // No "disabled" class: Bootstrap would block the click and the reason could not be shown.
            $class = 'btn-outline-secondary wm-delete-order-trigger';
            $attributes['aria-disabled'] = 'true';
            $attributes['title'] = !empty($inspection['has_credit_slip'])
                ? $this->trans('Orders with a credit slip cannot be deleted.', array(), 'Modules.Wildenmanager.Admin')
                : $this->trans('Orders with an issued invoice cannot be deleted.', array(), 'Modules.Wildenmanager.Admin');
        }

        $params['actions_bar_buttons_collection']->add(
            new \PrestaShop\PrestaShop\Core\Action\ActionsBarButton(
                $class,
                $attributes,
                $this->trans('Delete order permanently', array(), 'Modules.Wildenmanager.Admin')
            )
        );
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
        $value = '%' . WmDb::escapeLike(trim((string) $filters[$filterName])) . '%';
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
        // Order grid and order view only: skip the DB query, assets and JS strings elsewhere.
        if (!$this->isOrdersBackOfficePage()) {
            return;
        }

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
                'storageKey' => 'wildenmanager_order_columns_' . self::VERSION . '_' .
                    (int) $this->context->employee->id . '_' . (int) $this->context->shop->id,
                'saveUrl' => $this->context->link->getAdminLink(self::TAB_CLASS) . '&ajax=1&action=saveNativeColumns',
                'canExport' => $canExport,
                'exportUrl' => $this->context->link->getAdminLink(self::TAB_CLASS) . '&ajax=1&action=exportSelectedOrders',
                'exportColumns' => $exportColumnOptions,
                'xlsxAvailable' => class_exists('ZipArchive'),
                'exportStorageKey' => 'wildenmanager_export_columns_' . (int) $this->context->employee->id,
                'documentPreviewUrl' => $this->context->link->getAdminLink(self::TAB_CLASS) . '&ajax=1&action=previewDocuments',
                'documentDownloadUrl' => $this->context->link->getAdminLink(self::TAB_CLASS) . '&ajax=1&action=downloadDocuments',
                'documentZipAvailable' => class_exists('ZipArchive'),
                'maxDocuments' => self::MAX_DOCUMENT_ORDERS,
                'title' => $this->trans('Configure order columns', array(), 'Modules.Wildenmanager.Admin'),
                'save' => $this->trans('Save and reload', array(), 'Modules.Wildenmanager.Admin'),
                'reset' => $this->trans('Restore defaults', array(), 'Modules.Wildenmanager.Admin'),
                'cancel' => $this->trans('Cancel', array(), 'Modules.Wildenmanager.Admin'),
                'error' => $this->trans('The column preference could not be saved.', array(), 'Modules.Wildenmanager.Admin'),
                'exportTitle' => $this->trans('Export selected orders', array(), 'Modules.Wildenmanager.Admin'),
                'exportFormat' => $this->trans('File format', array(), 'Modules.Wildenmanager.Admin'),
                'exportFields' => $this->trans('Columns to export', array(), 'Modules.Wildenmanager.Admin'),
                'exportDownload' => $this->trans('Download export', array(), 'Modules.Wildenmanager.Admin'),
                'exportNoColumns' => $this->trans('Select at least one export column.', array(), 'Modules.Wildenmanager.Admin'),
                'exportCsv' => $this->trans('CSV (UTF-8, semicolon separated)', array(), 'Modules.Wildenmanager.Admin'),
                'exportXlsx' => $this->trans('Excel XLSX', array(), 'Modules.Wildenmanager.Admin'),
                'documentTitle' => $this->trans('Combined order documents', array(), 'Modules.Wildenmanager.Admin'),
                'documentType' => $this->trans('Document type', array(), 'Modules.Wildenmanager.Admin'),
                'documentPreview' => $this->trans('Check documents', array(), 'Modules.Wildenmanager.Admin'),
                'documentInvoice' => $this->trans('Invoices PDF', array(), 'Modules.Wildenmanager.Admin'),
                'documentDelivery' => $this->trans('Delivery slips PDF', array(), 'Modules.Wildenmanager.Admin'),
                'documentBoth' => $this->trans('Both in ZIP', array(), 'Modules.Wildenmanager.Admin'),
                'documentDownload' => $this->trans('Download', array(), 'Modules.Wildenmanager.Admin'),
                'documentAvailable' => $this->trans('Available', array(), 'Modules.Wildenmanager.Admin'),
                'documentMissing' => $this->trans('Missing', array(), 'Modules.Wildenmanager.Admin'),
                'documentReference' => $this->trans('Reference', array(), 'Modules.Wildenmanager.Admin'),
                'documentInvoicesCount' => $this->trans('Invoices available', array(), 'Modules.Wildenmanager.Admin'),
                'documentDeliveriesCount' => $this->trans('Delivery slips available', array(), 'Modules.Wildenmanager.Admin'),
                'documentError' => $this->trans('The documents could not be checked or generated.', array(), 'Modules.Wildenmanager.Admin'),
                'selectionRequired' => $this->trans('Select at least one order in the native list.', array(), 'Modules.Wildenmanager.Admin'),
                'selectedOrders' => $this->trans('selected orders', array(), 'Modules.Wildenmanager.Admin'),
                'tooManyDocuments' => $this->trans('The maximum number of orders per document operation is', array(), 'Modules.Wildenmanager.Admin'),
                'canDeleteOrders' => $this->isCurrentEmployeeSuperAdmin(),
                'deleteEligibilityUrl' => $this->context->link->getAdminLink(self::TAB_CLASS) . '&ajax=1&action=orderDeletionEligibility',
                'deleteUrl' => $this->context->link->getAdminLink(self::TAB_CLASS) . '&ajax=1&action=deleteOrderPermanently',
                'ordersUrl' => $this->context->link->getAdminLink('AdminOrders'),
                'deleteTitle' => $this->trans('Permanently delete order', array(), 'Modules.Wildenmanager.Admin'),
                'deleteWarning' => WmOrderDeletionService::isStockReinjectionEnabled()
                    ? $this->trans('This operation is irreversible. The order and its PrestaShop records will be removed. The ordered quantities will be returned to stock unless the order is cancelled or in error.', array(), 'Modules.Wildenmanager.Admin')
                    : $this->trans('This operation is irreversible. The order and its PrestaShop records will be removed. Stock will not be reinjected.', array(), 'Modules.Wildenmanager.Admin'),
                'deletePrompt' => $this->trans('Type the order ID to confirm:', array(), 'Modules.Wildenmanager.Admin'),
                'deleteConfirm' => $this->trans('Delete permanently', array(), 'Modules.Wildenmanager.Admin'),
                'deleteInvoiceBlocked' => $this->trans('This order has an issued invoice or a credit slip and cannot be deleted.', array(), 'Modules.Wildenmanager.Admin'),
                'deleteError' => $this->trans('The order could not be deleted.', array(), 'Modules.Wildenmanager.Admin'),
                'deleteSuccess' => $this->trans('The order and its PrestaShop records were deleted.', array(), 'Modules.Wildenmanager.Admin'),
                'deleteStockReinjected' => $this->trans('Units returned to stock:', array(), 'Modules.Wildenmanager.Admin'),
                'deleteStockAlready' => $this->trans('Stock was not changed because the order was already cancelled or in error.', array(), 'Modules.Wildenmanager.Admin'),
                'deleteStockDisabled' => $this->trans('Stock was not changed because stock management is disabled in the shop.', array(), 'Modules.Wildenmanager.Admin'),
                'deleteActionLabel' => $this->trans('Delete permanently', array(), 'Modules.Wildenmanager.Admin'),
                'canEditHistory' => $this->canCurrentEmployeeEditHistory(),
                'historyListUrl' => $this->context->link->getAdminLink(self::TAB_CLASS) . '&ajax=1&action=orderHistoryList',
                'historyUpdateUrl' => $this->context->link->getAdminLink(self::TAB_CLASS) . '&ajax=1&action=orderHistoryUpdate',
                'historyDeleteUrl' => $this->context->link->getAdminLink(self::TAB_CLASS) . '&ajax=1&action=orderHistoryDelete',
                'historyEdit' => $this->trans('Edit', array(), 'Modules.Wildenmanager.Admin'),
                'historyDelete' => $this->trans('Delete', array(), 'Modules.Wildenmanager.Admin'),
                'historyEditTitle' => $this->trans('Edit status entry', array(), 'Modules.Wildenmanager.Admin'),
                'historyEditHelp' => $this->trans('Only the record is corrected: no emails are sent and stock, invoices and payments are not changed.', array(), 'Modules.Wildenmanager.Admin'),
                'historyDate' => $this->trans('Date and time', array(), 'Modules.Wildenmanager.Admin'),
                'historyEmployee' => $this->trans('Employee', array(), 'Modules.Wildenmanager.Admin'),
                'historyNoEmployee' => $this->trans('No employee (customer or automatic)', array(), 'Modules.Wildenmanager.Admin'),
                'historyInactive' => $this->trans('inactive', array(), 'Modules.Wildenmanager.Admin'),
                'historySave' => $this->trans('Save', array(), 'Modules.Wildenmanager.Admin'),
                'historyDeleteConfirm' => $this->trans('Delete this status entry from the order history? The current order status will not change.', array(), 'Modules.Wildenmanager.Admin'),
                'historyLatestNoDelete' => $this->trans('The most recent status cannot be deleted.', array(), 'Modules.Wildenmanager.Admin'),
                'historyError' => $this->trans('The status history could not be updated.', array(), 'Modules.Wildenmanager.Admin'),
            ),
        ));
    }

    /**
     * True on the Symfony order pages (grid and view). Their legacy context controller
     * carries the legacy name "AdminOrders" in controller_name.
     */
    private function isOrdersBackOfficePage()
    {
        $controller = isset($this->context->controller) ? $this->context->controller : null;
        if (!$controller || empty($this->context->employee)) {
            return false;
        }
        $name = isset($controller->controller_name) ? (string) $controller->controller_name : '';

        return $name === 'AdminOrders';
    }

    /**
     * Cache-busts with the file modification time, so an asset uploaded without a version
     * change is still refreshed by browsers.
     */
    private function getVersionedAssetUrl($relativePath)
    {
        $relativePath = ltrim((string) $relativePath, '/');
        $file = $this->getLocalPath() . $relativePath;
        $version = is_file($file) ? (string) filemtime($file) : self::VERSION;

        return $this->_path . $relativePath . '?v=' . rawurlencode($version);
    }

    private function getIntegrityJsConfiguration()
    {
        return array(
            'scanUrl' => $this->context->link->getAdminLink(self::TAB_CLASS) . '&ajax=1&action=integrityScan',
            'exportUrl' => $this->context->link->getAdminLink(self::TAB_CLASS) . '&ajax=1&action=exportIntegrity',
            'reviewUrl' => $this->context->link->getAdminLink(self::TAB_CLASS) . '&ajax=1&action=updateIntegrityReview',
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
            'allIssues' => $this->trans('All issue types', array(), 'Modules.Wildenmanager.Admin'),
            'allSeverities' => $this->trans('All severities', array(), 'Modules.Wildenmanager.Admin'),
            'issue' => $this->trans('Issue', array(), 'Modules.Wildenmanager.Admin'),
            'severity' => $this->trans('Severity', array(), 'Modules.Wildenmanager.Admin'),
            'high' => $this->trans('High', array(), 'Modules.Wildenmanager.Admin'),
            'medium' => $this->trans('Medium', array(), 'Modules.Wildenmanager.Admin'),
            'info' => $this->trans('Information', array(), 'Modules.Wildenmanager.Admin'),
            'order' => $this->trans('Order', array(), 'Modules.Wildenmanager.Admin'),
            'reference' => $this->trans('Reference', array(), 'Modules.Wildenmanager.Admin'),
            'date' => $this->trans('Date', array(), 'Modules.Wildenmanager.Admin'),
            'detail' => $this->trans('Detail', array(), 'Modules.Wildenmanager.Admin'),
            'noIssues' => $this->trans('No issues match the selected filters.', array(), 'Modules.Wildenmanager.Admin'),
            'loading' => $this->trans('Analysing orders...', array(), 'Modules.Wildenmanager.Admin'),
            'error' => $this->trans('The integrity analysis could not be completed.', array(), 'Modules.Wildenmanager.Admin'),
            'previous' => $this->trans('Previous', array(), 'Modules.Wildenmanager.Admin'),
            'next' => $this->trans('Next', array(), 'Modules.Wildenmanager.Admin'),
            'page' => $this->trans('Page', array(), 'Modules.Wildenmanager.Admin'),
            'of' => $this->trans('of', array(), 'Modules.Wildenmanager.Admin'),
            'pendingReview' => $this->trans('Pending review', array(), 'Modules.Wildenmanager.Admin'),
            'reviewed' => $this->trans('Reviewed', array(), 'Modules.Wildenmanager.Admin'),
            'justified' => $this->trans('Justified', array(), 'Modules.Wildenmanager.Admin'),
            'confirmed' => $this->trans('Confirmed', array(), 'Modules.Wildenmanager.Admin'),
            'review' => $this->trans('Review', array(), 'Modules.Wildenmanager.Admin'),
            'reviewNote' => $this->trans('Justification or review note', array(), 'Modules.Wildenmanager.Admin'),
            'saveReview' => $this->trans('Save review', array(), 'Modules.Wildenmanager.Admin'),
            'reviewSaved' => $this->trans('The incident review has been saved.', array(), 'Modules.Wildenmanager.Admin'),
            'noteRequired' => $this->trans('A note is required for justified or confirmed incidents.', array(), 'Modules.Wildenmanager.Admin'),
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
                'label' => $name !== '' ? $name : ($idEmployee ? $this->trans('Employee', array(), 'Modules.Wildenmanager.Admin') . ' #' . $idEmployee : $this->trans('System', array(), 'Modules.Wildenmanager.Admin')),
            );
        }

        return array(
            'url' => $this->context->link->getAdminLink(self::TAB_CLASS) . '&ajax=1&action=auditLog',
            'actions' => $actions,
            'employees' => $employees,
            'allActions' => $this->trans('All actions', array(), 'Modules.Wildenmanager.Admin'),
            'allEmployees' => $this->trans('All employees', array(), 'Modules.Wildenmanager.Admin'),
            'loading' => $this->trans('Loading audit history...', array(), 'Modules.Wildenmanager.Admin'),
            'empty' => $this->trans('No audit records match the selected filters.', array(), 'Modules.Wildenmanager.Admin'),
            'error' => $this->trans('The audit history could not be loaded.', array(), 'Modules.Wildenmanager.Admin'),
            'previous' => $this->trans('Previous', array(), 'Modules.Wildenmanager.Admin'),
            'next' => $this->trans('Next', array(), 'Modules.Wildenmanager.Admin'),
            'page' => $this->trans('Page', array(), 'Modules.Wildenmanager.Admin'),
            'of' => $this->trans('of', array(), 'Modules.Wildenmanager.Admin'),
            'system' => $this->trans('System', array(), 'Modules.Wildenmanager.Admin'),
            'details' => $this->trans('Details', array(), 'Modules.Wildenmanager.Admin'),
        );
    }

    private function getStockIntegrityJsConfiguration()
    {
        return array(
            'scanUrl' => $this->context->link->getAdminLink(self::TAB_CLASS) . '&ajax=1&action=stockIntegrityScan',
            'exportUrl' => $this->context->link->getAdminLink(self::TAB_CLASS) . '&ajax=1&action=exportStockIntegrity',
            'reviewUrl' => $this->context->link->getAdminLink(self::TAB_CLASS) . '&ajax=1&action=updateIntegrityReview',
            'repairUrl' => $this->context->link->getAdminLink(self::TAB_CLASS) . '&ajax=1&action=repairStockIntegrity',
            'isSuperAdmin' => $this->isCurrentEmployeeSuperAdmin(),
            'issueTypes' => $this->getStockIntegrityIssueTypes(),
            'issueSeverities' => array(
                'refunded_exceeds_ordered' => 'high',
                'returned_exceeds_ordered' => 'high',
                'reinjected_exceeds_ordered' => 'high',
                'credit_slip_exceeds_ordered' => 'high',
                'return_request_exceeds_ordered' => 'high',
                'refund_without_credit_slip' => 'info',
                'refund_not_reinjected' => 'info',
                'cancelled_restock_evidence_missing' => 'info',
                'stock_cache_mismatch' => 'medium',
                'pack_stock_cache_mismatch' => 'info',
            ),
            'allIssues' => $this->trans('All stock issue types', array(), 'Modules.Wildenmanager.Admin'),
            'allSeverities' => $this->trans('All severities', array(), 'Modules.Wildenmanager.Admin'),
            'allKinds' => $this->trans('All product types', array(), 'Modules.Wildenmanager.Admin'),
            'high' => $this->trans('High', array(), 'Modules.Wildenmanager.Admin'),
            'medium' => $this->trans('Medium', array(), 'Modules.Wildenmanager.Admin'),
            'info' => $this->trans('Information', array(), 'Modules.Wildenmanager.Admin'),
            'standard' => $this->trans('Standard', array(), 'Modules.Wildenmanager.Admin'),
            'pack' => $this->trans('Pack', array(), 'Modules.Wildenmanager.Admin'),
            'custom' => $this->trans('Custom product', array(), 'Modules.Wildenmanager.Admin'),
            'orderLevel' => $this->trans('Order-level', array(), 'Modules.Wildenmanager.Admin'),
            'noIssues' => $this->trans('No stock issues match the selected filters.', array(), 'Modules.Wildenmanager.Admin'),
            'loading' => $this->trans('Analysing stock, cancellations and refunds...', array(), 'Modules.Wildenmanager.Admin'),
            'error' => $this->trans('The stock analysis could not be completed.', array(), 'Modules.Wildenmanager.Admin'),
            'previous' => $this->trans('Previous', array(), 'Modules.Wildenmanager.Admin'),
            'next' => $this->trans('Next', array(), 'Modules.Wildenmanager.Admin'),
            'page' => $this->trans('Page', array(), 'Modules.Wildenmanager.Admin'),
            'of' => $this->trans('of', array(), 'Modules.Wildenmanager.Admin'),
            'pendingReview' => $this->trans('Pending review', array(), 'Modules.Wildenmanager.Admin'),
            'reviewed' => $this->trans('Reviewed', array(), 'Modules.Wildenmanager.Admin'),
            'justified' => $this->trans('Justified', array(), 'Modules.Wildenmanager.Admin'),
            'confirmed' => $this->trans('Confirmed', array(), 'Modules.Wildenmanager.Admin'),
            'review' => $this->trans('Review', array(), 'Modules.Wildenmanager.Admin'),
            'reviewNote' => $this->trans('Justification or review note', array(), 'Modules.Wildenmanager.Admin'),
            'saveReview' => $this->trans('Save review', array(), 'Modules.Wildenmanager.Admin'),
            'reviewSaved' => $this->trans('The incident review has been saved.', array(), 'Modules.Wildenmanager.Admin'),
            'noteRequired' => $this->trans('A note is required for justified or confirmed incidents.', array(), 'Modules.Wildenmanager.Admin'),
            'repair' => $this->trans('Repair physical cache', array(), 'Modules.Wildenmanager.Admin'),
            'repairConfirm' => $this->trans('Repair only this physical stock cache value? Sellable and reserved quantities will remain unchanged.', array(), 'Modules.Wildenmanager.Admin'),
            'repairDone' => $this->trans('The physical stock cache was repaired and verified.', array(), 'Modules.Wildenmanager.Admin'),
            'safeRepair' => $this->trans('Safely repairable', array(), 'Modules.Wildenmanager.Admin'),
            'needsReview' => $this->trans('Requires review', array(), 'Modules.Wildenmanager.Admin'),
            'doNotRepair' => $this->trans('Do not repair', array(), 'Modules.Wildenmanager.Admin'),
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
