<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class WmOrderNote
{
    public static function get($idOrder)
    {
        return (string) Db::getInstance()->getValue(
            'SELECT note FROM `' . _DB_PREFIX_ . 'wilden_manager_order_note`
             WHERE id_order = ' . (int) $idOrder
        );
    }

    public static function save($idOrder, $note, $idEmployee)
    {
        $now = date('Y-m-d H:i:s');
        $existingId = (int) Db::getInstance()->getValue(
            'SELECT id_wilden_manager_order_note
             FROM `' . _DB_PREFIX_ . 'wilden_manager_order_note`
             WHERE id_order = ' . (int) $idOrder
        );

        if ($existingId) {
            return Db::getInstance()->update('wilden_manager_order_note', array(
                'note' => pSQL($note, true),
                'id_employee' => (int) $idEmployee,
                'date_upd' => $now,
            ), 'id_wilden_manager_order_note = ' . $existingId);
        }

        return Db::getInstance()->insert('wilden_manager_order_note', array(
            'id_order' => (int) $idOrder,
            'note' => pSQL($note, true),
            'id_employee' => (int) $idEmployee,
            'date_add' => $now,
            'date_upd' => $now,
        ));
    }
}
