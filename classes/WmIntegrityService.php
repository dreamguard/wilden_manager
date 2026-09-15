<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class WmIntegrityService
{
    private $context;
    private $allowedShopIds;

    public function __construct(Context $context)
    {
        $this->context = $context;
        $this->allowedShopIds = array_map('intval', Shop::getContextListShopID());
        if (!$this->allowedShopIds && isset($context->shop->id)) {
            $this->allowedShopIds = array((int) $context->shop->id);
        }
    }

    public function scan($issueType, $severity, $page, $limit)
    {
        $issueType = $this->sanitizeIssueType($issueType);
        $severity = $this->sanitizeSeverity($severity);
        $page = max(1, (int) $page);
        $limit = max(10, min(100, (int) $limit));
        $offset = ($page - 1) * $limit;
        $where = $this->buildFilter($issueType, $severity);
        $union = $this->getUnionSql();

        $summaryRows = Db::getInstance()->executeS(
            'SELECT issue_type, severity, COUNT(*) AS total
             FROM (' . $union . ') wm_integrity
             GROUP BY issue_type, severity',
            true,
            false
        );
        $rows = Db::getInstance()->executeS(
            'SELECT severity, issue_type, id_order, reference, order_date, value_a, value_b
             FROM (' . $union . ') wm_integrity' . $where . '
             ORDER BY severity_rank DESC, id_order DESC, issue_type ASC
             LIMIT ' . $offset . ', ' . $limit,
            true,
            false
        );

        $summary = array('high' => 0, 'medium' => 0, 'info' => 0);
        $byType = array();
        $total = 0;
        foreach ((array) $summaryRows as $summaryRow) {
            $rowSeverity = (string) $summaryRow['severity'];
            $rowType = (string) $summaryRow['issue_type'];
            $rowTotal = (int) $summaryRow['total'];
            if (isset($summary[$rowSeverity])) {
                $summary[$rowSeverity] += $rowTotal;
            }
            $byType[$rowType] = $rowTotal;
            if ((!$issueType || $issueType === $rowType) && (!$severity || $severity === $rowSeverity)) {
                $total += $rowTotal;
            }
        }

        return array(
            'issues' => $rows ?: array(),
            'summary' => $summary,
            'by_type' => $byType,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => max(1, (int) ceil($total / $limit)),
        );
    }

    public function getForExport($issueType, $severity, $maxRows)
    {
        $where = $this->buildFilter(
            $this->sanitizeIssueType($issueType),
            $this->sanitizeSeverity($severity)
        );

        return Db::getInstance()->executeS(
            'SELECT severity, issue_type, id_order, reference, order_date, value_a, value_b
             FROM (' . $this->getUnionSql() . ') wm_integrity' . $where . '
             ORDER BY severity_rank DESC, id_order DESC, issue_type ASC
             LIMIT ' . max(1, (int) $maxRows),
            true,
            false
        ) ?: array();
    }

    private function buildFilter($issueType, $severity)
    {
        $conditions = array();
        if ($issueType) {
            $conditions[] = "issue_type = '" . pSQL($issueType) . "'";
        }
        if ($severity) {
            $conditions[] = "severity = '" . pSQL($severity) . "'";
        }

        return $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
    }

    private function sanitizeIssueType($issueType)
    {
        $issueType = (string) $issueType;
        return in_array($issueType, array(
            'missing_history',
            'state_history_mismatch',
            'delivery_address_missing',
            'invoice_address_missing',
            'customer_missing',
            'customer_incomplete',
            'invoice_number_without_date',
            'delivery_number_without_date',
            'delivery_address_deleted',
            'invoice_address_deleted',
            'customer_deleted',
        ), true) ? $issueType : '';
    }

    private function sanitizeSeverity($severity)
    {
        $severity = (string) $severity;
        return in_array($severity, array('high', 'medium', 'info'), true) ? $severity : '';
    }

    private function getUnionSql()
    {
        $prefix = _DB_PREFIX_;
        $shops = implode(',', $this->allowedShopIds ?: array(0));
        $baseFields = "o.id_order, o.reference, o.date_add AS order_date";
        $latestHistory = '(SELECT oh.id_order, oh.id_order_state
            FROM `' . $prefix . 'order_history` oh
            INNER JOIN (
                SELECT id_order, MAX(id_order_history) AS max_id
                FROM `' . $prefix . 'order_history`
                GROUP BY id_order
            ) wm_last ON wm_last.max_id = oh.id_order_history)';

        $queries = array(
            "SELECT 3 AS severity_rank, 'high' AS severity, 'missing_history' AS issue_type,
                    $baseFields, o.current_state AS value_a, NULL AS value_b
             FROM `{$prefix}orders` o
             LEFT JOIN $latestHistory lh ON lh.id_order = o.id_order
             WHERE o.id_shop IN ($shops) AND lh.id_order IS NULL",
            "SELECT 3 AS severity_rank, 'high' AS severity, 'state_history_mismatch' AS issue_type,
                    $baseFields, o.current_state AS value_a, lh.id_order_state AS value_b
             FROM `{$prefix}orders` o
             INNER JOIN $latestHistory lh ON lh.id_order = o.id_order
             WHERE o.id_shop IN ($shops) AND o.current_state <> lh.id_order_state",
            "SELECT 3 AS severity_rank, 'high' AS severity, 'delivery_address_missing' AS issue_type,
                    $baseFields, o.id_address_delivery AS value_a, NULL AS value_b
             FROM `{$prefix}orders` o
             LEFT JOIN `{$prefix}address` a ON a.id_address = o.id_address_delivery
             WHERE o.id_shop IN ($shops) AND a.id_address IS NULL",
            "SELECT 3 AS severity_rank, 'high' AS severity, 'invoice_address_missing' AS issue_type,
                    $baseFields, o.id_address_invoice AS value_a, NULL AS value_b
             FROM `{$prefix}orders` o
             LEFT JOIN `{$prefix}address` a ON a.id_address = o.id_address_invoice
             WHERE o.id_shop IN ($shops) AND a.id_address IS NULL",
            "SELECT 3 AS severity_rank, 'high' AS severity, 'customer_missing' AS issue_type,
                    $baseFields, o.id_customer AS value_a, NULL AS value_b
             FROM `{$prefix}orders` o
             LEFT JOIN `{$prefix}customer` c ON c.id_customer = o.id_customer
             WHERE o.id_shop IN ($shops) AND c.id_customer IS NULL",
            "SELECT 2 AS severity_rank, 'medium' AS severity, 'customer_incomplete' AS issue_type,
                    $baseFields, o.id_customer AS value_a,
                    CONCAT_WS(', ', IF(TRIM(c.firstname) = '', 'firstname', NULL),
                        IF(TRIM(c.lastname) = '', 'lastname', NULL), IF(TRIM(c.email) = '', 'email', NULL)) AS value_b
             FROM `{$prefix}orders` o
             INNER JOIN `{$prefix}customer` c ON c.id_customer = o.id_customer
             WHERE o.id_shop IN ($shops)
               AND (TRIM(c.firstname) = '' OR TRIM(c.lastname) = '' OR TRIM(c.email) = '')",
            "SELECT 2 AS severity_rank, 'medium' AS severity, 'invoice_number_without_date' AS issue_type,
                    $baseFields, oi.id_order_invoice AS value_a, oi.number AS value_b
             FROM `{$prefix}orders` o
             INNER JOIN `{$prefix}order_invoice` oi ON oi.id_order = o.id_order
             WHERE o.id_shop IN ($shops) AND oi.number > 0
               AND (oi.date_add IS NULL OR oi.date_add <= '1000-01-01 00:00:00')",
            "SELECT 2 AS severity_rank, 'medium' AS severity, 'delivery_number_without_date' AS issue_type,
                    $baseFields, oi.id_order_invoice AS value_a, oi.delivery_number AS value_b
             FROM `{$prefix}orders` o
             INNER JOIN `{$prefix}order_invoice` oi ON oi.id_order = o.id_order
             WHERE o.id_shop IN ($shops) AND oi.delivery_number > 0
               AND (oi.delivery_date IS NULL OR oi.delivery_date <= '1000-01-01 00:00:00')",
            "SELECT 1 AS severity_rank, 'info' AS severity, 'delivery_address_deleted' AS issue_type,
                    $baseFields, a.id_address AS value_a, NULL AS value_b
             FROM `{$prefix}orders` o
             INNER JOIN `{$prefix}address` a ON a.id_address = o.id_address_delivery
             WHERE o.id_shop IN ($shops) AND a.deleted = 1",
            "SELECT 1 AS severity_rank, 'info' AS severity, 'invoice_address_deleted' AS issue_type,
                    $baseFields, a.id_address AS value_a, NULL AS value_b
             FROM `{$prefix}orders` o
             INNER JOIN `{$prefix}address` a ON a.id_address = o.id_address_invoice
             WHERE o.id_shop IN ($shops) AND a.deleted = 1",
            "SELECT 1 AS severity_rank, 'info' AS severity, 'customer_deleted' AS issue_type,
                    $baseFields, c.id_customer AS value_a, NULL AS value_b
             FROM `{$prefix}orders` o
             INNER JOIN `{$prefix}customer` c ON c.id_customer = o.id_customer
             WHERE o.id_shop IN ($shops) AND c.deleted = 1",
        );

        return implode(' UNION ALL ', $queries);
    }
}
