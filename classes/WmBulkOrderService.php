<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class WmBulkOrderService
{
    const PREVIEW_TTL = 900;

    private $context;
    private $repository;

    public function __construct(Context $context, WmOrderRepository $repository)
    {
        $this->context = $context;
        $this->repository = $repository;
    }

    public function preview(array $ids, $targetState)
    {
        $ids = $this->normalizeIds($ids);
        $state = new OrderState((int) $targetState, (int) $this->context->language->id);

        if (!Validate::isLoadedObject($state)) {
            throw new PrestaShopException('Invalid target order state.');
        }

        $orders = $this->repository->getOrderSummaries($ids);
        if (count($orders) !== count($ids)) {
            throw new PrestaShopException('One or more selected orders are not accessible.');
        }

        $timestamp = time();
        $snapshot = $this->buildSnapshot($orders);

        return array(
            'orders' => $orders,
            'target_state' => array('id' => (int) $state->id, 'name' => (string) $state->name),
            'timestamp' => $timestamp,
            'snapshot' => $snapshot,
            'signature' => $this->sign($ids, (int) $state->id, $timestamp, $snapshot),
            'ids' => $ids,
        );
    }

    public function execute(array $ids, $targetState, $timestamp, $snapshot, $signature, $sendEmail)
    {
        $ids = $this->normalizeIds($ids);
        $targetState = (int) $targetState;
        $timestamp = (int) $timestamp;
        $state = new OrderState($targetState, (int) $this->context->language->id);

        if (!Validate::isLoadedObject($state)) {
            throw new PrestaShopException('Invalid target order state.');
        }

        if ($timestamp < time() - self::PREVIEW_TTL ||
            !hash_equals($this->sign($ids, $targetState, $timestamp, (string) $snapshot), (string) $signature)) {
            throw new PrestaShopException('The preview expired or no longer matches this operation.');
        }

        $summaries = $this->repository->getOrderSummaries($ids);
        if (count($summaries) !== count($ids)) {
            throw new PrestaShopException('One or more selected orders are not accessible.');
        }
        if (!hash_equals((string) $snapshot, $this->buildSnapshot($summaries))) {
            throw new PrestaShopException('At least one order changed after the preview. Generate a new preview.');
        }

        $results = array('success' => array(), 'warnings' => array(), 'errors' => array());

        foreach ($summaries as $summary) {
            $order = new Order((int) $summary['id_order']);
            if (!Validate::isLoadedObject($order)) {
                $results['errors'][] = array('id_order' => (int) $summary['id_order'], 'error' => 'Order not found.');
                continue;
            }
            if ((int) $order->current_state !== (int) $summary['current_state']) {
                $results['errors'][] = array(
                    'id_order' => (int) $order->id,
                    'error' => 'The order changed during execution. Generate a new preview.',
                );
                continue;
            }
            if ((int) $order->current_state === $targetState) {
                $results['success'][] = array('id_order' => (int) $order->id, 'skipped' => true);
                continue;
            }

            try {
                $oldState = (int) $order->current_state;
                $history = new OrderHistory();
                $history->id_order = (int) $order->id;
                $history->id_employee = (int) $this->context->employee->id;
                $useExistingPayment = !$order->hasInvoice();
                $history->changeIdOrderState($targetState, $order, $useExistingPayment);
                $saved = $history->add();

                if (!$saved) {
                    throw new PrestaShopException('The order history could not be saved.');
                }

                $emailSent = null;
                if ($sendEmail) {
                    $templateVars = array();
                    if ($history->id_order_state == Configuration::get('PS_OS_SHIPPING') && $order->getShippingNumber()) {
                        $carrier = new Carrier((int) $order->id_carrier, (int) $order->id_lang);
                        if (Validate::isLoadedObject($carrier) && $carrier->url) {
                            $templateVars['{followup}'] = str_replace('@', $order->getShippingNumber(), $carrier->url);
                        }
                    }
                    $emailSent = (bool) $history->sendEmail($order, $templateVars);
                    if (!$emailSent) {
                        $results['warnings'][] = array(
                            'id_order' => (int) $order->id,
                            'warning' => 'The state was changed, but the customer email could not be sent.',
                        );
                    }
                }

                WmAuditLogger::log('bulk_status_change', array(
                    'old_state' => $oldState,
                    'new_state' => $targetState,
                    'email_requested' => (bool) $sendEmail,
                    'email_sent' => $emailSent,
                ), (int) $order->id);

                $results['success'][] = array('id_order' => (int) $order->id, 'skipped' => false);
            } catch (Exception $exception) {
                $results['errors'][] = array(
                    'id_order' => (int) $order->id,
                    'error' => $exception->getMessage(),
                );
                WmAuditLogger::log('bulk_status_error', array(
                    'new_state' => $targetState,
                    'error' => $exception->getMessage(),
                ), (int) $order->id);
            }
        }

        return $results;
    }

    private function normalizeIds(array $ids)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        sort($ids, SORT_NUMERIC);

        if (!$ids) {
            throw new PrestaShopException('Select at least one order.');
        }
        if (count($ids) > Wilden_manager::MAX_BULK_ORDERS) {
            throw new PrestaShopException('Too many orders selected for one operation.');
        }

        return $ids;
    }

    private function buildSnapshot(array $orders)
    {
        $parts = array();
        foreach ($orders as $order) {
            $parts[] = (int) $order['id_order'] . ':' . (int) $order['current_state'];
        }
        sort($parts, SORT_STRING);

        return implode(',', $parts);
    }

    private function sign(array $ids, $targetState, $timestamp, $snapshot)
    {
        $employeeId = isset($this->context->employee) ? (int) $this->context->employee->id : 0;
        $payload = implode(',', $ids) . '|' . (int) $targetState . '|' . (int) $timestamp . '|' .
            (string) $snapshot . '|' . $employeeId;

        return hash_hmac('sha256', $payload, _COOKIE_KEY_);
    }
}
