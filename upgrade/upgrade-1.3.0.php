<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_3_0($module)
{
    if (!$module instanceof Wilden_manager
        || !$module->registerHook('actionOrderGridDefinitionModifier')
        || !$module->registerHook('actionOrderGridQueryBuilderModifier')
        || !$module->registerHook('displayBackOfficeHeader')) {
        return false;
    }

    $rows = Db::getInstance()->executeS(
        'SELECT id_employee, id_shop, columns_json
         FROM `' . _DB_PREFIX_ . 'wilden_manager_grid_preference`',
        true,
        false
    );
    if (!is_array($rows)) {
        return false;
    }

    foreach ($rows as $row) {
        $columns = json_decode($row['columns_json'], true);
        if (!is_array($columns)) {
            continue;
        }

        $updated = array();
        foreach ($columns as $column) {
            $updated[] = $column;
            if ($column === 'customer' && !in_array('customer_email', $columns, true)) {
                $updated[] = 'customer_email';
            }
            if ($column === 'payment' && !in_array('shipping_method', $columns, true)) {
                $updated[] = 'shipping_method';
            }
        }
        if (!in_array('customer_email', $updated, true)) {
            $updated[] = 'customer_email';
        }
        if (!in_array('shipping_method', $updated, true)) {
            $updated[] = 'shipping_method';
        }

        if (!WmGridPreference::save(
            array_values(array_unique($updated)),
            (int) $row['id_employee'],
            (int) $row['id_shop']
        )) {
            return false;
        }
    }

    return true;
}
