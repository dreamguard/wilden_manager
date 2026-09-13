<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class WmGridPreference
{
    public static function get($idEmployee, $idShop)
    {
        $json = Db::getInstance()->getValue(
            'SELECT columns_json FROM `' . _DB_PREFIX_ . 'wilden_manager_grid_preference`
             WHERE id_employee = ' . (int) $idEmployee . '
               AND id_shop = ' . (int) $idShop,
            false
        );

        if (!$json) {
            return array();
        }

        $columns = json_decode($json, true);

        return is_array($columns) ? array_values($columns) : array();
    }

    public static function save(array $columns, $idEmployee, $idShop)
    {
        $data = array(
            'columns_json' => pSQL(json_encode(array_values($columns))),
            'date_upd' => date('Y-m-d H:i:s'),
        );
        $where = 'id_employee = ' . (int) $idEmployee . ' AND id_shop = ' . (int) $idShop;

        if (Db::getInstance()->getValue(
            'SELECT id_wilden_manager_grid_preference
             FROM `' . _DB_PREFIX_ . 'wilden_manager_grid_preference`
             WHERE ' . $where,
            false
        )) {
            return Db::getInstance()->update('wilden_manager_grid_preference', $data, $where, 0, false, false);
        }

        $data['id_employee'] = (int) $idEmployee;
        $data['id_shop'] = (int) $idShop;
        $data['date_add'] = $data['date_upd'];

        return Db::getInstance()->insert('wilden_manager_grid_preference', $data, false, false);
    }
}
