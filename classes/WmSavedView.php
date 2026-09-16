<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class WmSavedView
{
    const MAX_VIEWS_PER_EMPLOYEE = 25;

    public static function getForEmployee($idEmployee, $idShop)
    {
        return Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'wilden_manager_saved_view`
             WHERE id_employee = ' . (int) $idEmployee . '
               AND id_shop = ' . (int) $idShop . '
             ORDER BY is_default DESC, name ASC',
            true,
            false
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

    public static function getNativeForEmployee($idEmployee, $idShop)
    {
        $views = array();
        foreach ((array) self::getForEmployee($idEmployee, $idShop) as $row) {
            $state = json_decode((string) $row['filters_json'], true);
            $columns = json_decode((string) $row['columns_json'], true);
            if (!is_array($state) || empty($state['native_grid']) || !is_array($columns)) {
                continue;
            }
            $views[] = array(
                'id' => (int) $row['id_wilden_manager_saved_view'],
                'name' => (string) $row['name'],
                'state' => $state,
                'columns' => array_values($columns),
                'is_default' => (bool) $row['is_default'],
            );
        }

        return $views;
    }

    public static function getNative($idView, $idEmployee, $idShop)
    {
        $row = self::get($idView, $idEmployee, $idShop);
        if (!$row) {
            return false;
        }
        $state = json_decode((string) $row['filters_json'], true);

        return is_array($state) && !empty($state['native_grid']) ? $row : false;
    }

    public static function saveNative($idView, $name, array $state, array $columns, $idEmployee, $idShop, $isDefault)
    {
        $idView = (int) $idView;
        $idEmployee = (int) $idEmployee;
        $idShop = (int) $idShop;
        $now = date('Y-m-d H:i:s');
        $data = array(
            'name' => pSQL($name),
            'filters_json' => pSQL(json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'columns_json' => pSQL(json_encode(array_values($columns), JSON_UNESCAPED_UNICODE)),
            'is_default' => (int) $isDefault,
            'date_upd' => $now,
        );

        if ($idView > 0) {
            if (!self::get($idView, $idEmployee, $idShop)) {
                return false;
            }
            $saved = Db::getInstance()->update(
                'wilden_manager_saved_view',
                $data,
                'id_wilden_manager_saved_view = ' . $idView .
                ' AND id_employee = ' . $idEmployee .
                ' AND id_shop = ' . $idShop,
                0,
                false,
                false
            );
        } else {
            $count = count(self::getNativeForEmployee($idEmployee, $idShop));
            if ($count >= self::MAX_VIEWS_PER_EMPLOYEE) {
                return false;
            }
            $data['id_employee'] = $idEmployee;
            $data['id_shop'] = $idShop;
            $data['date_add'] = $now;
            $saved = Db::getInstance()->insert('wilden_manager_saved_view', $data, false, false);
            $idView = $saved ? (int) Db::getInstance()->Insert_ID() : 0;
        }

        if (!$saved || $idView <= 0) {
            return false;
        }
        if ($isDefault) {
            Db::getInstance()->update(
                'wilden_manager_saved_view',
                array('is_default' => 0, 'date_upd' => $now),
                'id_employee = ' . $idEmployee .
                ' AND id_shop = ' . $idShop .
                ' AND id_wilden_manager_saved_view != ' . $idView,
                0,
                false,
                false
            );
        }

        return $idView;
    }

    public static function getAllNative($limit = 250)
    {
        $rows = Db::getInstance()->executeS(
            'SELECT v.*, CONCAT(e.firstname, CHAR(32), e.lastname) AS employee_name, s.name AS shop_name
             FROM `' . _DB_PREFIX_ . 'wilden_manager_saved_view` v
             LEFT JOIN `' . _DB_PREFIX_ . 'employee` e ON e.id_employee = v.id_employee
             LEFT JOIN `' . _DB_PREFIX_ . 'shop` s ON s.id_shop = v.id_shop
             ORDER BY v.date_upd DESC
             LIMIT ' . max(1, min(500, (int) $limit)),
            true,
            false
        );

        return array_values(array_filter((array) $rows, function ($row) {
            $state = json_decode((string) $row['filters_json'], true);

            return is_array($state) && !empty($state['native_grid']);
        }));
    }

    public static function deleteAny($idView)
    {
        $row = Db::getInstance()->getRow(
            'SELECT `filters_json` FROM `' . _DB_PREFIX_ . 'wilden_manager_saved_view`
             WHERE `id_wilden_manager_saved_view` = ' . (int) $idView
        );
        $state = $row ? json_decode((string) $row['filters_json'], true) : null;
        if (!is_array($state) || empty($state['native_grid'])) {
            return false;
        }

        return Db::getInstance()->delete(
            'wilden_manager_saved_view',
            'id_wilden_manager_saved_view = ' . (int) $idView
        );
    }
}
