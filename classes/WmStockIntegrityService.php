<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Read-only stock, cancellation and refund diagnostics.
 *
 * The service deliberately reports only evidence stored by PrestaShop. It does
 * not attempt to reconstruct historical stock from the current available
 * quantity, because purchases, manual corrections, packs and custom products
 * make that reconstruction unreliable.
 */
class WmStockIntegrityService
{
    private $context;
    private $allowedShopIds;
    private $hasCloneTable;

    public function __construct(Context $context)
    {
        $this->context = $context;
        $this->allowedShopIds = array_map('intval', Shop::getContextListShopID());
        if (!$this->allowedShopIds && isset($context->shop->id)) {
            $this->allowedShopIds = array((int) $context->shop->id);
        }
        $this->hasCloneTable = $this->tableExists(_DB_PREFIX_ . 'idxrcustomproduct_clones');
    }

    public function scan($issueType, $severity, $productKind, $page, $limit)
    {
        $issueType = $this->sanitizeIssueType($issueType);
        $severity = $this->sanitizeSeverity($severity);
        $productKind = $this->sanitizeProductKind($productKind);
        $page = max(1, (int) $page);
        $limit = max(10, min(100, (int) $limit));
        $union = $this->getUnionSql();
        $where = $this->buildFilter($issueType, $severity, $productKind);

        $summaryRows = Db::getInstance()->executeS(
            'SELECT issue_type, severity, product_kind, COUNT(*) AS total
             FROM (' . $union . ') wm_stock_integrity
             GROUP BY issue_type, severity, product_kind',
            true,
            false
        );

        $summary = array('high' => 0, 'medium' => 0, 'info' => 0);
        $byType = array();
        $byKind = array('standard' => 0, 'pack' => 0, 'custom' => 0, 'order' => 0);
        $total = 0;
        foreach ((array) $summaryRows as $summaryRow) {
            $rowSeverity = (string) $summaryRow['severity'];
            $rowType = (string) $summaryRow['issue_type'];
            $rowKind = (string) $summaryRow['product_kind'];
            $rowTotal = (int) $summaryRow['total'];
            if (isset($summary[$rowSeverity])) {
                $summary[$rowSeverity] += $rowTotal;
            }
            if (!isset($byType[$rowType])) {
                $byType[$rowType] = 0;
            }
            $byType[$rowType] += $rowTotal;
            if (isset($byKind[$rowKind])) {
                $byKind[$rowKind] += $rowTotal;
            }
            if ((!$issueType || $issueType === $rowType)
                && (!$severity || $severity === $rowSeverity)
                && (!$productKind || $productKind === $rowKind)
            ) {
                $total += $rowTotal;
            }
        }

        $pages = max(1, (int) ceil($total / $limit));
        $page = min($page, $pages);
        $offset = ($page - 1) * $limit;
        $rows = Db::getInstance()->executeS(
            'SELECT severity, issue_type, product_kind, id_order, reference, order_date,
                    id_order_detail, id_product, id_product_attribute, product_name,
                    ordered_quantity, refunded_quantity, returned_quantity,
                    reinjected_quantity, value_a, value_b, value_c
             FROM (' . $union . ') wm_stock_integrity' . $where . '
             ORDER BY severity_rank DESC, order_date DESC, id_order DESC, id_product DESC
             LIMIT ' . $offset . ', ' . $limit,
            true,
            false
        );

        return array(
            'issues' => $rows ?: array(),
            'summary' => $summary,
            'by_type' => $byType,
            'by_kind' => $byKind,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => $pages,
        );
    }

    public function getForExport($issueType, $severity, $productKind, $maxRows)
    {
        $where = $this->buildFilter(
            $this->sanitizeIssueType($issueType),
            $this->sanitizeSeverity($severity),
            $this->sanitizeProductKind($productKind)
        );

        return Db::getInstance()->executeS(
            'SELECT severity, issue_type, product_kind, id_order, reference, order_date,
                    id_order_detail, id_product, id_product_attribute, product_name,
                    ordered_quantity, refunded_quantity, returned_quantity,
                    reinjected_quantity, value_a, value_b, value_c
             FROM (' . $this->getUnionSql() . ') wm_stock_integrity' . $where . '
             ORDER BY severity_rank DESC, order_date DESC, id_order DESC, id_product DESC
             LIMIT ' . max(1, (int) $maxRows),
            true,
            false
        ) ?: array();
    }

    private function getUnionSql()
    {
        $prefix = _DB_PREFIX_;
        $shops = implode(',', $this->allowedShopIds ?: array(0));
        $cloneJoin = $this->hasCloneTable
            ? " LEFT JOIN `{$prefix}idxrcustomproduct_clones` wm_clone ON wm_clone.id_clon = od.product_id"
            : '';
        $cloneStockJoin = $this->hasCloneTable
            ? " LEFT JOIN `{$prefix}idxrcustomproduct_clones` wm_clone ON wm_clone.id_clon = sa.id_product"
            : '';
        $customCondition = $this->hasCloneTable ? 'wm_clone.id_clon IS NOT NULL' : '0 = 1';
        $orderKind = "CASE WHEN {$customCondition} THEN 'custom'
            WHEN p.cache_is_pack = 1 OR p.product_type = 'pack' THEN 'pack'
            ELSE 'standard' END";
        $stockKind = "CASE WHEN {$customCondition} THEN 'custom'
            WHEN p.cache_is_pack = 1 OR p.product_type = 'pack' THEN 'pack'
            ELSE 'standard' END";
        $orderFields = "o.id_order, o.reference, o.date_add AS order_date,
            od.id_order_detail, od.product_id AS id_product,
            od.product_attribute_id AS id_product_attribute, od.product_name,
            od.product_quantity AS ordered_quantity,
            od.product_quantity_refunded AS refunded_quantity,
            od.product_quantity_return AS returned_quantity,
            od.product_quantity_reinjected AS reinjected_quantity";
        $orderJoins = " FROM `{$prefix}order_detail` od
            INNER JOIN `{$prefix}orders` o ON o.id_order = od.id_order
            LEFT JOIN `{$prefix}product` p ON p.id_product = od.product_id" . $cloneJoin;
        $cancelledState = (int) Configuration::get('PS_OS_CANCELED');
        if ($cancelledState <= 0 && defined('_PS_OS_CANCELED_')) {
            $cancelledState = (int) _PS_OS_CANCELED_;
        }
        if ($cancelledState <= 0) {
            $cancelledState = 6;
        }

        $queries = array(
            "SELECT 3 severity_rank, 'high' severity, 'refunded_exceeds_ordered' issue_type,
                    {$orderKind} product_kind, {$orderFields},
                    od.product_quantity value_a, od.product_quantity_refunded value_b, NULL value_c
             {$orderJoins}
             WHERE o.id_shop IN ({$shops})
               AND od.product_quantity_refunded > od.product_quantity",
            "SELECT 3 severity_rank, 'high' severity, 'returned_exceeds_ordered' issue_type,
                    {$orderKind} product_kind, {$orderFields},
                    od.product_quantity value_a, od.product_quantity_return value_b, NULL value_c
             {$orderJoins}
             WHERE o.id_shop IN ({$shops})
               AND od.product_quantity_return > od.product_quantity",
            "SELECT 3 severity_rank, 'high' severity, 'reinjected_exceeds_ordered' issue_type,
                    {$orderKind} product_kind, {$orderFields},
                    od.product_quantity value_a, od.product_quantity_reinjected value_b, NULL value_c
             {$orderJoins}
             WHERE o.id_shop IN ({$shops})
               AND od.product_quantity_reinjected > od.product_quantity",
            "SELECT 3 severity_rank, 'high' severity, 'credit_slip_exceeds_ordered' issue_type,
                    {$orderKind} product_kind, {$orderFields},
                    od.product_quantity value_a, wm_slip.quantity value_b, NULL value_c
             {$orderJoins}
             INNER JOIN (
                SELECT id_order_detail, SUM(product_quantity) quantity
                FROM `{$prefix}order_slip_detail`
                GROUP BY id_order_detail
             ) wm_slip ON wm_slip.id_order_detail = od.id_order_detail
             WHERE o.id_shop IN ({$shops}) AND wm_slip.quantity > od.product_quantity",
            "SELECT 3 severity_rank, 'high' severity, 'return_request_exceeds_ordered' issue_type,
                    {$orderKind} product_kind, {$orderFields},
                    od.product_quantity value_a, wm_return.quantity value_b, NULL value_c
             {$orderJoins}
             INNER JOIN (
                SELECT id_order_detail, SUM(product_quantity) quantity
                FROM `{$prefix}order_return_detail`
                GROUP BY id_order_detail
             ) wm_return ON wm_return.id_order_detail = od.id_order_detail
             WHERE o.id_shop IN ({$shops}) AND wm_return.quantity > od.product_quantity",
            "SELECT 2 severity_rank, 'medium' severity, 'refund_without_credit_slip' issue_type,
                    {$orderKind} product_kind, {$orderFields},
                    od.product_quantity_refunded value_a, COALESCE(wm_slip.quantity, 0) value_b,
                    od.product_quantity value_c
             {$orderJoins}
             LEFT JOIN (
                SELECT id_order_detail, SUM(product_quantity) quantity
                FROM `{$prefix}order_slip_detail`
                GROUP BY id_order_detail
             ) wm_slip ON wm_slip.id_order_detail = od.id_order_detail
             WHERE o.id_shop IN ({$shops}) AND od.product_quantity_refunded > 0
               AND COALESCE(wm_slip.quantity, 0) < od.product_quantity_refunded",
            "SELECT 1 severity_rank, 'info' severity, 'refund_not_reinjected' issue_type,
                    {$orderKind} product_kind, {$orderFields},
                    GREATEST(od.product_quantity_refunded, od.product_quantity_return) value_a,
                    od.product_quantity_reinjected value_b, od.product_quantity value_c
             {$orderJoins}
             WHERE o.id_shop IN ({$shops})
               AND od.product_quantity_refunded <= od.product_quantity
               AND od.product_quantity_return <= od.product_quantity
               AND od.product_quantity_reinjected <= od.product_quantity
               AND GREATEST(od.product_quantity_refunded, od.product_quantity_return)
                    > od.product_quantity_reinjected",
            "SELECT 1 severity_rank, 'info' severity, 'cancelled_restock_evidence_missing' issue_type,
                    'order' product_kind, o.id_order, o.reference, o.date_add order_date,
                    NULL id_order_detail, NULL id_product, NULL id_product_attribute,
                    NULL product_name, SUM(od.product_quantity) ordered_quantity,
                    SUM(od.product_quantity_refunded) refunded_quantity,
                    SUM(od.product_quantity_return) returned_quantity,
                    SUM(od.product_quantity_reinjected) reinjected_quantity,
                    SUM(od.product_quantity) value_a,
                    SUM(od.product_quantity_reinjected) value_b, NULL value_c
             FROM `{$prefix}orders` o
             INNER JOIN `{$prefix}order_detail` od ON od.id_order = o.id_order
             LEFT JOIN `{$prefix}product` p ON p.id_product = od.product_id
             WHERE o.id_shop IN ({$shops}) AND o.current_state = {$cancelledState}
               AND (p.id_product IS NULL OR p.product_type <> 'virtual')
               AND EXISTS (
                    SELECT 1 FROM `{$prefix}order_history` oh
                    INNER JOIN `{$prefix}order_state` os ON os.id_order_state = oh.id_order_state
                    WHERE oh.id_order = o.id_order AND os.logable = 1
               )
               AND NOT EXISTS (
                    SELECT 1 FROM `{$prefix}stock_mvt` sm
                    WHERE sm.id_order = o.id_order AND sm.`sign` = 1
               )
             GROUP BY o.id_order, o.reference, o.date_add
             HAVING SUM(od.product_quantity_reinjected) < SUM(od.product_quantity)",
            "SELECT 2 severity_rank, 'medium' severity, 'stock_cache_mismatch' issue_type,
                    {$stockKind} product_kind, NULL id_order, NULL reference,
                    p.date_upd order_date, NULL id_order_detail, sa.id_product,
                    sa.id_product_attribute, pl.name product_name,
                    NULL ordered_quantity, NULL refunded_quantity, NULL returned_quantity,
                    NULL reinjected_quantity, sa.quantity value_a,
                    sa.reserved_quantity value_b, sa.physical_quantity value_c
             FROM `{$prefix}stock_available` sa
             INNER JOIN `{$prefix}product` p ON p.id_product = sa.id_product
             LEFT JOIN `{$prefix}product_lang` pl ON pl.id_product = sa.id_product
                AND pl.id_shop = sa.id_shop AND pl.id_lang = " . (int) $this->context->language->id .
             $cloneStockJoin . "
             WHERE sa.id_shop IN ({$shops})
               AND sa.physical_quantity <> sa.quantity + sa.reserved_quantity
               AND p.product_type <> 'virtual'
               AND NOT (p.product_type = 'combinations' AND sa.id_product_attribute = 0)
               AND NOT (p.cache_is_pack = 1 OR p.product_type = 'pack')",
            "SELECT 1 severity_rank, 'info' severity, 'pack_stock_cache_mismatch' issue_type,
                    {$stockKind} product_kind, NULL id_order, NULL reference,
                    p.date_upd order_date, NULL id_order_detail, sa.id_product,
                    sa.id_product_attribute, pl.name product_name,
                    NULL ordered_quantity, NULL refunded_quantity, NULL returned_quantity,
                    NULL reinjected_quantity, sa.quantity value_a,
                    sa.reserved_quantity value_b, sa.physical_quantity value_c
             FROM `{$prefix}stock_available` sa
             INNER JOIN `{$prefix}product` p ON p.id_product = sa.id_product
             LEFT JOIN `{$prefix}product_lang` pl ON pl.id_product = sa.id_product
                AND pl.id_shop = sa.id_shop AND pl.id_lang = " . (int) $this->context->language->id .
             $cloneStockJoin . "
             WHERE sa.id_shop IN ({$shops})
               AND sa.physical_quantity <> sa.quantity + sa.reserved_quantity
               AND p.product_type <> 'virtual'
               AND (p.cache_is_pack = 1 OR p.product_type = 'pack')",
        );

        return implode(' UNION ALL ', $queries);
    }

    private function buildFilter($issueType, $severity, $productKind)
    {
        $conditions = array();
        if ($issueType) {
            $conditions[] = "issue_type = '" . pSQL($issueType) . "'";
        }
        if ($severity) {
            $conditions[] = "severity = '" . pSQL($severity) . "'";
        }
        if ($productKind) {
            $conditions[] = "product_kind = '" . pSQL($productKind) . "'";
        }

        return $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
    }

    private function sanitizeIssueType($issueType)
    {
        $issueType = (string) $issueType;
        return in_array($issueType, array(
            'refunded_exceeds_ordered',
            'returned_exceeds_ordered',
            'reinjected_exceeds_ordered',
            'credit_slip_exceeds_ordered',
            'return_request_exceeds_ordered',
            'refund_without_credit_slip',
            'refund_not_reinjected',
            'cancelled_restock_evidence_missing',
            'stock_cache_mismatch',
            'pack_stock_cache_mismatch',
        ), true) ? $issueType : '';
    }

    private function sanitizeSeverity($severity)
    {
        $severity = (string) $severity;
        return in_array($severity, array('high', 'medium', 'info'), true) ? $severity : '';
    }

    private function sanitizeProductKind($productKind)
    {
        $productKind = (string) $productKind;
        return in_array($productKind, array('standard', 'pack', 'custom', 'order'), true)
            ? $productKind
            : '';
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
