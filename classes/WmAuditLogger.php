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
}
