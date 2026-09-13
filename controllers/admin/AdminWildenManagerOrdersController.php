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
            $order['view_url'] = $this->context->link->getAdminLink('AdminOrders') .
                '&vieworder&id_order=' . (int) $order['id_order'];
            $order['invoice_url'] = $this->context->link->getAdminLink('AdminPdf') .
                '&submitAction=generateInvoicePDF&id_order=' . (int) $order['id_order'];
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
            'wm_recent_audit' => WmAuditLogger::getRecent(20),
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
            'id_order_state' => (int) Tools::getValue('id_order_state', 0),
            'payment' => trim((string) Tools::getValue('payment', '')),
            'carrier' => trim((string) Tools::getValue('carrier', '')),
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
            'customer' => $this->module->l('Customer', 'AdminWildenManagerOrdersController'),
            'email' => $this->module->l('Email', 'AdminWildenManagerOrdersController'),
            'total' => $this->module->l('Total', 'AdminWildenManagerOrdersController'),
            'state' => $this->module->l('State', 'AdminWildenManagerOrdersController'),
            'payment' => $this->module->l('Payment', 'AdminWildenManagerOrdersController'),
            'carrier' => $this->module->l('Carrier', 'AdminWildenManagerOrdersController'),
            'note' => $this->module->l('Internal note', 'AdminWildenManagerOrdersController'),
            'date_add' => $this->module->l('Date', 'AdminWildenManagerOrdersController'),
        );
    }

    private function getSelectedColumns()
    {
        $requested = Tools::getValue('columns');
        if (!is_array($requested)) {
            return array('id_order', 'reference', 'customer', 'total', 'state', 'payment', 'date_add');
        }

        return $this->sanitizeColumns($requested);
    }

    private function sanitizeColumns(array $columns)
    {
        $allowed = array_keys($this->getAvailableColumns());
        $columns = array_values(array_intersect($allowed, $columns));

        return $columns ?: array('id_order', 'reference', 'customer', 'total', 'state', 'date_add');
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
