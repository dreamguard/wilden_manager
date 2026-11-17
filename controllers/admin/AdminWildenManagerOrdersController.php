<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminWildenManagerOrdersController extends ModuleAdminController
{
    private $repository;

    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->repository = new WmOrderRepository($this->context);
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);
        $this->addCSS($this->module->getPathUri() . 'views/css/admin-orders.css');
        $this->addJS($this->module->getPathUri() . 'views/js/admin-orders.js');
    }

    public function initPageHeaderToolbar()
    {
        $this->page_header_toolbar_title = $this->module->displayName;
        parent::initPageHeaderToolbar();
    }

    public function postProcess()
    {
        $mutatingActions = array(
            'submitWmSaveNote',
        );
        foreach ($mutatingActions as $action) {
            if (Tools::isSubmit($action) && (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST')) {
                $this->errors[] = $this->trans('This action requires a POST request.', array(), 'Modules.Wildenmanager.Admin');
                return;
            }
        }

        if (Tools::isSubmit('exportWmOrders')) {
            $this->exportOrders();
        }

        if (Tools::isSubmit('submitWmSaveNote')) {
            $this->saveNote();
        }
        parent::postProcess();
    }

    public function initContent()
    {
        if (!Tools::getValue('ajax')) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminOrders'));
        }

        parent::initContent();

        $filters = $this->getFilters();
        $columns = $this->getSelectedColumns();

        $page = max(1, (int) Tools::getValue('page', 1));
        $limit = max(10, min(100, (int) Tools::getValue('limit', 25)));
        $sort = (string) Tools::getValue('sort', 'date_add');
        $direction = strtoupper((string) Tools::getValue('direction', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $orders = $this->repository->getOrders($filters, $page, $limit, $sort, $direction);
        $total = $this->repository->countOrders($filters);

        foreach ($orders as &$order) {
            if (!preg_match('/^#[0-9a-f]{6}$/i', (string) $order['state_color'])) {
                $order['state_color'] = '#6c757d';
            }
            $order['formatted_total'] = Tools::displayPrice(
                (float) $order['total_paid_tax_incl'],
                new Currency((int) $order['id_currency'])
            );
            $order['formatted_date'] = Tools::displayDate($order['date_add'], true);
            $order['view_url'] = $this->getOrderViewUrl((int) $order['id_order']);
            $order['customer_url'] = $this->context->link->getAdminLink('AdminCustomers') .
                '&viewcustomer&id_customer=' . (int) $order['id_customer'];
            $order['invoice_url'] = $this->context->link->getAdminLink('AdminPdf') .
                '&submitAction=generateInvoicePDF&id_order=' . (int) $order['id_order'];
            $order['delivery_slip_url'] = $this->context->link->getAdminLink('AdminPdf') .
                '&submitAction=generateDeliverySlipPDF&id_order=' . (int) $order['id_order'];
        }
        unset($order);

        $baseUrl = $this->context->link->getAdminLink(Wildenmanager::TAB_CLASS);
        $this->context->smarty->assign(array(
            'wm_base_url' => $baseUrl,
            'wm_ajax_url' => $baseUrl . '&ajax=1',
            'wm_orders' => $orders,
            'wm_filters' => $filters,
            'wm_columns' => $columns,
            'wm_available_columns' => $this->getAvailableColumns(),
            'wm_order_states' => OrderState::getOrderStates((int) $this->context->language->id),
            'wm_total' => $total,
            'wm_page' => $page,
            'wm_limit' => $limit,
            'wm_limits' => array(10, 25, 50, 100),
            'wm_total_pages' => max(1, (int) ceil($total / $limit)),
            'wm_sort' => $sort,
            'wm_direction' => $direction,
            'wm_query' => $this->buildPersistentQuery($filters, $columns, $limit, $sort, $direction),
            'wm_selected_order_ids' => array_map('intval', (array) Tools::getValue('order_ids', array())),
        ));

        $this->setTemplate('orders.tpl');
    }

    public function ajaxProcessQuickView()
    {
        $idOrder = (int) Tools::getValue('id_order');
        if (!$idOrder || !$this->repository->isOrderAccessible($idOrder)) {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => 'Order not found.')));
        }

        $order = new Order($idOrder);
        $customer = new Customer((int) $order->id_customer);
        $currency = new Currency((int) $order->id_currency);
        $invoiceAddress = new Address((int) $order->id_address_invoice);
        $deliveryAddress = new Address((int) $order->id_address_delivery);

        $products = $order->getProducts();
        foreach ($products as &$product) {
            $product['formatted_unit_price'] = Tools::displayPrice((float) $product['unit_price_tax_incl'], $currency);
            $product['formatted_total_price'] = Tools::displayPrice((float) $product['total_price_tax_incl'], $currency);
        }
        unset($product);

        $this->context->smarty->assign(array(
            'wm_qv_order' => $order,
            'wm_qv_customer' => $customer,
            'wm_qv_currency' => $currency,
            'wm_qv_products' => $products,
            'wm_qv_history' => $order->getHistory((int) $this->context->language->id),
            'wm_qv_invoice_address' => $this->formatAddressText($invoiceAddress),
            'wm_qv_delivery_address' => $this->formatAddressText($deliveryAddress),
            'wm_qv_note' => WmOrderNote::get($idOrder),
            'wm_qv_total' => Tools::displayPrice((float) $order->total_paid_tax_incl, $currency),
            'wm_qv_view_url' => $this->getOrderViewUrl($idOrder),
            'wm_can_edit' => $this->canEdit(),
        ));

        $html = $this->context->smarty->fetch($this->module->getLocalPath() . 'views/templates/admin/quick-view.tpl');
        $this->ajaxDie(json_encode(array('success' => true, 'html' => $html)));
    }

    public function ajaxProcessSaveNativeColumns()
    {
        $available = array_keys($this->module->getNativeOrderColumns());
        $requested = Tools::getValue('columns', array());
        $requested = is_array($requested) ? $requested : array();
        $columns = array_values(array_unique(array_intersect($requested, $available)));

        if (!$columns) {
            $this->ajaxDie(json_encode(array(
                'success' => false,
                'error' => $this->trans('Select at least one order column.', array(), 'Modules.Wildenmanager.Admin'),
            )));
        }

        if (!WmGridPreference::save(
            $columns,
            (int) $this->context->employee->id,
            (int) $this->context->shop->id
        )) {
            $this->ajaxDie(json_encode(array(
                'success' => false,
                'error' => $this->trans('The column preference could not be saved.', array(), 'Modules.Wildenmanager.Admin'),
            )));
        }

        $storedColumns = WmGridPreference::get(
            (int) $this->context->employee->id,
            (int) $this->context->shop->id
        );
        if ($storedColumns !== $columns) {
            $this->ajaxDie(json_encode(array(
                'success' => false,
                'error' => $this->trans('The saved column preference could not be verified.', array(), 'Modules.Wildenmanager.Admin'),
            )));
        }

        WmAuditLogger::log('native_order_columns_updated', array('columns' => $columns));
        $this->ajaxDie(json_encode(array('success' => true, 'columns' => $storedColumns)));
    }

    public function ajaxProcessOrderDeletionEligibility()
    {
        if (!$this->module->isCurrentEmployeeSuperAdmin()) {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => 'Access denied.')));
        }

        $ids = array_slice(array_values(array_unique(array_filter(array_map(
            'intval',
            (array) Tools::getValue('order_ids', array())
        )))), 0, 100);
        $service = new WmOrderDeletionService();

        try {
            $this->ajaxDie(json_encode(array(
                'success' => true,
                'orders' => $service->inspectMany($ids, Shop::getContextListShopID()),
            ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } catch (Exception $exception) {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => $exception->getMessage())));
        }
    }

    public function ajaxProcessDeleteOrderPermanently()
    {
        if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => 'POST required.')));
        }
        if (!$this->module->isCurrentEmployeeSuperAdmin()) {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => 'Only SuperAdmin can delete an order.')));
        }

        $idOrder = (int) Tools::getValue('id_order');
        $confirmation = trim((string) Tools::getValue('confirmation'));
        if ($idOrder <= 0 || !hash_equals((string) $idOrder, $confirmation)) {
            $this->ajaxDie(json_encode(array(
                'success' => false,
                'error' => 'The confirmation does not match the order ID.',
            )));
        }

        try {
            $result = (new WmOrderDeletionService())->delete(
                $idOrder,
                Shop::getContextListShopID(),
                WmOrderDeletionService::isStockReinjectionEnabled()
            );
            WmAuditLogger::log('order_deleted_anonymously', array(
                'id_shop' => (int) $result['id_shop'],
                'stock_action' => (string) $result['stock_action'],
                'stock_reinjected' => (bool) $result['stock_reinjected'],
                'stock_units_reinjected' => (int) $result['stock_units_reinjected'],
                'order_identifier_retained' => false,
            ));
            $this->ajaxDie(json_encode(array(
                'success' => true,
                'stock_action' => (string) $result['stock_action'],
                'stock_units_reinjected' => (int) $result['stock_units_reinjected'],
            ), JSON_UNESCAPED_UNICODE));
        } catch (Exception $exception) {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => $this->translateServiceError($exception))));
        } catch (Throwable $throwable) {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => $this->translateServiceError($throwable))));
        }
    }

    public function ajaxProcessOrderHistoryList()
    {
        if (!$this->module->canCurrentEmployeeEditHistory()) {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => $this->trans('Access denied.', array(), 'Modules.Wildenmanager.Admin'))));
        }
        try {
            $history = (new WmOrderHistoryService())->getHistory(
                (int) Tools::getValue('id_order'),
                Shop::getContextListShopID(),
                (int) $this->context->language->id
            );
            $this->ajaxDie(json_encode(array('success' => true, 'history' => $history), JSON_UNESCAPED_UNICODE));
        } catch (Exception $exception) {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => $this->translateServiceError($exception))));
        }
    }

    public function ajaxProcessOrderHistoryUpdate()
    {
        $this->assertHistoryWriteRequest();
        $idOrder = (int) Tools::getValue('id_order');
        try {
            $result = (new WmOrderHistoryService())->update(
                $idOrder,
                (int) Tools::getValue('id_order_history'),
                (string) Tools::getValue('date_add'),
                (int) Tools::getValue('id_employee'),
                Shop::getContextListShopID()
            );
            WmAuditLogger::log('order_history_updated', $result, $idOrder);
            $this->ajaxDie(json_encode(array('success' => true), JSON_UNESCAPED_UNICODE));
        } catch (Exception $exception) {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => $this->translateServiceError($exception))));
        } catch (Throwable $throwable) {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => $this->translateServiceError($throwable))));
        }
    }

    public function ajaxProcessOrderHistoryDelete()
    {
        $this->assertHistoryWriteRequest();
        $idOrder = (int) Tools::getValue('id_order');
        try {
            $result = (new WmOrderHistoryService())->delete(
                $idOrder,
                (int) Tools::getValue('id_order_history'),
                Shop::getContextListShopID()
            );
            WmAuditLogger::log('order_history_deleted', $result, $idOrder);
            $this->ajaxDie(json_encode(array('success' => true), JSON_UNESCAPED_UNICODE));
        } catch (Exception $exception) {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => $this->translateServiceError($exception))));
        } catch (Throwable $throwable) {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => $this->translateServiceError($throwable))));
        }
    }

    private function assertHistoryWriteRequest()
    {
        if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => $this->trans('This action requires a POST request.', array(), 'Modules.Wildenmanager.Admin'))));
        }
        if (!$this->module->canCurrentEmployeeEditHistory()) {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => $this->trans('Access denied.', array(), 'Modules.Wildenmanager.Admin'))));
        }
    }

    /**
     * Service exceptions carry English messages; they are listed in the module's
     * translation catalogue, so the translator returns the localized text when available.
     */
    private function translateServiceError($throwable)
    {
        return $this->trans((string) $throwable->getMessage(), array(), 'Modules.Wildenmanager.Admin');
    }

    public function ajaxProcessExportSelectedOrders()
    {
        if (!$this->access('view') || !$this->module->canCurrentEmployeeExport()) {
            $this->ajaxDie(json_encode(array(
                'success' => false,
                'error' => $this->trans('You do not have permission to export orders.', array(), 'Modules.Wildenmanager.Admin'),
            )));
        }

        $ids = array_values(array_unique(array_filter(array_map(
            'intval',
            (array) Tools::getValue('order_ids', array())
        ))));
        $available = $this->module->getExportColumns();
        $columns = array_values(array_unique(array_intersect(
            (array) Tools::getValue('export_columns', array()),
            array_keys($available)
        )));
        $format = strtolower((string) Tools::getValue('export_format', 'csv'));

        if (!$ids || count($ids) > Wildenmanager::MAX_EXPORT_ORDERS || !$columns) {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => 'Invalid export selection.')));
        }
        if (!in_array($format, array('csv', 'xlsx'), true)) {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => 'Invalid export format.')));
        }
        if ($format === 'xlsx' && !class_exists('ZipArchive')) {
            $this->ajaxDie(json_encode(array(
                'success' => false,
                'error' => 'XLSX export requires the PHP Zip extension.',
            )));
        }

        $orders = $this->repository->getOrdersByIds($ids, Wildenmanager::MAX_EXPORT_ORDERS);
        if (count($orders) !== count($ids)) {
            $this->ajaxDie(json_encode(array(
                'success' => false,
                'error' => 'One or more selected orders are not accessible.',
            )));
        }

        WmAuditLogger::log('orders_exported', array(
            'format' => $format,
            'columns' => $columns,
            'order_ids' => $ids,
        ));

        $service = new WmExportService();
        $service->download($orders, $columns, $available, $format);
    }

    public function ajaxProcessPreviewDocuments()
    {
        if (!$this->access('view')) {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => 'Access denied.')));
        }

        try {
            $service = new WmDocumentService($this->context, $this->repository);
            $preview = $service->preview((array) Tools::getValue('order_ids', array()));
            $this->ajaxDie(json_encode(array('success' => true, 'preview' => $preview)));
        } catch (Exception $exception) {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => $exception->getMessage())));
        }
    }

    public function ajaxProcessDownloadDocuments()
    {
        if (!$this->access('view')) {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => 'Access denied.')));
        }

        $type = strtolower((string) Tools::getValue('document_type'));
        if (!in_array($type, array('invoice', 'delivery', 'both'), true)) {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => 'Invalid document type.')));
        }

        try {
            $service = new WmDocumentService($this->context, $this->repository);
            $ids = (array) Tools::getValue('order_ids', array());
            $result = $service->getDocuments($ids);
            WmAuditLogger::log('documents_download_requested', array(
                'type' => $type,
                'order_ids' => $result['order_ids'],
                'invoice_count' => count($result['invoices']),
                'delivery_count' => count($result['delivery_slips']),
            ));
            $service->download($result, $type);
        } catch (Exception $exception) {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => $exception->getMessage())));
        }
    }

    public function ajaxProcessIntegrityScan()
    {
        if (!$this->access('view') || !$this->module->canCurrentEmployeeViewDiagnostics()) {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => 'Access denied.')));
        }

        try {
            $service = new WmIntegrityService($this->context);
            $report = $service->scan(
                (string) Tools::getValue('issue_type'),
                (string) Tools::getValue('severity'),
                (int) Tools::getValue('page', 1),
                (int) Tools::getValue('limit', 50)
            );
            $report['issues'] = $this->prepareIntegrityIssues($report['issues']);
            $this->ajaxDie(json_encode(array('success' => true, 'report' => $report)));
        } catch (Exception $exception) {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => $exception->getMessage())));
        }
    }

    public function ajaxProcessStockIntegrityScan()
    {
        if (!$this->access('view') || !$this->module->canCurrentEmployeeViewDiagnostics()) {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => 'Access denied.')));
        }

        try {
            $service = new WmStockIntegrityService($this->context);
            $report = $service->scan(
                (string) Tools::getValue('issue_type'),
                (string) Tools::getValue('severity'),
                (string) Tools::getValue('product_kind'),
                (int) Tools::getValue('page', 1),
                (int) Tools::getValue('limit', 50)
            );
            $report['issues'] = $this->prepareStockIntegrityIssues($report['issues']);
            $this->ajaxDie(json_encode(
                array('success' => true, 'report' => $report),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ));
        } catch (Exception $exception) {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => $exception->getMessage())));
        }
    }

    public function ajaxProcessUpdateIntegrityReview()
    {
        if (!$this->access('view') || !$this->module->canCurrentEmployeeViewDiagnostics()) {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => 'Access denied.')));
        }
        if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => 'POST required.')));
        }

        try {
            $idShop = (int) Tools::getValue('id_shop');
            $allowedShopIds = array_map('intval', Shop::getContextListShopID());
            if (!$allowedShopIds && isset($this->context->shop->id)) {
                $allowedShopIds = array((int) $this->context->shop->id);
            }
            if (!in_array($idShop, $allowedShopIds, true)) {
                throw new RuntimeException('The incident does not belong to the active shop context.');
            }
            $scope = (string) Tools::getValue('scope');
            $issueKey = (string) Tools::getValue('issue_key');
            $issueType = (string) Tools::getValue('issue_type');
            $status = (string) Tools::getValue('status');
            $result = WmIntegrityReview::save(
                $scope,
                $issueKey,
                $issueType,
                $status,
                (string) Tools::getValue('note'),
                (string) Tools::getValue('snapshot_hash'),
                $idShop
            );
            $auditOrderId = 0;
            if (preg_match('/^(?:order|stock):[a-z0-9_]+:o:(\d+)/', $issueKey, $orderMatch)) {
                $auditOrderId = (int) $orderMatch[1];
            } elseif (preg_match('/^stock:[a-z0-9_]+:od:(\d+)$/', $issueKey, $detailMatch)) {
                $auditOrderId = (int) Db::getInstance()->getValue(
                    'SELECT id_order FROM `' . _DB_PREFIX_ . 'order_detail`
                     WHERE id_order_detail = ' . (int) $detailMatch[1],
                    false
                );
            }
            WmAuditLogger::log('integrity_review_updated', array(
                'scope' => $scope,
                'issue_key' => $issueKey,
                'issue_type' => $issueType,
                'status' => $status,
                'note' => $result['note'],
            ), $auditOrderId ?: null);
            $this->ajaxDie(json_encode(array('success' => true, 'review' => $result), JSON_UNESCAPED_UNICODE));
        } catch (Exception $exception) {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => $exception->getMessage())));
        }
    }

    public function ajaxProcessRepairStockIntegrity()
    {
        if (!$this->access('edit') || !$this->module->isCurrentEmployeeSuperAdmin()) {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => 'Only SuperAdmin can repair a confirmed incident.')));
        }
        if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => 'POST required.')));
        }

        try {
            $service = new WmStockRepairService($this->context);
            $result = $service->repair(
                (string) Tools::getValue('issue_key'),
                (string) Tools::getValue('snapshot_hash')
            );
            $this->ajaxDie(json_encode(array('success' => true, 'result' => $result), JSON_UNESCAPED_UNICODE));
        } catch (Exception $exception) {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => $exception->getMessage())));
        }
    }

    public function ajaxProcessAuditLog()
    {
        if (!$this->access('view') || !$this->module->canCurrentEmployeeViewDiagnostics()) {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => 'Access denied.')));
        }

        try {
            $employeeFilter = trim((string) Tools::getValue('audit_employee'));
            $filters = array(
                'action' => Tools::substr(trim((string) Tools::getValue('audit_action')), 0, 64),
                'id_employee' => $employeeFilter === '' ? null : max(0, (int) $employeeFilter),
                'id_order' => max(0, (int) Tools::getValue('audit_order')),
                'date_from' => trim((string) Tools::getValue('audit_date_from')),
                'date_to' => trim((string) Tools::getValue('audit_date_to')),
            );
            $page = max(1, (int) Tools::getValue('page', 1));
            $limit = max(10, min(100, (int) Tools::getValue('limit', 50)));
            $idShop = (int) $this->context->shop->id;
            $total = WmAuditLogger::count($filters, $idShop);
            $pages = max(1, (int) ceil($total / $limit));
            $page = min($page, $pages);
            $rows = WmAuditLogger::search($filters, $page, $limit, $idShop);
            $labels = $this->module->getAuditActionLabels();

            foreach ($rows as &$row) {
                $details = json_decode((string) $row['details_json'], true);
                $row['details'] = is_array($details) ? $details : array('raw' => (string) $row['details_json']);
                unset($row['details_json']);
                $row['action_label'] = isset($labels[$row['action']])
                    ? $labels[$row['action']]
                    : str_replace('_', ' ', (string) $row['action']);
                $row['employee_name'] = trim((string) $row['employee_name']);
                $row['order_url'] = !empty($row['id_order'])
                    ? $this->getOrderViewUrl((int) $row['id_order'])
                    : '';
            }
            unset($row);

            $this->ajaxDie(json_encode(array(
                'success' => true,
                'report' => array(
                    'rows' => $rows,
                    'total' => $total,
                    'page' => $page,
                    'pages' => $pages,
                    'limit' => $limit,
                ),
            ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } catch (Exception $exception) {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => $exception->getMessage())));
        }
    }

    public function ajaxProcessExportIntegrity()
    {
        if (!$this->access('view') || !$this->module->canCurrentEmployeeViewDiagnostics()) {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => 'Access denied.')));
        }

        try {
            $service = new WmIntegrityService($this->context);
            $issues = $this->prepareIntegrityIssues($service->getForExport(
                (string) Tools::getValue('issue_type'),
                (string) Tools::getValue('severity'),
                Wildenmanager::MAX_INTEGRITY_EXPORT
            ));
            WmAuditLogger::log('integrity_report_exported', array(
                'issue_type' => (string) Tools::getValue('issue_type'),
                'severity' => (string) Tools::getValue('severity'),
                'rows' => count($issues),
            ));
            $this->downloadIntegrityCsv($issues);
        } catch (Exception $exception) {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => $exception->getMessage())));
        }
    }

    public function ajaxProcessExportStockIntegrity()
    {
        if (!$this->access('view') || !$this->module->canCurrentEmployeeViewDiagnostics()) {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => 'Access denied.')));
        }

        try {
            $service = new WmStockIntegrityService($this->context);
            $issues = $this->prepareStockIntegrityIssues($service->getForExport(
                (string) Tools::getValue('issue_type'),
                (string) Tools::getValue('severity'),
                (string) Tools::getValue('product_kind'),
                Wildenmanager::MAX_STOCK_INTEGRITY_EXPORT
            ));
            WmAuditLogger::log('stock_integrity_report_exported', array(
                'issue_type' => (string) Tools::getValue('issue_type'),
                'severity' => (string) Tools::getValue('severity'),
                'product_kind' => (string) Tools::getValue('product_kind'),
                'rows' => count($issues),
            ));
            $this->downloadStockIntegrityCsv($issues);
        } catch (Exception $exception) {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => $exception->getMessage())));
        }
    }

    private function getOrderViewUrl($idOrder)
    {
        return $this->context->link->getAdminLink(
            'AdminOrders',
            true,
            array(),
            array(
                'vieworder' => 1,
                'id_order' => (int) $idOrder,
            )
        );
    }

    private function getProductEditUrl($idProduct)
    {
        return $this->context->link->getAdminLink(
            'AdminProducts',
            true,
            array(
                'route' => 'admin_product_form',
                'id' => (int) $idProduct,
            )
        );
    }

    private function prepareIntegrityIssues(array $issues)
    {
        $labels = $this->module->getIntegrityIssueTypes();
        foreach ($issues as &$issue) {
            $issue['label'] = isset($labels[$issue['issue_type']]) ? $labels[$issue['issue_type']] : $issue['issue_type'];
            $issue['detail'] = $this->getIntegrityDetail($issue);
            $issue['order_url'] = $this->getOrderViewUrl((int) $issue['id_order']);
        }
        unset($issue);

        return WmIntegrityReview::attach('order_integrity', $issues);
    }

    private function getIntegrityDetail(array $issue)
    {
        switch ($issue['issue_type']) {
            case 'missing_history':
                return $this->trans('The order has no status history.', array(), 'Modules.Wildenmanager.Admin');
            case 'state_history_mismatch':
                return sprintf(
                    $this->trans('Current status #%d; last history status #%d.', array(), 'Modules.Wildenmanager.Admin'),
                    (int) $issue['value_a'],
                    (int) $issue['value_b']
                );
            case 'delivery_address_missing':
            case 'invoice_address_missing':
                return sprintf(
                    $this->trans('Referenced address #%d does not exist.', array(), 'Modules.Wildenmanager.Admin'),
                    (int) $issue['value_a']
                );
            case 'customer_missing':
                return sprintf(
                    $this->trans('Referenced customer #%d does not exist.', array(), 'Modules.Wildenmanager.Admin'),
                    (int) $issue['value_a']
                );
            case 'customer_incomplete':
                return sprintf(
                    $this->trans('Customer #%d has empty fields: %s.', array(), 'Modules.Wildenmanager.Admin'),
                    (int) $issue['value_a'],
                    (string) $issue['value_b']
                );
            case 'invoice_number_without_date':
                return sprintf(
                    $this->trans('Invoice #%d (record #%d) has no valid date.', array(), 'Modules.Wildenmanager.Admin'),
                    (int) $issue['value_b'],
                    (int) $issue['value_a']
                );
            case 'delivery_number_without_date':
                return sprintf(
                    $this->trans('Delivery slip #%d (invoice record #%d) has no valid date.', array(), 'Modules.Wildenmanager.Admin'),
                    (int) $issue['value_b'],
                    (int) $issue['value_a']
                );
            case 'delivery_address_deleted':
            case 'invoice_address_deleted':
                return sprintf(
                    $this->trans('Address #%d is retained for order history and marked deleted.', array(), 'Modules.Wildenmanager.Admin'),
                    (int) $issue['value_a']
                );
            case 'customer_deleted':
                return sprintf(
                    $this->trans('Customer #%d is retained for order history and marked deleted.', array(), 'Modules.Wildenmanager.Admin'),
                    (int) $issue['value_a']
                );
        }

        return '';
    }

    private function prepareStockIntegrityIssues(array $issues)
    {
        $labels = $this->module->getStockIntegrityIssueTypes();
        $activeProducts = array();
        $productIds = array();
        foreach ($issues as $candidate) {
            if ($candidate['issue_type'] === 'stock_cache_mismatch' && !empty($candidate['id_product'])) {
                $productIds[] = (int) $candidate['id_product'];
            }
        }
        $productIds = array_values(array_unique(array_filter($productIds)));
        if ($productIds) {
            $productRows = Db::getInstance()->executeS(
                'SELECT id_product, active FROM `' . _DB_PREFIX_ . 'product`
                 WHERE id_product IN (' . implode(',', $productIds) . ')',
                true,
                false
            );
            foreach ((array) $productRows as $productRow) {
                $activeProducts[(int) $productRow['id_product']] = (bool) $productRow['active'];
            }
        }
        foreach ($issues as &$issue) {
            $issue['label'] = isset($labels[$issue['issue_type']])
                ? $labels[$issue['issue_type']]
                : $issue['issue_type'];
            $issue['detail'] = $this->getStockIntegrityDetail($issue);
            $issue['order_url'] = !empty($issue['id_order'])
                ? $this->getOrderViewUrl((int) $issue['id_order'])
                : '';
            $issue['product_url'] = !empty($issue['id_product'])
                ? $this->getProductEditUrl((int) $issue['id_product'])
                : '';
            $issue['repair_eligible'] = $this->module->isCurrentEmployeeSuperAdmin()
                && $issue['issue_type'] === 'stock_cache_mismatch'
                && $issue['product_kind'] === 'standard'
                && !empty($activeProducts[(int) $issue['id_product']]);
            if ($issue['issue_type'] === 'stock_cache_mismatch') {
                $issue['repair_classification'] = !empty($activeProducts[(int) $issue['id_product']])
                    ? 'safe'
                    : 'review';
            } elseif ($issue['issue_type'] === 'refund_without_credit_slip'
                || in_array($issue['severity'], array('high', 'medium'), true)
            ) {
                $issue['repair_classification'] = 'review';
            } else {
                $issue['repair_classification'] = 'do_not_repair';
            }
        }
        unset($issue);

        return WmIntegrityReview::attach('stock_integrity', $issues);
    }

    private function getStockIntegrityDetail(array $issue)
    {
        switch ($issue['issue_type']) {
            case 'refunded_exceeds_ordered':
                return sprintf(
                    $this->trans('Ordered: %d; refunded counter: %d.', array(), 'Modules.Wildenmanager.Admin'),
                    (int) $issue['value_a'],
                    (int) $issue['value_b']
                );
            case 'returned_exceeds_ordered':
                return sprintf(
                    $this->trans('Ordered: %d; returned counter: %d.', array(), 'Modules.Wildenmanager.Admin'),
                    (int) $issue['value_a'],
                    (int) $issue['value_b']
                );
            case 'reinjected_exceeds_ordered':
                return sprintf(
                    $this->trans('Ordered: %d; reinjected into stock: %d.', array(), 'Modules.Wildenmanager.Admin'),
                    (int) $issue['value_a'],
                    (int) $issue['value_b']
                );
            case 'credit_slip_exceeds_ordered':
                return sprintf(
                    $this->trans('Ordered: %d; cumulative credit-slip quantity: %d.', array(), 'Modules.Wildenmanager.Admin'),
                    (int) $issue['value_a'],
                    (int) $issue['value_b']
                );
            case 'return_request_exceeds_ordered':
                return sprintf(
                    $this->trans('Ordered: %d; cumulative return-request quantity: %d.', array(), 'Modules.Wildenmanager.Admin'),
                    (int) $issue['value_a'],
                    (int) $issue['value_b']
                );
            case 'refund_without_credit_slip':
                return sprintf(
                    $this->trans('Refunded counter: %d; quantity covered by credit slips: %d; ordered: %d.', array(), 'Modules.Wildenmanager.Admin'),
                    (int) $issue['value_a'],
                    (int) $issue['value_b'],
                    (int) $issue['value_c']
                );
            case 'refund_not_reinjected':
                return sprintf(
                    $this->trans('Refunded/returned evidence: %d; stock reinjection marker: %d; ordered: %d. The absence of reinjection may be intentional.', array(), 'Modules.Wildenmanager.Admin'),
                    (int) $issue['value_a'],
                    (int) $issue['value_b'],
                    (int) $issue['value_c']
                );
            case 'cancelled_restock_evidence_missing':
                return sprintf(
                    $this->trans('The cancelled order previously had a stock-affecting status. Ordered: %d; line reinjection markers: %d. No persistent positive movement evidence is available; manual verification may be required.', array(), 'Modules.Wildenmanager.Admin'),
                    (int) $issue['value_a'],
                    (int) $issue['value_b']
                );
            case 'stock_cache_mismatch':
            case 'pack_stock_cache_mismatch':
                return sprintf(
                    $this->trans('Available: %d; reserved: %d; physical: %d. Expected physical quantity: %d.', array(), 'Modules.Wildenmanager.Admin'),
                    (int) $issue['value_a'],
                    (int) $issue['value_b'],
                    (int) $issue['value_c'],
                    (int) $issue['value_a'] + (int) $issue['value_b']
                );
        }

        return '';
    }

    private function downloadIntegrityCsv(array $issues)
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="wilden-integrity-' . date('Y-m-d-His') . '.csv"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo "\xEF\xBB\xBF";
        $stream = fopen('php://output', 'w');
        fputcsv($stream, array(
            'Severity', 'Issue', 'Order ID', 'Reference', 'Order date', 'Detail',
            'Review status', 'Review note', 'Reviewed by', 'Review date',
        ), ';');
        foreach ($issues as $issue) {
            $row = array(
                $issue['severity'],
                $issue['label'],
                (int) $issue['id_order'],
                $issue['reference'],
                $issue['order_date'],
                $issue['detail'],
                $issue['review_status'],
                $issue['review_note'],
                $issue['review_employee'],
                $issue['review_date'],
            );
            $row = WmSpreadsheet::neutralizeRow($row);
            fputcsv($stream, $row, ';');
        }
        fclose($stream);
        exit;
    }

    private function downloadStockIntegrityCsv(array $issues)
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="wilden-stock-integrity-' . date('Y-m-d-His') . '.csv"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo "\xEF\xBB\xBF";
        $stream = fopen('php://output', 'w');
        fputcsv($stream, array(
            'Severity', 'Issue', 'Product type', 'Order ID', 'Reference', 'Order date',
            'Order detail ID', 'Product ID', 'Attribute ID', 'Product', 'Ordered',
            'Refunded', 'Returned', 'Reinjected', 'Detail', 'Review status',
            'Review note', 'Reviewed by', 'Review date',
        ), ';');
        foreach ($issues as $issue) {
            $row = array(
                $issue['severity'],
                $issue['label'],
                $issue['product_kind'],
                $issue['id_order'],
                $issue['reference'],
                $issue['order_date'],
                $issue['id_order_detail'],
                $issue['id_product'],
                $issue['id_product_attribute'],
                $issue['product_name'],
                $issue['ordered_quantity'],
                $issue['refunded_quantity'],
                $issue['returned_quantity'],
                $issue['reinjected_quantity'],
                $issue['detail'],
                $issue['review_status'],
                $issue['review_note'],
                $issue['review_employee'],
                $issue['review_date'],
            );
            $row = WmSpreadsheet::neutralizeRow($row);
            fputcsv($stream, $row, ';');
        }
        fclose($stream);
        exit;
    }

    private function saveNote()
    {
        if (!$this->canEdit()) {
            $this->errors[] = $this->trans('You do not have permission to edit orders.', array(), 'Modules.Wildenmanager.Admin');
            return;
        }

        $idOrder = (int) Tools::getValue('id_order');
        $note = trim((string) Tools::getValue('wm_note'));

        if (!$idOrder || !$this->repository->isOrderAccessible($idOrder)) {
            $this->errors[] = $this->trans('Order not found.', array(), 'Modules.Wildenmanager.Admin');
            return;
        }
        if (Tools::strlen($note) > 5000 || strpos($note, "\0") !== false) {
            $this->errors[] = $this->trans('The note is invalid or exceeds 5,000 characters.', array(), 'Modules.Wildenmanager.Admin');
            return;
        }

        $oldNote = WmOrderNote::get($idOrder);
        if (WmOrderNote::save($idOrder, $note, (int) $this->context->employee->id)) {
            WmAuditLogger::log('note_updated', array(
                'old_length' => Tools::strlen($oldNote),
                'new_length' => Tools::strlen($note),
            ), $idOrder);
            $this->confirmations[] = $this->trans('The internal note was saved.', array(), 'Modules.Wildenmanager.Admin');
        } else {
            $this->errors[] = $this->trans('The internal note could not be saved.', array(), 'Modules.Wildenmanager.Admin');
        }
    }

    private function exportOrders()
    {
        if (!$this->access('view') || !$this->module->canCurrentEmployeeExport()) {
            $this->errors[] = $this->trans('You do not have permission to export orders.', array(), 'Modules.Wildenmanager.Admin');

            return;
        }

        $filters = $this->getFilters();
        $sort = (string) Tools::getValue('sort', 'date_add');
        $direction = (string) Tools::getValue('direction', 'DESC');
        $orders = $this->repository->getOrdersForExport($filters, $sort, $direction);

        WmAuditLogger::log('orders_exported', array('rows' => count($orders), 'filters' => $filters));

        $filename = 'wilden-orders-' . date('Y-m-d-His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        echo "\xEF\xBB\xBF";

        $stream = fopen('php://output', 'w');
        fputcsv($stream, array('ID', 'Reference', 'Date', 'Customer', 'Email', 'Total', 'State', 'Payment', 'Carrier', 'Internal note'), ';');
        foreach ($orders as $order) {
            fputcsv($stream, array(
                (int) $order['id_order'],
                $this->safeSpreadsheetValue($order['reference']),
                $order['date_add'],
                $this->safeSpreadsheetValue($order['customer']),
                $this->safeSpreadsheetValue($order['email']),
                number_format((float) $order['total_paid_tax_incl'], 2, '.', ''),
                $this->safeSpreadsheetValue($order['state_name']),
                $this->safeSpreadsheetValue($order['payment']),
                $this->safeSpreadsheetValue($order['carrier_name']),
                $this->safeSpreadsheetValue($order['note']),
            ), ';');
        }
        fclose($stream);
        exit;
    }

    private function getFilters()
    {
        return array(
            'id_order' => trim((string) Tools::getValue('id_order_filter', '')),
            'reference' => trim((string) Tools::getValue('reference', '')),
            'customer' => trim((string) Tools::getValue('customer', '')),
            'new_customer' => trim((string) Tools::getValue('new_customer', '')),
            'email' => trim((string) Tools::getValue('email', '')),
            'country' => trim((string) Tools::getValue('country', '')),
            'company' => trim((string) Tools::getValue('company', '')),
            'id_order_state' => (int) Tools::getValue('id_order_state', 0),
            'payment' => trim((string) Tools::getValue('payment', '')),
            'carrier' => trim((string) Tools::getValue('carrier', '')),
            'shop' => trim((string) Tools::getValue('shop', '')),
            'note' => trim((string) Tools::getValue('note', '')),
            'date_from' => trim((string) Tools::getValue('date_from', '')),
            'date_to' => trim((string) Tools::getValue('date_to', '')),
            'total_min' => trim((string) Tools::getValue('total_min', '')),
            'total_max' => trim((string) Tools::getValue('total_max', '')),
        );
    }

    private function getAvailableColumns()
    {
        return array(
            'id_order' => 'ID',
            'reference' => $this->trans('Reference', array(), 'Modules.Wildenmanager.Admin'),
            'new' => $this->trans('New client', array(), 'Modules.Wildenmanager.Admin'),
            'country' => $this->trans('Delivery', array(), 'Modules.Wildenmanager.Admin'),
            'customer' => $this->trans('Customer', array(), 'Modules.Wildenmanager.Admin'),
            'email' => $this->trans('Email', array(), 'Modules.Wildenmanager.Admin'),
            'total' => $this->trans('Total', array(), 'Modules.Wildenmanager.Admin'),
            'state' => $this->trans('State', array(), 'Modules.Wildenmanager.Admin'),
            'payment' => $this->trans('Payment', array(), 'Modules.Wildenmanager.Admin'),
            'carrier' => $this->trans('Carrier', array(), 'Modules.Wildenmanager.Admin'),
            'company' => $this->trans('Company', array(), 'Modules.Wildenmanager.Admin'),
            'shop' => $this->trans('Store', array(), 'Modules.Wildenmanager.Admin'),
            'note' => $this->trans('Internal note', array(), 'Modules.Wildenmanager.Admin'),
            'date_add' => $this->trans('Date', array(), 'Modules.Wildenmanager.Admin'),
        );
    }

    private function getSelectedColumns()
    {
        $requested = Tools::getValue('columns');
        if (!is_array($requested)) {
            return array('id_order', 'reference', 'new', 'country', 'customer', 'total', 'payment', 'state', 'date_add');
        }

        return $this->sanitizeColumns($requested);
    }

    private function sanitizeColumns(array $columns)
    {
        $allowed = array_keys($this->getAvailableColumns());
        $columns = array_values(array_intersect($allowed, $columns));

        return $columns ?: array('id_order', 'reference', 'new', 'country', 'customer', 'total', 'payment', 'state', 'date_add');
    }

    private function buildPersistentQuery(array $filters, array $columns, $limit, $sort, $direction)
    {
        $queryFilters = $filters;
        $queryFilters['id_order_filter'] = $queryFilters['id_order'];
        unset($queryFilters['id_order']);

        return http_build_query(array_merge($queryFilters, array(
            'columns' => $columns,
            'limit' => (int) $limit,
            'sort' => $sort,
            'direction' => $direction,
            'wm_filter_submitted' => 1,
        )));
    }

    private function canEdit()
    {
        return (bool) $this->access('edit');
    }

    private function safeSpreadsheetValue($value)
    {
        return WmSpreadsheet::neutralize($value);
    }

    private function formatAddressText(Address $address)
    {
        if (!Validate::isLoadedObject($address)) {
            return '';
        }

        $country = new Country((int) $address->id_country, (int) $this->context->language->id);
        $state = $address->id_state ? new State((int) $address->id_state) : null;
        $lines = array(
            trim($address->firstname . ' ' . $address->lastname),
            $address->company,
            $address->address1,
            $address->address2,
            trim($address->postcode . ' ' . $address->city),
            $state && Validate::isLoadedObject($state) ? $state->name : '',
            Validate::isLoadedObject($country) ? $country->name : '',
            $address->phone ?: $address->phone_mobile,
        );

        return implode("\n", array_filter(array_map('trim', $lines)));
    }
}
