<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_5_0($module)
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
        if (!is_array($columns) || !in_array('internal_note', $columns, true)) {
            continue;
        }

        $columns = array_values(array_diff($columns, array('internal_note')));
        if (!WmGridPreference::save(
            $columns,
            (int) $row['id_employee'],
            (int) $row['id_shop']
        )) {
            return false;
        }
    }

    return true;
}
