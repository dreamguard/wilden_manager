<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Irreversibly removes one order without an issued fiscal document.
 *
 * Safety model:
 * - Only an explicit list of PrestaShop core tables and tables owned by this
 *   module is touched. Tables are resolved by their exact prefixed name, so
 *   other installations sharing the database and third-party modules are
 *   never modified.
 * - Orders with a numbered invoice or a credit slip are refused. Orders with
 *   payments but without a numbered invoice are allowed; their payment rows are
 *   removed when no other order shares the same reference.
 * - Every affected table must be transactional. The operation runs in one
 *   transaction and is verified before commit.
 * - Stock is reinjected only when the module setting is enabled. The stock
 *   cache (reserved and physical quantities) is always recalculated for the
 *   affected products so it stays consistent after the order disappears.
 */
class WmOrderDeletionService
{
    const CONFIG_REINJECT_STOCK = 'WM_DELETE_REINJECT_STOCK';

    private $db;
    private $prefix;
    private $engines;

    public function __construct()
    {
        $this->db = Db::getInstance();
        $this->prefix = _DB_PREFIX_;
        $this->engines = null;
    }

    public static function isStockReinjectionEnabled()
    {
        return (bool) Configuration::getGlobalValue(self::CONFIG_REINJECT_STOCK);
    }

    /**
     * @param int $idOrder
     * @param int[] $allowedShopIds shops the current employee is working on
     */
    public function inspect($idOrder, array $allowedShopIds)
    {
        $idOrder = (int) $idOrder;
        $allowedShopIds = array_map('intval', $allowedShopIds);
        $row = WmDb::fetchRow(
            'SELECT o.id_order, o.id_shop, o.reference, o.invoice_number
             FROM `' . $this->prefix . 'orders` o
             WHERE o.id_order = ' . $idOrder
        );

        if (!$row || !in_array((int) $row['id_shop'], $allowedShopIds, true)) {
            return array(
                'exists' => false,
                'eligible' => false,
                'reason' => 'Order not found in the active store context.',
            );
        }

        $blocker = $this->findBlocker($idOrder, (int) $row['invoice_number']);

        return array(
            'exists' => true,
            'eligible' => $blocker === '',
            'has_invoice' => $blocker === 'invoice',
            'has_credit_slip' => $blocker === 'credit_slip',
            'id_order' => $idOrder,
            'id_shop' => (int) $row['id_shop'],
            'reference' => (string) $row['reference'],
            'reason' => $this->getBlockerMessage($blocker),
        );
    }

    public function inspectMany(array $ids, array $allowedShopIds)
    {
        $result = array();
        foreach (array_values(array_unique(array_filter(array_map('intval', $ids)))) as $idOrder) {
            $result[$idOrder] = $this->inspect($idOrder, $allowedShopIds);
        }

        return $result;
    }

    /**
     * @return array summary without any order identifier
     */
    public function delete($idOrder, array $allowedShopIds, $reinjectStock)
    {
        $idOrder = (int) $idOrder;
        $reinjectStock = (bool) $reinjectStock;
        $inspection = $this->inspect($idOrder, $allowedShopIds);
        if (empty($inspection['exists'])) {
            throw new RuntimeException('Order not found in the active store context.');
        }
        if (empty($inspection['eligible'])) {
            throw new RuntimeException((string) $inspection['reason']);
        }
        $idShop = (int) $inspection['id_shop'];

        $order = WmDb::fetchRow(
            'SELECT id_order, id_shop, id_cart, reference, current_state
             FROM `' . $this->prefix . 'orders`
             WHERE id_order = ' . $idOrder
        );
        if (!$order) {
            throw new RuntimeException('Order not found.');
        }

        $plan = $this->buildPlan($idOrder, (int) $order['id_cart'], (string) $order['reference']);
        $stockLines = $this->getStockLines($idOrder);
        $stockAction = $this->resolveStockAction($reinjectStock, (int) $order['current_state']);

        $this->assertTransactional($plan, $stockLines ? true : false);

        $this->db->execute('START TRANSACTION');
        try {
            $locked = WmDb::lockRow(
                'SELECT id_order, id_shop, invoice_number
                 FROM `' . $this->prefix . 'orders`
                 WHERE id_order = ' . $idOrder
            );
            if (!$locked || (int) $locked['id_shop'] !== $idShop) {
                throw new RuntimeException('The order changed and can no longer be deleted.');
            }
            $blocker = $this->findBlocker($idOrder, (int) $locked['invoice_number']);
            if ($blocker !== '') {
                throw new RuntimeException($this->getBlockerMessage($blocker));
            }

            $orderModel = new Order($idOrder);
            if (!Validate::isLoadedObject($orderModel)
                || (int) $orderModel->id_shop !== $idShop
                || !$orderModel->delete()
            ) {
                throw new RuntimeException('PrestaShop could not delete the order entity.');
            }

            foreach ($plan as $step) {
                if (!$this->executeStep($step)) {
                    throw new RuntimeException('A related record could not be deleted in ' . $step['table'] . '.');
                }
            }

            $reinjectedUnits = 0;
            if ($stockAction === 'reinject') {
                $reinjectedUnits = $this->reinjectStock($stockLines, $idShop);
            }
            $this->recalculateStockCache($stockLines, $idShop);

            $this->verifyDeletion($idOrder, $plan);
            $this->db->execute('COMMIT');
        } catch (Exception $exception) {
            $this->db->execute('ROLLBACK');
            throw $exception;
        } catch (Throwable $throwable) {
            $this->db->execute('ROLLBACK');
            throw $throwable;
        }

        return array(
            'id_shop' => $idShop,
            'stock_action' => $stockAction,
            'stock_reinjected' => $stockAction === 'reinject' && $reinjectedUnits > 0,
            'stock_units_reinjected' => $reinjectedUnits,
            'product_lines' => count($stockLines),
        );
    }

    /**
     * Returns '' when the order may be deleted, otherwise the blocker code.
     */
    private function findBlocker($idOrder, $orderInvoiceNumber)
    {
        if ((int) $orderInvoiceNumber > 0) {
            return 'invoice';
        }
        if ($this->tableExists('order_invoice') && WmDb::exists(
            'SELECT 1 FROM `' . $this->prefix . 'order_invoice`
             WHERE id_order = ' . (int) $idOrder . ' AND number > 0'
        )) {
            return 'invoice';
        }
        if ($this->tableExists('order_slip') && WmDb::exists(
            'SELECT 1 FROM `' . $this->prefix . 'order_slip`
             WHERE id_order = ' . (int) $idOrder
        )) {
            return 'credit_slip';
        }

        return '';
    }

    private function getBlockerMessage($blocker)
    {
        if ($blocker === 'invoice') {
            return 'Orders with an issued invoice cannot be deleted.';
        }
        if ($blocker === 'credit_slip') {
            return 'Orders with a credit slip cannot be deleted.';
        }

        return '';
    }

    /**
     * Ordered list of deletions limited to known tables. Children go first.
     */
    private function buildPlan($idOrder, $idCart, $reference)
    {
        $idOrder = (int) $idOrder;
        $detailIds = $this->getIds('order_detail', 'id_order_detail', 'id_order = ' . $idOrder);
        $invoiceIds = $this->getIds('order_invoice', 'id_order_invoice', 'id_order = ' . $idOrder);
        $returnIds = $this->getIds('order_return', 'id_order_return', 'id_order = ' . $idOrder);
        $threadIds = $this->getIds('customer_thread', 'id_customer_thread', 'id_order = ' . $idOrder);

        $referenceIsExclusive = $reference !== '' && !WmDb::exists(
            'SELECT 1 FROM `' . $this->prefix . 'orders`
             WHERE reference = \'' . pSQL($reference) . '\' AND id_order <> ' . $idOrder
        );
        $paymentIds = $referenceIsExclusive
            ? $this->getIds('order_payment', 'id_order_payment', "order_reference = '" . pSQL($reference) . "'")
            : array();

        $cartIsExclusive = $idCart > 0 && !WmDb::exists(
            'SELECT 1 FROM `' . $this->prefix . 'orders`
             WHERE id_cart = ' . (int) $idCart . ' AND id_order <> ' . $idOrder
        );
        $customizationIds = $cartIsExclusive
            ? $this->getIds('customization', 'id_customization', 'id_cart = ' . (int) $idCart)
            : array();

        $plan = array();
        // Children of order lines, invoices, returns and threads.
        $this->addStep($plan, 'order_detail_tax', 'id_order_detail', $detailIds);
        $this->addStep($plan, 'order_return_detail', 'id_order_return', $returnIds);
        $this->addStep($plan, 'order_return_detail', 'id_order_detail', $detailIds);
        $this->addStep($plan, 'order_invoice_tax', 'id_order_invoice', $invoiceIds);
        $this->addStep($plan, 'order_invoice_payment', 'id_order_invoice', $invoiceIds);
        $this->addStep($plan, 'order_invoice_payment', 'id_order_payment', $paymentIds);
        $this->addStep($plan, 'customer_message', 'id_customer_thread', $threadIds);

        // Rows owned directly by the order.
        foreach (array(
            'order_invoice_payment',
            'order_detail',
            'order_history',
            'order_carrier',
            'order_cart_rule',
            'order_invoice',
            'order_return',
            'message',
            'customer_thread',
            'wildenmanager_order_note',
            'wildenmanager_audit',
        ) as $table) {
            $this->addStep($plan, $table, 'id_order', array($idOrder));
        }

        // Payments recorded only for this order reference.
        $this->addStep($plan, 'order_payment', 'id_order_payment', $paymentIds);

        // Cart data used exclusively by this order.
        if ($cartIsExclusive) {
            $this->addStep($plan, 'customized_data', 'id_customization', $customizationIds);
            foreach (array('customization', 'cart_product', 'cart_cart_rule', 'specific_price', 'message', 'cart') as $table) {
                $this->addStep($plan, $table, 'id_cart', array((int) $idCart));
            }
        }

        // Diagnostic reviews owned by this module.
        if ($this->tableExists('wildenmanager_integrity_review')) {
            $patterns = array('%:o:' . $idOrder, '%:o:' . $idOrder . ':%');
            foreach ($detailIds as $idDetail) {
                $patterns[] = '%:od:' . (int) $idDetail;
            }
            $plan[] = array(
                'table' => 'wildenmanager_integrity_review',
                'column' => 'issue_key',
                'like' => $patterns,
            );
        }

        return $plan;
    }

    private function addStep(array &$plan, $table, $column, array $values)
    {
        $values = array_values(array_unique(array_filter(array_map('intval', $values))));
        if (!$values || !$this->columnExists($table, $column)) {
            return;
        }
        $plan[] = array('table' => $table, 'column' => $column, 'values' => $values);
    }

    private function stepWhere(array $step)
    {
        if (isset($step['like'])) {
            $parts = array();
            foreach ($step['like'] as $pattern) {
                $parts[] = '`issue_key` LIKE \'' . pSQL($pattern) . '\'';
            }

            return '(' . implode(' OR ', $parts) . ')';
        }

        return '`' . bqSQL($step['column']) . '` IN (' . implode(',', array_map('intval', $step['values'])) . ')';
    }

    private function executeStep(array $step)
    {
        return $this->db->execute(
            'DELETE FROM `' . bqSQL($this->prefix . $step['table']) . '` WHERE ' . $this->stepWhere($step)
        );
    }

    private function stepHasRows(array $step)
    {
        return WmDb::exists(
            'SELECT 1 FROM `' . bqSQL($this->prefix . $step['table']) . '` WHERE ' . $this->stepWhere($step)
        );
    }

    private function assertTransactional(array $plan, $touchesStock)
    {
        $tables = array('orders' => true);
        foreach ($plan as $step) {
            if ($this->stepHasRows($step)) {
                $tables[$step['table']] = true;
            }
        }
        if ($touchesStock) {
            $tables['stock_available'] = true;
            $tables['stock_mvt'] = true;
        }
        $engines = $this->getEngines();
        foreach (array_keys($tables) as $table) {
            $fullName = $this->prefix . $table;
            if (isset($engines[$fullName]) && strtoupper($engines[$fullName]) !== 'INNODB') {
                throw new RuntimeException('Deletion was stopped because a related table is not transactional: ' . $fullName);
            }
        }
    }

    /**
     * Product lines captured before deletion, with the quantity still out of stock.
     */
    private function getStockLines($idOrder)
    {
        $rows = $this->db->executeS(
            'SELECT product_id, product_attribute_id, product_quantity, product_quantity_reinjected
             FROM `' . $this->prefix . 'order_detail`
             WHERE id_order = ' . (int) $idOrder,
            true,
            false
        );
        $lines = array();
        foreach ((array) $rows as $row) {
            $idProduct = (int) $row['product_id'];
            if ($idProduct <= 0) {
                continue;
            }
            $lines[] = array(
                'id_product' => $idProduct,
                'id_product_attribute' => (int) $row['product_attribute_id'],
                'quantity' => max(0, (int) $row['product_quantity'] - (int) $row['product_quantity_reinjected']),
            );
        }

        return $lines;
    }

    /**
     * none     -> setting disabled
     * disabled -> setting enabled but shop stock management is off
     * already  -> order is cancelled or in error; PrestaShop already reinjected
     * reinject -> stock will be returned
     */
    private function resolveStockAction($reinjectStock, $currentState)
    {
        if (!$reinjectStock) {
            return 'none';
        }
        if (!Configuration::get('PS_STOCK_MANAGEMENT')) {
            return 'disabled';
        }
        $finalStates = array_filter(array(
            (int) Configuration::get('PS_OS_CANCELED'),
            (int) Configuration::get('PS_OS_ERROR'),
        ));
        if (in_array((int) $currentState, $finalStates, true)) {
            return 'already';
        }

        return 'reinject';
    }

    private function reinjectStock(array $lines, $idShop)
    {
        $reason = (int) Configuration::get('PS_STOCK_MVT_INC_EMPLOYEE_EDITION');
        $units = 0;
        foreach ($lines as $line) {
            if ($line['quantity'] <= 0 || !Product::existsInDatabase($line['id_product'], 'product')) {
                continue;
            }
            if ($line['id_product_attribute'] > 0
                && !Combination::existsInDatabase($line['id_product_attribute'], 'product_attribute')
            ) {
                continue;
            }
            $params = $reason > 0 ? array('id_stock_mvt_reason' => $reason) : array();
            $updated = StockAvailable::updateQuantity(
                $line['id_product'],
                $line['id_product_attribute'],
                $line['quantity'],
                (int) $idShop,
                $reason > 0,
                $params
            );
            if ($updated === false) {
                throw new RuntimeException('Stock could not be reinjected for one of the products.');
            }
            $units += $line['quantity'];
        }

        return $units;
    }

    /**
     * Recomputes reserved and physical quantities now that the order is gone.
     */
    private function recalculateStockCache(array $lines, $idShop)
    {
        if (!$lines || !class_exists('\PrestaShop\PrestaShop\Adapter\StockManager')) {
            return;
        }
        $manager = new \PrestaShop\PrestaShop\Adapter\StockManager();
        if (!method_exists($manager, 'updatePhysicalProductQuantity')) {
            return;
        }
        $done = array();
        foreach ($lines as $line) {
            if (isset($done[$line['id_product']])) {
                continue;
            }
            $done[$line['id_product']] = true;
            $manager->updatePhysicalProductQuantity(
                (int) $idShop,
                (int) Configuration::get('PS_OS_ERROR'),
                (int) Configuration::get('PS_OS_CANCELED'),
                (int) $line['id_product']
            );
        }
    }

    private function verifyDeletion($idOrder, array $plan)
    {
        if (WmDb::exists(
            'SELECT 1 FROM `' . $this->prefix . 'orders` WHERE id_order = ' . (int) $idOrder
        )) {
            throw new RuntimeException('The order record remains.');
        }
        foreach ($plan as $step) {
            if ($this->stepHasRows($step)) {
                throw new RuntimeException('A related order record remains in ' . $this->prefix . $step['table'] . '.');
            }
        }
    }

    private function getIds($table, $idColumn, $where)
    {
        if (!$this->columnExists($table, $idColumn)) {
            return array();
        }
        $rows = $this->db->executeS(
            'SELECT `' . bqSQL($idColumn) . '` FROM `' . bqSQL($this->prefix . $table) . '` WHERE ' . $where,
            true,
            false
        );

        return array_values(array_unique(array_filter(array_map(function ($row) use ($idColumn) {
            return isset($row[$idColumn]) ? (int) $row[$idColumn] : 0;
        }, (array) $rows))));
    }

    private function tableExists($table)
    {
        $engines = $this->getEngines();

        return isset($engines[$this->prefix . $table]);
    }

    private function columnExists($table, $column)
    {
        if (!$this->tableExists($table)) {
            return false;
        }

        return WmDb::exists(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = \'' . pSQL($this->prefix . $table) . '\'
               AND COLUMN_NAME = \'' . pSQL($column) . '\''
        );
    }

    /**
     * Engines of the known tables only, matched by exact name. No LIKE pattern is
     * used, so tables of other installations sharing the database never match.
     */
    private function getEngines()
    {
        if ($this->engines !== null) {
            return $this->engines;
        }
        $names = array();
        foreach (self::knownTables() as $table) {
            $names[] = '\'' . pSQL($this->prefix . $table) . '\'';
        }
        $rows = $this->db->executeS(
            'SELECT TABLE_NAME, ENGINE
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME IN (' . implode(',', $names) . ')',
            true,
            false
        );
        $this->engines = array();
        foreach ((array) $rows as $row) {
            $this->engines[(string) $row['TABLE_NAME']] = (string) $row['ENGINE'];
        }
        if (!isset($this->engines[$this->prefix . 'orders'])) {
            throw new RuntimeException('The database schema could not be inspected.');
        }

        return $this->engines;
    }

    private static function knownTables()
    {
        return array(
            'orders',
            'order_detail',
            'order_detail_tax',
            'order_history',
            'order_carrier',
            'order_cart_rule',
            'order_invoice',
            'order_invoice_tax',
            'order_invoice_payment',
            'order_payment',
            'order_return',
            'order_return_detail',
            'order_slip',
            'message',
            'customer_thread',
            'customer_message',
            'cart',
            'cart_product',
            'cart_cart_rule',
            'customization',
            'customized_data',
            'specific_price',
            'stock_available',
            'stock_mvt',
            'wildenmanager_order_note',
            'wildenmanager_audit',
            'wildenmanager_integrity_review',
        );
    }
}
