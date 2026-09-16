<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class WmAuditLogger
{
    public static function log($action, array $details = array(), $idOrder = null)
    {
        $context = Context::getContext();
        $employeeId = isset($context->employee) ? (int) $context->employee->id : 0;
        $shopId = isset($context->shop) ? (int) $context->shop->id : 0;

        return Db::getInstance()->insert('wilden_manager_audit', array(
            'id_employee' => $employeeId,
            'id_shop' => $shopId,
            'id_order' => $idOrder ? (int) $idOrder : null,
            'action' => pSQL($action),
            'details_json' => pSQL(json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'date_add' => date('Y-m-d H:i:s'),
        ), true, true, Db::INSERT);
    }

    public static function getRecent($limit = 25, $idOrder = null)
    {
        $where = $idOrder ? ' WHERE a.id_order = ' . (int) $idOrder : '';

        return Db::getInstance()->executeS(
            'SELECT a.*, CONCAT(e.firstname, CHAR(32), e.lastname) AS employee_name
             FROM `' . _DB_PREFIX_ . 'wilden_manager_audit` a
             LEFT JOIN `' . _DB_PREFIX_ . 'employee` e ON e.id_employee = a.id_employee' .
             $where . '
             ORDER BY a.date_add DESC, a.id_wilden_manager_audit DESC
             LIMIT ' . (int) $limit
        );
    }

    public static function search(array $filters, $page = 1, $limit = 50, $idShop = null)
    {
        $page = max(1, (int) $page);
        $limit = max(10, min(100, (int) $limit));
        $offset = ($page - 1) * $limit;

        return Db::getInstance()->executeS(
            'SELECT a.*, CONCAT(e.firstname, CHAR(32), e.lastname) AS employee_name,
                    s.name AS shop_name
             FROM `' . _DB_PREFIX_ . 'wilden_manager_audit` a
             LEFT JOIN `' . _DB_PREFIX_ . 'employee` e ON e.id_employee = a.id_employee
             LEFT JOIN `' . _DB_PREFIX_ . 'shop` s ON s.id_shop = a.id_shop' .
            self::buildWhere($filters, $idShop) . '
             ORDER BY a.date_add DESC, a.id_wilden_manager_audit DESC
             LIMIT ' . $offset . ', ' . $limit,
            true,
            false
        );
    }

    public static function count(array $filters, $idShop = null)
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*)
             FROM `' . _DB_PREFIX_ . 'wilden_manager_audit` a' .
            self::buildWhere($filters, $idShop),
            false
        );
    }

    public static function getActions($idShop = null)
    {
        $where = (int) $idShop > 0 ? ' WHERE a.id_shop = ' . (int) $idShop : '';
        $rows = Db::getInstance()->executeS(
            'SELECT DISTINCT a.action
             FROM `' . _DB_PREFIX_ . 'wilden_manager_audit` a' . $where . '
             ORDER BY a.action ASC',
            true,
            false
        );

        return array_values(array_filter(array_map(function ($row) {
            return isset($row['action']) ? (string) $row['action'] : '';
        }, is_array($rows) ? $rows : array())));
    }

    public static function getEmployees($idShop = null)
    {
        $where = (int) $idShop > 0 ? ' WHERE a.id_shop = ' . (int) $idShop : '';

        return Db::getInstance()->executeS(
            'SELECT DISTINCT a.id_employee,
                    CONCAT(e.firstname, CHAR(32), e.lastname) AS employee_name
             FROM `' . _DB_PREFIX_ . 'wilden_manager_audit` a
             LEFT JOIN `' . _DB_PREFIX_ . 'employee` e ON e.id_employee = a.id_employee' .
            $where . '
             ORDER BY employee_name ASC, a.id_employee ASC',
            true,
            false
        );
    }

    private static function buildWhere(array $filters, $idShop = null)
    {
        $conditions = array();
        if ((int) $idShop > 0) {
            $conditions[] = 'a.id_shop = ' . (int) $idShop;
        }
        if (!empty($filters['action'])) {
            $conditions[] = "a.action = '" . pSQL((string) $filters['action']) . "'";
        }
        if (array_key_exists('id_employee', $filters) && $filters['id_employee'] !== null) {
            $conditions[] = 'a.id_employee = ' . (int) $filters['id_employee'];
        }
        if (!empty($filters['id_order'])) {
            $conditions[] = 'a.id_order = ' . (int) $filters['id_order'];
        }
        if (!empty($filters['date_from']) && self::isDate($filters['date_from'])) {
            $conditions[] = "a.date_add >= '" . pSQL($filters['date_from']) . " 00:00:00'";
        }
        if (!empty($filters['date_to']) && self::isDate($filters['date_to'])) {
            $conditions[] = "a.date_add <= '" . pSQL($filters['date_to']) . " 23:59:59'";
        }

        return $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
    }

    private static function isDate($value)
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value)) {
            return false;
        }

        $parts = array_map('intval', explode('-', (string) $value));

        return count($parts) === 3 && checkdate($parts[1], $parts[2], $parts[0]);
    }
}
