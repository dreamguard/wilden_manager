<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Human review metadata for diagnostic findings.
 *
 * This class never changes orders, products or stock. It only stores the
 * employee's assessment in a module-owned table.
 */
class WmIntegrityReview
{
    const STATUS_REVIEWED = 'reviewed';
    const STATUS_JUSTIFIED = 'justified';
    const STATUS_CONFIRMED = 'confirmed';

    public static function attach($scope, array $issues)
    {
        if (!$issues) {
            return $issues;
        }

        $keys = array();
        foreach ($issues as &$issue) {
            $issue['review_key'] = self::buildKey($scope, $issue);
            $issue['snapshot_hash'] = self::snapshotHash($issue);
            $issue['review_status'] = '';
            $issue['review_note'] = '';
            $issue['review_employee'] = '';
            $issue['review_date'] = '';
            $keys[] = $issue['review_key'];
        }
        unset($issue);

        $quoted = array_map(function ($key) {
            return "'" . pSQL($key) . "'";
        }, array_values(array_unique($keys)));
        $rows = Db::getInstance()->executeS(
            'SELECT r.issue_key, r.status, r.note, r.date_upd,
                    CONCAT(e.firstname, CHAR(32), e.lastname) AS employee_name
             FROM `' . _DB_PREFIX_ . 'wilden_manager_integrity_review` r
             LEFT JOIN `' . _DB_PREFIX_ . 'employee` e ON e.id_employee = r.id_employee
             WHERE r.scope = \'' . pSQL($scope) . '\'
               AND r.issue_key IN (' . implode(',', $quoted) . ')',
            true,
            false
        );

        $reviews = array();
        foreach ((array) $rows as $row) {
            $reviews[(string) $row['issue_key']] = $row;
        }
        foreach ($issues as &$issue) {
            if (!isset($reviews[$issue['review_key']])) {
                continue;
            }
            $review = $reviews[$issue['review_key']];
            $issue['review_status'] = (string) $review['status'];
            $issue['review_note'] = (string) $review['note'];
            $issue['review_employee'] = trim((string) $review['employee_name']);
            $issue['review_date'] = (string) $review['date_upd'];
        }
        unset($issue);

        return $issues;
    }

    public static function save($scope, $issueKey, $issueType, $status, $note, $snapshotHash, $idShop)
    {
        $scope = (string) $scope;
        $issueKey = trim((string) $issueKey);
        $issueType = Tools::substr(trim((string) $issueType), 0, 64);
        $status = (string) $status;
        $note = Tools::substr(trim((string) $note), 0, 2000);
        $snapshotHash = strtolower(trim((string) $snapshotHash));
        $idShop = (int) $idShop;

        if (!in_array($scope, array('order_integrity', 'stock_integrity'), true)
            || !preg_match('/^[a-z0-9:_-]{5,191}$/', $issueKey)
            || !preg_match('/^[a-z0-9_]{3,64}$/', $issueType)
            || !in_array($status, self::statuses(), true)
            || !preg_match('/^[a-f0-9]{64}$/', $snapshotHash)
            || $idShop <= 0
        ) {
            throw new InvalidArgumentException('Invalid review data.');
        }
        if (in_array($status, array(self::STATUS_JUSTIFIED, self::STATUS_CONFIRMED), true) && $note === '') {
            throw new InvalidArgumentException('A justification is required for this status.');
        }

        $context = Context::getContext();
        $employeeId = isset($context->employee) ? (int) $context->employee->id : 0;
        $now = date('Y-m-d H:i:s');
        $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'wilden_manager_integrity_review`
                (`id_shop`, `scope`, `issue_key`, `issue_type`, `status`, `note`, `snapshot_hash`,
                 `id_employee`, `date_add`, `date_upd`)
                VALUES (' . $idShop . ', \'' . pSQL($scope) . '\', \'' . pSQL($issueKey) . '\',
                        \'' . pSQL($issueType) . '\', \'' . pSQL($status) . '\', \'' . pSQL($note) . '\',
                        \'' . pSQL($snapshotHash) . '\', ' . $employeeId . ', \'' . pSQL($now) . '\', \'' . pSQL($now) . '\')
                ON DUPLICATE KEY UPDATE
                    `issue_type` = VALUES(`issue_type`), `status` = VALUES(`status`),
                    `note` = VALUES(`note`), `snapshot_hash` = VALUES(`snapshot_hash`),
                    `id_employee` = VALUES(`id_employee`), `date_upd` = VALUES(`date_upd`)';

        if (!Db::getInstance()->execute($sql)) {
            throw new RuntimeException('The review could not be saved.');
        }

        return array('status' => $status, 'note' => $note, 'date_upd' => $now);
    }

    public static function isConfirmed($scope, $issueKey, $snapshotHash, $idShop)
    {
        return (bool) Db::getInstance()->getValue(
            'SELECT 1 FROM `' . _DB_PREFIX_ . 'wilden_manager_integrity_review`
             WHERE id_shop = ' . (int) $idShop . '
               AND scope = \'' . pSQL((string) $scope) . '\'
               AND issue_key = \'' . pSQL((string) $issueKey) . '\'
               AND status = \'' . self::STATUS_CONFIRMED . '\'
               AND snapshot_hash = \'' . pSQL((string) $snapshotHash) . '\'',
            false
        );
    }

    public static function buildKey($scope, array $issue)
    {
        $type = preg_replace('/[^a-z0-9_]/', '', (string) $issue['issue_type']);
        if ($scope === 'order_integrity') {
            return 'order:' . $type . ':o:' . (int) $issue['id_order'] . ':v:' . (int) $issue['value_a'];
        }
        if (!empty($issue['id_order_detail'])) {
            return 'stock:' . $type . ':od:' . (int) $issue['id_order_detail'];
        }
        if (!empty($issue['id_product'])) {
            return 'stock:' . $type . ':p:' . (int) $issue['id_product'] . ':a:' .
                (int) $issue['id_product_attribute'] . ':s:' . (int) $issue['id_shop'];
        }

        return 'stock:' . $type . ':o:' . (int) $issue['id_order'];
    }

    public static function snapshotHash(array $issue)
    {
        $fields = array(
            'issue_type', 'id_shop', 'id_order', 'id_order_detail', 'id_product',
            'id_product_attribute', 'value_a', 'value_b', 'value_c',
            'ordered_quantity', 'refunded_quantity', 'returned_quantity', 'reinjected_quantity',
        );
        $snapshot = array();
        foreach ($fields as $field) {
            $snapshot[$field] = isset($issue[$field]) ? (string) $issue[$field] : '';
        }

        return hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public static function statuses()
    {
        return array(self::STATUS_REVIEWED, self::STATUS_JUSTIFIED, self::STATUS_CONFIRMED);
    }
}
