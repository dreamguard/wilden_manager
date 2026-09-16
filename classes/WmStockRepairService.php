<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Narrow repair for an unequivocal physical stock cache mismatch.
 *
 * It changes only physical_quantity, which is derived from the untouched
 * sellable and reserved quantities. Every write is guarded by an exact
 * snapshot and performed inside a transaction.
 */
class WmStockRepairService
{
    private $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    public function repair($issueKey, $snapshotHash)
    {
        if (!preg_match('/^stock:stock_cache_mismatch:p:(\d+):a:(\d+):s:(\d+)$/', (string) $issueKey, $matches)) {
            throw new InvalidArgumentException('This incident is not eligible for controlled repair.');
        }
        $idProduct = (int) $matches[1];
        $idAttribute = (int) $matches[2];
        $idShop = (int) $matches[3];
        $allowedShopIds = array_map('intval', Shop::getContextListShopID());
        if (!$allowedShopIds && isset($this->context->shop->id)) {
            $allowedShopIds = array((int) $this->context->shop->id);
        }
        if (!in_array($idShop, $allowedShopIds, true)) {
            throw new RuntimeException('The incident does not belong to the active shop context.');
        }

        $row = $this->getCurrentRow($idProduct, $idAttribute, $idShop);
        if (!$row || !(int) $row['active']) {
            throw new RuntimeException('Only active products with a current stock row can be repaired.');
        }
        if ((int) $row['cache_is_pack'] === 1
            || in_array((string) $row['product_type'], array('pack', 'virtual'), true)
            || (int) $row['is_custom_clone'] === 1
            || ($idAttribute > 0 && !(int) $row['combination_exists'])
        ) {
            throw new RuntimeException('Packs, virtual products, custom clones and orphan combinations are excluded.');
        }

        $issue = array(
            'issue_type' => 'stock_cache_mismatch',
            'id_shop' => $idShop,
            'id_order' => '',
            'id_order_detail' => '',
            'id_product' => $idProduct,
            'id_product_attribute' => $idAttribute,
            'value_a' => (int) $row['quantity'],
            'value_b' => (int) $row['reserved_quantity'],
            'value_c' => (int) $row['physical_quantity'],
        );
        $currentHash = WmIntegrityReview::snapshotHash($issue);
        if (!hash_equals($currentHash, (string) $snapshotHash)) {
            throw new RuntimeException('The stock data changed after the analysis. Refresh the report and review it again.');
        }
        if (!WmIntegrityReview::isConfirmed('stock_integrity', $issueKey, $currentHash, $idShop)) {
            throw new RuntimeException('The current incident snapshot must be marked as confirmed before repair.');
        }

        $expectedReserved = $this->calculateReserved($idProduct, $idAttribute, $idShop);
        if ($expectedReserved !== (int) $row['reserved_quantity']) {
            throw new RuntimeException('Reserved stock no longer matches pending orders. This case requires manual review.');
        }
        $expectedPhysical = (int) $row['quantity'] + (int) $row['reserved_quantity'];
        if ($expectedPhysical === (int) $row['physical_quantity']) {
            throw new RuntimeException('The incident has already been resolved.');
        }

        $db = Db::getInstance();
        $db->execute('START TRANSACTION');
        try {
            $updated = $db->execute(
                'UPDATE `' . _DB_PREFIX_ . 'stock_available`
                 SET physical_quantity = ' . $expectedPhysical . '
                 WHERE id_stock_available = ' . (int) $row['id_stock_available'] . '
                   AND quantity = ' . (int) $row['quantity'] . '
                   AND reserved_quantity = ' . (int) $row['reserved_quantity'] . '
                   AND physical_quantity = ' . (int) $row['physical_quantity']
            );
            $after = $this->getCurrentRow($idProduct, $idAttribute, $idShop);
            if (!$updated || !$after
                || (int) $after['quantity'] !== (int) $row['quantity']
                || (int) $after['reserved_quantity'] !== (int) $row['reserved_quantity']
                || (int) $after['physical_quantity'] !== $expectedPhysical
            ) {
                throw new RuntimeException('The guarded stock update could not be verified.');
            }
            $db->execute('COMMIT');
        } catch (Exception $exception) {
            $db->execute('ROLLBACK');
            throw $exception;
        }

        WmAuditLogger::log('stock_integrity_cache_repaired', array(
            'issue_key' => $issueKey,
            'id_product' => $idProduct,
            'id_product_attribute' => $idAttribute,
            'quantity_unchanged' => (int) $row['quantity'],
            'reserved_unchanged' => (int) $row['reserved_quantity'],
            'physical_before' => (int) $row['physical_quantity'],
            'physical_after' => $expectedPhysical,
        ));

        return array(
            'id_product' => $idProduct,
            'id_product_attribute' => $idAttribute,
            'quantity' => (int) $row['quantity'],
            'reserved_quantity' => (int) $row['reserved_quantity'],
            'physical_before' => (int) $row['physical_quantity'],
            'physical_after' => $expectedPhysical,
        );
    }

    private function getCurrentRow($idProduct, $idAttribute, $idShop)
    {
        $cloneJoin = $this->tableExists(_DB_PREFIX_ . 'idxrcustomproduct_clones')
            ? ' LEFT JOIN `' . _DB_PREFIX_ . 'idxrcustomproduct_clones` c ON c.id_clon = sa.id_product'
            : '';
        $customField = $cloneJoin ? 'CASE WHEN c.id_clon IS NULL THEN 0 ELSE 1 END' : '0';

        return Db::getInstance()->getRow(
            'SELECT sa.id_stock_available, sa.quantity, sa.reserved_quantity, sa.physical_quantity,
                    p.active, p.product_type, p.cache_is_pack,
                    ' . $customField . ' AS is_custom_clone,
                    CASE WHEN sa.id_product_attribute = 0 OR pa.id_product_attribute IS NOT NULL THEN 1 ELSE 0 END AS combination_exists
             FROM `' . _DB_PREFIX_ . 'stock_available` sa
             INNER JOIN `' . _DB_PREFIX_ . 'product` p ON p.id_product = sa.id_product
             LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute` pa
                ON pa.id_product_attribute = sa.id_product_attribute' . $cloneJoin . '
             WHERE sa.id_product = ' . (int) $idProduct . '
               AND sa.id_product_attribute = ' . (int) $idAttribute . '
               AND sa.id_shop = ' . (int) $idShop,
            false
        );
    }

    private function calculateReserved($idProduct, $idAttribute, $idShop)
    {
        $cancelled = (int) Configuration::get('PS_OS_CANCELED');
        $error = (int) Configuration::get('PS_OS_ERROR');

        return (int) Db::getInstance()->getValue(
            'SELECT COALESCE(SUM(od.product_quantity - od.product_quantity_refunded), 0)
             FROM `' . _DB_PREFIX_ . 'order_detail` od
             INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order = od.id_order
             INNER JOIN `' . _DB_PREFIX_ . 'order_state` os ON os.id_order_state = o.current_state
             WHERE od.product_id = ' . (int) $idProduct . '
               AND od.product_attribute_id = ' . (int) $idAttribute . '
               AND o.id_shop = ' . (int) $idShop . '
               AND os.shipped <> 1
               AND (o.valid = 1 OR o.current_state NOT IN (' . $cancelled . ', ' . $error . '))',
            false
        );
    }

    private function tableExists($table)
    {
        $rows = Db::getInstance()->executeS("SHOW TABLES LIKE '" . pSQL($table) . "'");
        foreach ((array) $rows as $row) {
            if ((string) reset($row) === (string) $table) {
                return true;
            }
        }

        return false;
    }
}
