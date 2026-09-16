<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminWildenManagerOrdersController extends ModuleAdminController
{
    private $repository;
    private $bulkPreview;
    private $bulkResults;

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
            'submitWmSaveView',
            'deleteWmView',
            'submitWmBulkPreview',
            'submitWmBulkExecute',
        );
        foreach ($mutatingActions as $action) {
            if (Tools::isSubmit($action) && (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST')) {
                $this->errors[] = $this->module->l('This action requires a POST request.', 'AdminWildenManagerOrdersController');
                return;
            }
        }

        if (Tools::isSubmit('exportWmOrders')) {
            $this->exportOrders();
        }

        if (Tools::isSubmit('submitWmSaveNote')) {
            $this->saveNote();
        }
        if (Tools::isSubmit('submitWmSaveView')) {
            $this->saveView();
        }
        if (Tools::isSubmit('deleteWmView')) {
            $this->deleteView();
        }
        if (Tools::isSubmit('submitWmBulkPreview')) {
            $this->previewBulkAction();
        }
        if (Tools::isSubmit('submitWmBulkExecute')) {
            $this->executeBulkAction();
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
        $savedView = $this->getRequestedSavedView();
        $savedSort = 'date_add';
        $savedDirection = 'DESC';
        $savedLimit = 25;

        if ($savedView && !Tools::isSubmit('wm_filter_submitted')) {
            $loadedFilters = json_decode($savedView['filters_json'], true);
            $loadedColumns = json_decode($savedView['columns_json'], true);
            if (is_array($loadedFilters)) {
                $savedSort = isset($loadedFilters['_sort']) ? (string) $loadedFilters['_sort'] : $savedSort;
                $savedDirection = isset($loadedFilters['_direction']) ? (string) $loadedFilters['_direction'] : $savedDirection;
                $savedLimit = isset($loadedFilters['_limit']) ? (int) $loadedFilters['_limit'] : $savedLimit;
                unset($loadedFilters['_sort'], $loadedFilters['_direction'], $loadedFilters['_limit']);
                $filters = array_merge($filters, $loadedFilters);
            }
            if (is_array($loadedColumns)) {
                $columns = $this->sanitizeColumns($loadedColumns);
            }
        }

        $page = max(1, (int) Tools::getValue('page', 1));
        $limit = max(10, min(100, (int) Tools::getValue('limit', $savedLimit)));
        $sort = (string) Tools::getValue('sort', $savedSort);
        $direction = strtoupper((string) Tools::getValue('direction', $savedDirection)) === 'ASC' ? 'ASC' : 'DESC';
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
            $order['view_url'] = $this->context->link->getAdminLink('AdminOrders') .
                '&vieworder&id_order=' . (int) $order['id_order'];
            $order['customer_url'] = $this->context->link->getAdminLink('AdminCustomers') .
                '&viewcustomer&id_customer=' . (int) $order['id_customer'];
            $order['invoice_url'] = $this->context->link->getAdminLink('AdminPdf') .
                '&submitAction=generateInvoicePDF&id_order=' . (int) $order['id_order'];
            $order['delivery_slip_url'] = $this->context->link->getAdminLink('AdminPdf') .
                '&submitAction=generateDeliverySlipPDF&id_order=' . (int) $order['id_order'];
        }
        unset($order);

        $baseUrl = $this->context->link->getAdminLink(Wilden_manager::TAB_CLASS);
        $this->context->smarty->assign(array(
            'wm_base_url' => $baseUrl,
            'wm_ajax_url' => $baseUrl . '&ajax=1',
            'wm_orders' => $orders,
            'wm_filters' => $filters,
            'wm_columns' => $columns,
            'wm_available_columns' => $this->getAvailableColumns(),
            'wm_order_states' => OrderState::getOrderStates((int) $this->context->language->id),
            'wm_saved_views' => WmSavedView::getForEmployee(
                (int) $this->context->employee->id,
                (int) $this->context->shop->id
            ),
            'wm_active_view_id' => $savedView ? (int) $savedView['id_wilden_manager_saved_view'] : 0,
            'wm_total' => $total,
            'wm_page' => $page,
            'wm_limit' => $limit,
            'wm_limits' => array(10, 25, 50, 100),
            'wm_total_pages' => max(1, (int) ceil($total / $limit)),
            'wm_sort' => $sort,
            'wm_direction' => $direction,
            'wm_query' => $this->buildPersistentQuery($filters, $columns, $limit, $sort, $direction),
            'wm_bulk_preview' => $this->bulkPreview,
            'wm_bulk_results' => $this->bulkResults,
            'wm_selected_order_ids' => array_map('intval', (array) Tools::getValue('order_ids', array())),
            'wm_can_edit' => $this->canEdit(),
            'wm_max_bulk' => Wilden_manager::MAX_BULK_ORDERS,
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
            'wm_qv_view_url' => $this->context->link->getAdminLink('AdminOrders') . '&vieworder&id_order=' . $idOrder,
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
                'error' => $this->module->l('Select at least one order column.', 'AdminWildenManagerOrdersController'),
            )));
        }

        if (!WmGridPreference::save(
            $columns,
            (int) $this->context->employee->id,
            (int) $this->context->shop->id
        )) {
            $this->ajaxDie(json_encode(array(
                'success' => false,
                'error' => $this->module->l('The column preference could not be saved.', 'AdminWildenManagerOrdersController'),
            )));
        }

        $storedColumns = WmGridPreference::get(
            (int) $this->context->employee->id,
            (int) $this->context->shop->id
        );
        if ($storedColumns !== $columns) {
            $this->ajaxDie(json_encode(array(
                'success' => false,
                'error' => $this->module->l('The saved column preference could not be verified.', 'AdminWildenManagerOrdersController'),
            )));
        }

        WmAuditLogger::log('native_order_columns_updated', array('columns' => $columns));
        $this->ajaxDie(json_encode(array('success' => true, 'columns' => $storedColumns)));
    }

    public function ajaxProcessPreviewBulkStatus()
    {
        if (!$this->canEdit()) {
            $this->ajaxDie(json_encode(array(
                'success' => false,
                'error' => $this->module->l('You do not have permission to change order states.', 'AdminWildenManagerOrdersController'),
            )));
        }

        try {
            $service = new WmBulkOrderService($this->context, $this->repository);
            $preview = $service->preview(
                (array) Tools::getValue('order_ids', array()),
                (int) Tools::getValue('target_state')
            );
            $this->ajaxDie(json_encode(array('success' => true, 'preview' => $preview)));
        } catch (Exception $exception) {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => $exception->getMessage())));
        }
    }

    public function ajaxProcessExecuteBulkStatus()
    {
        if (!$this->canEdit()) {
            $this->ajaxDie(json_encode(array(
                'success' => false,
                'error' => $this->module->l('You do not have permission to change order states.', 'AdminWildenManagerOrdersController'),
            )));
        }

        try {
            $service = new WmBulkOrderService($this->context, $this->repository);
            $results = $service->execute(
                (array) Tools::getValue('order_ids', array()),
                (int) Tools::getValue('target_state'),
                (int) Tools::getValue('preview_timestamp'),
                (string) Tools::getValue('preview_snapshot'),
                (string) Tools::getValue('preview_signature'),
                (bool) Tools::getValue('send_email')
            );
            $this->ajaxDie(json_encode(array('success' => true, 'results' => $results)));
        } catch (Exception $exception) {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => $exception->getMessage())));
        }
    }

    public function ajaxProcessExportSelectedOrders()
    {
        if (!$this->access('view')) {
            $this->ajaxDie(json_encode(array(
                'success' => false,
                'error' => $this->module->l('You do not have permission to export orders.', 'AdminWildenManagerOrdersController'),
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

        if (!$ids || count($ids) > Wilden_manager::MAX_EXPORT_ORDERS || !$columns) {
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

        $orders = $this->repository->getOrdersByIds($ids, Wilden_manager::MAX_EXPORT_ORDERS);
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
        if (!$this->access('view')) {
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

    public function ajaxProcessAuditLog()
    {
        if (!$this->access('view')) {
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
                    ? $this->context->link->getAdminLink('AdminOrders') .
                        '&vieworder&id_order=' . (int) $row['id_order']
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
        if (!$this->access('view')) {
            $this->ajaxDie(json_encode(array('success' => false, 'error' => 'Access denied.')));
        }

        try {
            $service = new WmIntegrityService($this->context);
            $issues = $this->prepareIntegrityIssues($service->getForExport(
                (string) Tools::getValue('issue_type'),
                (string) Tools::getValue('severity'),
                Wilden_manager::MAX_INTEGRITY_EXPORT
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

    private function prepareIntegrityIssues(array $issues)
    {
        $labels = $this->module->getIntegrityIssueTypes();
        foreach ($issues as &$issue) {
            $issue['label'] = isset($labels[$issue['issue_type']]) ? $labels[$issue['issue_type']] : $issue['issue_type'];
            $issue['detail'] = $this->getIntegrityDetail($issue);
            $issue['order_url'] = $this->context->link->getAdminLink('AdminOrders') .
                '&vieworder&id_order=' . (int) $issue['id_order'];
        }
        unset($issue);

        return $issues;
    }

    private function getIntegrityDetail(array $issue)
    {
        switch ($issue['issue_type']) {
            case 'missing_history':
                return $this->module->l('The order has no status history.', 'AdminWildenManagerOrdersController');
            case 'state_history_mismatch':
                return sprintf(
                    $this->module->l('Current status #%d; last history status #%d.', 'AdminWildenManagerOrdersController'),
                    (int) $issue['value_a'],
                    (int) $issue['value_b']
                );
            case 'delivery_address_missing':
            case 'invoice_address_missing':
                return sprintf(
                    $this->module->l('Referenced address #%d does not exist.', 'AdminWildenManagerOrdersController'),
                    (int) $issue['value_a']
                );
            case 'customer_missing':
                return sprintf(
                    $this->module->l('Referenced customer #%d does not exist.', 'AdminWildenManagerOrdersController'),
                    (int) $issue['value_a']
                );
            case 'customer_incomplete':
                return sprintf(
                    $this->module->l('Customer #%d has empty fields: %s.', 'AdminWildenManagerOrdersController'),
                    (int) $issue['value_a'],
                    (string) $issue['value_b']
                );
            case 'invoice_number_without_date':
                return sprintf(
                    $this->module->l('Invoice #%d (record #%d) has no valid date.', 'AdminWildenManagerOrdersController'),
                    (int) $issue['value_b'],
                    (int) $issue['value_a']
                );
            case 'delivery_number_without_date':
                return sprintf(
                    $this->module->l('Delivery slip #%d (invoice record #%d) has no valid date.', 'AdminWildenManagerOrdersController'),
                    (int) $issue['value_b'],
                    (int) $issue['value_a']
                );
            case 'delivery_address_deleted':
            case 'invoice_address_deleted':
                return sprintf(
                    $this->module->l('Address #%d is retained for order history and marked deleted.', 'AdminWildenManagerOrdersController'),
                    (int) $issue['value_a']
                );
            case 'customer_deleted':
                return sprintf(
                    $this->module->l('Customer #%d is retained for order history and marked deleted.', 'AdminWildenManagerOrdersController'),
                    (int) $issue['value_a']
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
        fputcsv($stream, array('Severity', 'Issue', 'Order ID', 'Reference', 'Order date', 'Detail'), ';');
        foreach ($issues as $issue) {
            $row = array(
                $issue['severity'],
                $issue['label'],
                (int) $issue['id_order'],
                $issue['reference'],
                $issue['order_date'],
                $issue['detail'],
            );
            foreach ($row as &$value) {
                $value = (string) $value;
                if ($value !== '' && preg_match('/^[\x00-\x20]*[=+\-@]/', $value)) {
                    $value = "'" . $value;
                }
            }
            unset($value);
            fputcsv($stream, $row, ';');
        }
        fclose($stream);
        exit;
    }

    private function saveNote()
    {
        if (!$this->canEdit()) {
            $this->errors[] = $this->module->l('You do not have permission to edit orders.', 'AdminWildenManagerOrdersController');
            return;
        }

        $idOrder = (int) Tools::getValue('id_order');
        $note = trim((string) Tools::getValue('wm_note'));

        if (!$idOrder || !$this->repository->isOrderAccessible($idOrder)) {
            $this->errors[] = $this->module->l('Order not found.', 'AdminWildenManagerOrdersController');
            return;
        }
        if (Tools::strlen($note) > 5000 || strpos($note, "\0") !== false) {
            $this->errors[] = $this->module->l('The note is invalid or exceeds 5,000 characters.', 'AdminWildenManagerOrdersController');
            return;
        }

        $oldNote = WmOrderNote::get($idOrder);
        if (WmOrderNote::save($idOrder, $note, (int) $this->context->employee->id)) {
            WmAuditLogger::log('note_updated', array(
                'old_length' => Tools::strlen($oldNote),
                'new_length' => Tools::strlen($note),
            ), $idOrder);
            $this->confirmations[] = $this->module->l('The internal note was saved.', 'AdminWildenManagerOrdersController');
        } else {
            $this->errors[] = $this->module->l('The internal note could not be saved.', 'AdminWildenManagerOrdersController');
        }
    }

    private function saveView()
    {
        $name = trim((string) Tools::getValue('wm_view_name'));
        if ($name === '' || Tools::strlen($name) > 128 || !Validate::isGenericName($name)) {
            $this->errors[] = $this->module->l('Enter a valid saved-view name.', 'AdminWildenManagerOrdersController');
            return;
        }

        $viewFilters = $this->getFilters();
        $viewFilters['_sort'] = (string) Tools::getValue('sort', 'date_add');
        $viewFilters['_direction'] = (string) Tools::getValue('direction', 'DESC');
        $viewFilters['_limit'] = (int) Tools::getValue('limit', 25);

        if (WmSavedView::save(
            $name,
            $viewFilters,
            $this->getSelectedColumns(),
            (int) $this->context->employee->id,
            (int) $this->context->shop->id,
            (bool) Tools::getValue('wm_view_default')
        )) {
            WmAuditLogger::log('saved_view_created', array('name' => $name));
            $this->confirmations[] = $this->module->l('The view was saved.', 'AdminWildenManagerOrdersController');
        } else {
            $this->errors[] = $this->module->l('The view could not be saved.', 'AdminWildenManagerOrdersController');
        }
    }

    private function deleteView()
    {
        $idView = (int) Tools::getValue('id_wm_view');
        if (WmSavedView::delete(
            $idView,
            (int) $this->context->employee->id,
            (int) $this->context->shop->id
        )) {
            WmAuditLogger::log('saved_view_deleted', array('id_view' => $idView));
            $this->confirmations[] = $this->module->l('The saved view was deleted.', 'AdminWildenManagerOrdersController');
        }
    }

    private function previewBulkAction()
    {
        if (!$this->canEdit()) {
            $this->errors[] = $this->module->l('You do not have permission to change order states.', 'AdminWildenManagerOrdersController');
            return;
        }

        try {
            $service = new WmBulkOrderService($this->context, $this->repository);
            $this->bulkPreview = $service->preview(
                (array) Tools::getValue('order_ids', array()),
                (int) Tools::getValue('bulk_state')
            );
        } catch (Exception $exception) {
            $this->errors[] = $exception->getMessage();
        }
    }

    private function executeBulkAction()
    {
        if (!$this->canEdit()) {
            $this->errors[] = $this->module->l('You do not have permission to change order states.', 'AdminWildenManagerOrdersController');
            return;
        }

        try {
            $service = new WmBulkOrderService($this->context, $this->repository);
            $this->bulkResults = $service->execute(
                (array) Tools::getValue('order_ids', array()),
                (int) Tools::getValue('bulk_state'),
                (int) Tools::getValue('preview_timestamp'),
                (string) Tools::getValue('preview_snapshot'),
                (string) Tools::getValue('preview_signature'),
                (bool) Tools::getValue('send_email')
            );
            $this->confirmations[] = sprintf(
                $this->module->l('%d orders processed successfully.', 'AdminWildenManagerOrdersController'),
                count($this->bulkResults['success'])
            );
            foreach ($this->bulkResults['errors'] as $error) {
                $this->errors[] = sprintf('#%d: %s', (int) $error['id_order'], $error['error']);
            }
            foreach ($this->bulkResults['warnings'] as $warning) {
                $this->warnings[] = sprintf('#%d: %s', (int) $warning['id_order'], $warning['warning']);
            }
        } catch (Exception $exception) {
            $this->errors[] = $exception->getMessage();
        }
    }

    private function exportOrders()
    {
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
            'reference' => $this->module->l('Reference', 'AdminWildenManagerOrdersController'),
            'new' => $this->module->l('New client', 'AdminWildenManagerOrdersController'),
            'country' => $this->module->l('Delivery', 'AdminWildenManagerOrdersController'),
            'customer' => $this->module->l('Customer', 'AdminWildenManagerOrdersController'),
            'email' => $this->module->l('Email', 'AdminWildenManagerOrdersController'),
            'total' => $this->module->l('Total', 'AdminWildenManagerOrdersController'),
            'state' => $this->module->l('State', 'AdminWildenManagerOrdersController'),
            'payment' => $this->module->l('Payment', 'AdminWildenManagerOrdersController'),
            'carrier' => $this->module->l('Carrier', 'AdminWildenManagerOrdersController'),
            'company' => $this->module->l('Company', 'AdminWildenManagerOrdersController'),
            'shop' => $this->module->l('Store', 'AdminWildenManagerOrdersController'),
            'note' => $this->module->l('Internal note', 'AdminWildenManagerOrdersController'),
            'date_add' => $this->module->l('Date', 'AdminWildenManagerOrdersController'),
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

    private function getRequestedSavedView()
    {
        $idView = (int) Tools::getValue('id_wm_view');
        if (!$idView) {
            if (Tools::isSubmit('wm_filter_submitted')) {
                return false;
            }

            return WmSavedView::getDefault(
                (int) $this->context->employee->id,
                (int) $this->context->shop->id
            );
        }

        return WmSavedView::get(
            $idView,
            (int) $this->context->employee->id,
            (int) $this->context->shop->id
        );
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
        $value = (string) $value;
        if ($value !== '' && in_array($value[0], array('=', '+', '-', '@'), true)) {
            return "'" . $value;
        }

        return $value;
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
