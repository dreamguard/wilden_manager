<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class WmSavedView
{
    public static function getForEmployee($idEmployee, $idShop)
    {
        return Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'wilden_manager_saved_view`
             WHERE id_employee = ' . (int) $idEmployee . '
               AND id_shop = ' . (int) $idShop . '
             ORDER BY is_default DESC, name ASC'
        );
    }

    public static function get($idView, $idEmployee, $idShop)
    {
        return Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'wilden_manager_saved_view`
             WHERE id_wilden_manager_saved_view = ' . (int) $idView . '
               AND id_employee = ' . (int) $idEmployee . '
               AND id_shop = ' . (int) $idShop
        );
    }

    public static function getDefault($idEmployee, $idShop)
    {
        return Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'wilden_manager_saved_view`
             WHERE id_employee = ' . (int) $idEmployee . '
               AND id_shop = ' . (int) $idShop . '
               AND is_default = 1
             ORDER BY id_wilden_manager_saved_view DESC'
        );
    }

    public static function save($name, array $filters, array $columns, $idEmployee, $idShop, $isDefault)
    {
        if ($isDefault) {
            Db::getInstance()->update(
                'wilden_manager_saved_view',
                array('is_default' => 0),
                'id_employee = ' . (int) $idEmployee . ' AND id_shop = ' . (int) $idShop
            );
        }

        $now = date('Y-m-d H:i:s');

        return Db::getInstance()->insert('wilden_manager_saved_view', array(
            'id_employee' => (int) $idEmployee,
            'id_shop' => (int) $idShop,
            'name' => pSQL($name),
            'filters_json' => pSQL(json_encode($filters, JSON_UNESCAPED_UNICODE)),
            'columns_json' => pSQL(json_encode($columns, JSON_UNESCAPED_UNICODE)),
            'is_default' => (int) $isDefault,
            'date_add' => $now,
            'date_upd' => $now,
        ));
    }

    public static function delete($idView, $idEmployee, $idShop)
    {
        return Db::getInstance()->delete(
            'wilden_manager_saved_view',
            'id_wilden_manager_saved_view = ' . (int) $idView .
            ' AND id_employee = ' . (int) $idEmployee .
            ' AND id_shop = ' . (int) $idShop
        );
    }
}
