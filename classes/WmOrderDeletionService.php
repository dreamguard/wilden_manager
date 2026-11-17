<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Irreversibly removes one non-invoiced order and its database relations.
 *
 * Stock is deliberately not reinjected. The service refuses to touch affected
 * non-transactional tables so a failure can always roll the whole operation back.
 */
class WmOrderDeletionService
{
    private $db;
    private $prefix;
    private $schema;

    public function __construct()
    {
        $this->db = Db::getInstance();
        $this->prefix = _DB_PREFIX_;
        $this->schema = null;
    }

    public function inspect($idOrder, $idShop)
    {
        $idOrder = (int) $idOrder;
        $idShop = (int) $idShop;
        $row = $this->db->getRow(
            'SELECT o.id_order, o.id_shop, o.reference, o.invoice_number,
                    EXISTS(
                        SELECT 1 FROM `' . $this->prefix . 'order_invoice` oi
                        WHERE oi.id_order = o.id_order
                    ) AS has_invoice
             FROM `' . $this->prefix . 'orders` o
             WHERE o.id_order = ' . $idOrder,
            false
        );

        if (!$row || (int) $row['id_shop'] !== $idShop) {
            return array(
                'exists' => false,
                'eligible' => false,
                'reason' => 'Order not found in the active store.',
            );
        }

        $hasInvoice = (int) $row['invoice_number'] > 0 || !empty($row['has_invoice']);

        return array(
            'exists' => true,
            'eligible' => !$hasInvoice,
            'has_invoice' => $hasInvoice,
            'id_order' => $idOrder,
            'reference' => (string) $row['reference'],
            'reason' => $hasInvoice ? 'Orders with an invoice cannot be deleted.' : '',
        );
    }

    public function inspectMany(array $ids, $idShop)
    {
        $result = array();
        foreach (array_values(array_unique(array_filter(array_map('intval', $ids)))) as $idOrder) {
            $result[$idOrder] = $this->inspect($idOrder, $idShop);
        }

        return $result;
    }

    public function delete($idOrder, $idShop)
    {
        $idOrder = (int) $idOrder;
        $idShop = (int) $idShop;
        $inspection = $this->inspect($idOrder, $idShop);
        if (empty($inspection['exists'])) {
            throw new RuntimeException('Order not found in the active store.');
        }
        if (empty($inspection['eligible'])) {
            throw new RuntimeException((string) $inspection['reason']);
        }

        $order = $this->db->getRow(
            'SELECT id_order, id_shop, id_cart, reference, invoice_number
             FROM `' . $this->prefix . 'orders`
             WHERE id_order = ' . $idOrder,
            false
        );
        if (!$order) {
            throw new RuntimeException('Order not found.');
        }

        $idCart = (int) $order['id_cart'];
        $reference = (string) $order['reference'];
        $cartIsExclusive = $idCart > 0 && !(bool) $this->db->getValue(
            'SELECT 1 FROM `' . $this->prefix . 'orders`
             WHERE id_cart = ' . $idCart . ' AND id_order <> ' . $idOrder,
            false
        );
        $referenceIsExclusive = $reference !== '' && !(bool) $this->db->getValue(
            'SELECT 1 FROM `' . $this->prefix . 'orders`
             WHERE reference = \'' . pSQL($reference) . '\' AND id_order <> ' . $idOrder,
            false
        );

        $relations = array(
            'id_order_detail' => $this->getIds('order_detail', 'id_order_detail', 'id_order = ' . $idOrder),
            'id_order_return' => $this->getIds('order_return', 'id_order_return', 'id_order = ' . $idOrder),
            'id_order_slip' => $this->getIds('order_slip', 'id_order_slip', 'id_order = ' . $idOrder),
            'id_order_invoice' => $this->getIds('order_invoice', 'id_order_invoice', 'id_order = ' . $idOrder),
            'id_order_carrier' => $this->getIds('order_carrier', 'id_order_carrier', 'id_order = ' . $idOrder),
            'id_order_cart_rule' => $this->getIds('order_cart_rule', 'id_order_cart_rule', 'id_order = ' . $idOrder),
            'id_order_shipment' => $this->getIds('order_shipment', 'id_order_shipment', 'id_order = ' . $idOrder),
            'id_customer_thread' => $this->getIds('customer_thread', 'id_customer_thread', 'id_order = ' . $idOrder),
        );
        $relations['id_order_slip_detail'] = $relations['id_order_detail']
            ? $this->getIds(
                'order_slip_detail',
                'id_order_slip_detail',
                'id_order_detail IN (' . implode(',', array_map('intval', $relations['id_order_detail'])) . ')'
            )
            : array();
        if ($relations['id_order_invoice']) {
            throw new RuntimeException('Orders with an invoice cannot be deleted.');
        }
        if ($cartIsExclusive) {
            $relations['id_cart'] = array($idCart);
            $relations['id_customization'] = $this->getIds(
                'customization',
                'id_customization',
                'id_cart = ' . $idCart
            );
        }
        if ($referenceIsExclusive) {
            $relations['id_order_payment'] = $this->getIds(
                'order_payment',
                'id_order_payment',
                "order_reference = '" . pSQL($reference) . "'"
            );
        }

        $directTables = $this->findTablesWithColumn('id_order');
        $targets = array();
        foreach ($relations as $column => $values) {
            if (!$values) {
                continue;
            }
            foreach ($this->findTablesWithColumn($column) as $table => $engine) {
                if ($table === $this->prefix . 'orders') {
                    continue;
                }
                $targets[$table][$column] = $values;
            }
        }
        foreach ($directTables as $table => $engine) {
            if ($table !== $this->prefix . 'orders') {
                $targets[$table]['id_order'] = array($idOrder);
            }
        }

        $this->addReviewTargets($targets, $idOrder, $relations['id_order_detail']);
        $this->sortTargetsForDeletion($targets);
        $this->assertAffectedTablesAreTransactional($targets, $idOrder);

        $this->db->execute('START TRANSACTION');
        try {
            $locked = $this->db->getRow(
                'SELECT id_order, id_shop, invoice_number
                 FROM `' . $this->prefix . 'orders`
                 WHERE id_order = ' . $idOrder . ' FOR UPDATE',
                false
            );
            if (!$locked || (int) $locked['id_shop'] !== $idShop || (int) $locked['invoice_number'] > 0) {
                throw new RuntimeException('The order changed and can no longer be deleted.');
            }
            if ((bool) $this->db->getValue(
                'SELECT 1 FROM `' . $this->prefix . 'order_invoice` WHERE id_order = ' . $idOrder,
                false
            )) {
                throw new RuntimeException('The order acquired an invoice and cannot be deleted.');
            }

            foreach ($targets as $table => $conditions) {
                foreach ($conditions as $column => $values) {
                    if (!$this->deleteValues($table, $column, $values)) {
                        throw new RuntimeException('A related record could not be deleted.');
                    }
                }
            }

            if ($referenceIsExclusive && !$this->deletePaymentReference($reference)) {
                throw new RuntimeException('The order payment could not be deleted.');
            }
            if (!$this->db->execute(
                'DELETE FROM `' . $this->prefix . 'orders` WHERE id_order = ' . $idOrder
            )) {
                throw new RuntimeException('The order could not be deleted.');
            }

            $this->verifyDeletion(
                $idOrder,
                $directTables,
                $targets,
                $idCart,
                $cartIsExclusive,
                $reference,
                $referenceIsExclusive
            );
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
            'stock_reinjected' => false,
        );
    }

    private function addReviewTargets(array &$targets, $idOrder, array $detailIds)
    {
        $table = $this->prefix . 'wilden_manager_integrity_review';
        if (!isset($this->getSchema()[$table])) {
            return;
        }
        $patterns = array(
            '%:o:' . (int) $idOrder,
            '%:o:' . (int) $idOrder . ':%',
        );
        foreach ($detailIds as $idDetail) {
            $patterns[] = '%:od:' . (int) $idDetail;
        }
        $targets[$table]['__issue_key_like'] = $patterns;
    }

    private function sortTargetsForDeletion(array &$targets)
    {
        $owners = array(
            $this->prefix . 'order_detail' => 10,
            $this->prefix . 'order_return' => 20,
            $this->prefix . 'order_slip' => 20,
            $this->prefix . 'order_invoice' => 20,
            $this->prefix . 'customer_thread' => 30,
            $this->prefix . 'customization' => 30,
            $this->prefix . 'order_payment' => 40,
            $this->prefix . 'cart' => 50,
        );
        uksort($targets, function ($left, $right) use ($owners) {
            $leftPriority = isset($owners[$left]) ? $owners[$left] : 0;
            $rightPriority = isset($owners[$right]) ? $owners[$right] : 0;
            if ($leftPriority === $rightPriority) {
                return strcmp($left, $right);
            }

            return $leftPriority < $rightPriority ? -1 : 1;
        });
    }

    private function assertAffectedTablesAreTransactional(array $targets, $idOrder)
    {
        $schema = $this->getSchema();
        $targets[$this->prefix . 'orders'] = array('id_order' => array((int) $idOrder));
        foreach ($targets as $table => $conditions) {
            if (!isset($schema[$table])) {
                continue;
            }
            if (strtoupper((string) $schema[$table]['engine']) !== 'INNODB' && $this->targetHasRows($table, $conditions)) {
                throw new RuntimeException('Deletion was stopped because a related table is not transactional: ' . $table);
            }
        }
    }

    private function targetHasRows($table, array $conditions)
    {
        foreach ($conditions as $column => $values) {
            if ($column === '__issue_key_like') {
                foreach ($values as $pattern) {
                    if ((bool) $this->db->getValue(
                        'SELECT 1 FROM `' . bqSQL($table) . '` WHERE issue_key LIKE \'' . pSQL($pattern) . '\' LIMIT 1',
                        false
                    )) {
                        return true;
                    }
                }
                continue;
            }
            if ((bool) $this->db->getValue(
                'SELECT 1 FROM `' . bqSQL($table) . '` WHERE `' . bqSQL($column) . '` IN (' .
                implode(',', array_map('intval', $values)) . ') LIMIT 1',
                false
            )) {
                return true;
            }
        }

        return false;
    }

    private function deleteValues($table, $column, array $values)
    {
        if ($column === '__issue_key_like') {
            foreach ($values as $pattern) {
                if (!$this->db->execute(
                    'DELETE FROM `' . bqSQL($table) . '` WHERE issue_key LIKE \'' . pSQL($pattern) . '\''
                )) {
                    return false;
                }
            }

            return true;
        }
        if (!$values) {
            return true;
        }

        return $this->db->execute(
            'DELETE FROM `' . bqSQL($table) . '` WHERE `' . bqSQL($column) . '` IN (' .
            implode(',', array_map('intval', $values)) . ')'
        );
    }

    private function deletePaymentReference($reference)
    {
        if ($reference === '' || !isset($this->getSchema()[$this->prefix . 'order_payment'])) {
            return true;
        }

        return $this->db->execute(
            'DELETE FROM `' . $this->prefix . 'order_payment`
             WHERE order_reference = \'' . pSQL($reference) . '\''
        );
    }

    private function verifyDeletion(
        $idOrder,
        array $directTables,
        array $targets,
        $idCart,
        $cartIsExclusive,
        $reference,
        $referenceIsExclusive
    )
    {
        foreach ($directTables as $table => $engine) {
            if ((bool) $this->db->getValue(
                'SELECT 1 FROM `' . bqSQL($table) . '` WHERE id_order = ' . (int) $idOrder . ' LIMIT 1',
                false
            )) {
                throw new RuntimeException('A related order record remains in ' . $table . '.');
            }
        }
        foreach ($targets as $table => $conditions) {
            if ($this->targetHasRows($table, $conditions)) {
                throw new RuntimeException('An indirect order relation remains in ' . $table . '.');
            }
        }
        if ($cartIsExclusive && $idCart > 0) {
            foreach ($this->findTablesWithColumn('id_cart') as $table => $engine) {
                if ($table === $this->prefix . 'orders') {
                    continue;
                }
                if ((bool) $this->db->getValue(
                    'SELECT 1 FROM `' . bqSQL($table) . '` WHERE id_cart = ' . (int) $idCart . ' LIMIT 1',
                    false
                )) {
                    throw new RuntimeException('A related cart record remains in ' . $table . '.');
                }
            }
        }
        if ($referenceIsExclusive && $reference !== '' && (bool) $this->db->getValue(
            'SELECT 1 FROM `' . $this->prefix . 'order_payment`
             WHERE order_reference = \'' . pSQL($reference) . '\' LIMIT 1',
            false
        )) {
            throw new RuntimeException('A related payment record remains.');
        }
    }

    private function getIds($table, $idColumn, $where)
    {
        $fullTable = $this->prefix . $table;
        if (!isset($this->getSchema()[$fullTable]) || !isset($this->getSchema()[$fullTable]['columns'][$idColumn])) {
            return array();
        }
        $rows = $this->db->executeS(
            'SELECT `' . bqSQL($idColumn) . '` FROM `' . bqSQL($fullTable) . '` WHERE ' . $where,
            true,
            false
        );

        return array_values(array_unique(array_filter(array_map(function ($row) use ($idColumn) {
            return isset($row[$idColumn]) ? (int) $row[$idColumn] : 0;
        }, (array) $rows))));
    }

    private function findTablesWithColumn($column)
    {
        $tables = array();
        foreach ($this->getSchema() as $table => $metadata) {
            if (isset($metadata['columns'][$column])) {
                $tables[$table] = $metadata['engine'];
            }
        }

        return $tables;
    }

    private function getSchema()
    {
        if ($this->schema !== null) {
            return $this->schema;
        }
        $this->schema = array();
        $tables = $this->db->executeS(
            'SELECT TABLE_NAME, ENGINE
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME LIKE \'' . pSQL($this->prefix) . '%\'',
            true,
            false
        );
        foreach ((array) $tables as $table) {
            $name = (string) $table['TABLE_NAME'];
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
                continue;
            }
            $this->schema[$name] = array(
                'engine' => (string) $table['ENGINE'],
                'columns' => array(),
            );
        }
        if (!$this->schema) {
            throw new RuntimeException('The database schema could not be inspected.');
        }
        $columns = $this->db->executeS(
            'SELECT TABLE_NAME, COLUMN_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME LIKE \'' . pSQL($this->prefix) . '%\'',
            true,
            false
        );
        foreach ((array) $columns as $column) {
            $table = (string) $column['TABLE_NAME'];
            $name = (string) $column['COLUMN_NAME'];
            if (isset($this->schema[$table])) {
                $this->schema[$table]['columns'][$name] = true;
            }
        }

        return $this->schema;
    }
}
