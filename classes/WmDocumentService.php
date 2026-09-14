<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class WmDocumentService
{
    private $context;
    private $repository;

    public function __construct(Context $context, WmOrderRepository $repository)
    {
        $this->context = $context;
        $this->repository = $repository;
    }

    public function preview(array $ids)
    {
        $documents = $this->getDocuments($ids);
        $orders = array();
        foreach ($documents['orders'] as $order) {
            $idOrder = (int) $order['id_order'];
            $orders[] = array(
                'id_order' => $idOrder,
                'reference' => (string) $order['reference'],
                'has_invoice' => isset($documents['invoice_orders'][$idOrder]),
                'has_delivery' => isset($documents['delivery_orders'][$idOrder]),
            );
        }

        return array(
            'orders' => $orders,
            'order_ids' => $documents['order_ids'],
            'invoice_count' => count($documents['invoices']),
            'delivery_count' => count($documents['delivery_slips']),
        );
    }

    public function getDocuments(array $ids)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        sort($ids, SORT_NUMERIC);
        if (!$ids) {
            throw new PrestaShopException('Select at least one order.');
        }
        if (count($ids) > Wilden_manager::MAX_DOCUMENT_ORDERS) {
            throw new PrestaShopException('Too many orders selected for one document operation.');
        }

        $orders = $this->repository->getOrderSummaries($ids);
        if (count($orders) !== count($ids)) {
            throw new PrestaShopException('One or more selected orders are not accessible.');
        }

        $invoices = array();
        $deliverySlips = array();
        $invoiceOrders = array();
        $deliveryOrders = array();
        foreach ($orders as $summary) {
            $idOrder = (int) $summary['id_order'];
            $order = new Order($idOrder);
            if (!Validate::isLoadedObject($order)) {
                throw new PrestaShopException('Order #' . $idOrder . ' could not be loaded.');
            }

            foreach ($order->getInvoicesCollection() as $invoice) {
                if ((int) $invoice->number > 0) {
                    $invoices[(int) $invoice->id] = $invoice;
                    $invoiceOrders[$idOrder] = true;
                }
                if ((int) $invoice->delivery_number > 0) {
                    $deliverySlips[(int) $invoice->id] = $invoice;
                    $deliveryOrders[$idOrder] = true;
                }
            }
        }

        return array(
            'orders' => $orders,
            'order_ids' => $ids,
            'invoices' => array_values($invoices),
            'delivery_slips' => array_values($deliverySlips),
            'invoice_orders' => $invoiceOrders,
            'delivery_orders' => $deliveryOrders,
        );
    }

    public function download(array $documents, $type)
    {
        if ($type === 'invoice') {
            $this->renderPdf($documents['invoices'], PDF::TEMPLATE_INVOICE, true);
            exit;
        }
        if ($type === 'delivery') {
            $this->renderPdf($documents['delivery_slips'], PDF::TEMPLATE_DELIVERY_SLIP, true);
            exit;
        }

        if (!$documents['invoices'] && !$documents['delivery_slips']) {
            throw new PrestaShopException('The selected orders do not have invoices or delivery slips.');
        }
        if (!class_exists('ZipArchive')) {
            throw new PrestaShopException('Combined document download requires the PHP Zip extension.');
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'wm_documents_');
        if (!$temporaryPath) {
            throw new PrestaShopException('The temporary document archive could not be created.');
        }
        $zip = new ZipArchive();
        if ($zip->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($temporaryPath);
            throw new PrestaShopException('The document archive could not be created.');
        }
        if ($documents['invoices']) {
            $zip->addFromString(
                'invoices.pdf',
                $this->renderPdf($documents['invoices'], PDF::TEMPLATE_INVOICE, false)
            );
        }
        if ($documents['delivery_slips']) {
            $zip->addFromString(
                'delivery-slips.pdf',
                $this->renderPdf($documents['delivery_slips'], PDF::TEMPLATE_DELIVERY_SLIP, false)
            );
        }
        $zip->close();

        if (ob_get_level() && ob_get_length() > 0) {
            ob_clean();
        }
        $filename = 'wilden-documents-' . date('Y-m-d-His') . '.zip';
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($temporaryPath));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        readfile($temporaryPath);
        @unlink($temporaryPath);
        exit;
    }

    private function renderPdf(array $objects, $template, $display)
    {
        if (!$objects) {
            throw new PrestaShopException('No requested documents were found in the selected orders.');
        }
        if ($template === PDF::TEMPLATE_INVOICE) {
            Hook::exec('actionPDFInvoiceRender', array('order_invoice_list' => $objects));
        }

        $pdf = new PDF($objects, $template, $this->context->smarty);

        return $pdf->render($display);
    }
}
