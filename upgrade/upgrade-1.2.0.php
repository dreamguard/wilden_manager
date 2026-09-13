<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_2_0($module)
{
    $engine = _MYSQL_ENGINE_;
    $prefix = _DB_PREFIX_;
    $query = "CREATE TABLE IF NOT EXISTS `{$prefix}wilden_manager_grid_preference` (
        `id_wilden_manager_grid_preference` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `id_employee` INT UNSIGNED NOT NULL,
        `id_shop` INT UNSIGNED NOT NULL,
        `columns_json` TEXT NOT NULL,
        `date_add` DATETIME NOT NULL,
        `date_upd` DATETIME NOT NULL,
        PRIMARY KEY (`id_wilden_manager_grid_preference`),
        UNIQUE KEY `uniq_wm_grid_owner` (`id_employee`, `id_shop`)
    ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    return Db::getInstance()->execute($query)
        && $module->registerHook('actionOrderGridDefinitionModifier')
        && $module->registerHook('displayBackOfficeHeader');
}
