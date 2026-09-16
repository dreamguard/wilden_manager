<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_9_0($module)
{
    $indexName = 'idx_wm_audit_shop';
    $indexes = Db::getInstance()->executeS(
        "SHOW INDEX FROM `" . _DB_PREFIX_ . "wilden_manager_audit` WHERE Key_name = '" . pSQL($indexName) . "'",
        true,
        false
    );
    if (!$indexes && !Db::getInstance()->execute(
        'ALTER TABLE `' . _DB_PREFIX_ . 'wilden_manager_audit` ' .
        'ADD KEY `' . bqSQL($indexName) . '` (`id_shop`, `date_add`)'
    )) {
        return false;
    }

    return $module instanceof Wilden_manager
        && $module->registerHook('actionOrderGridDefinitionModifier')
        && $module->registerHook('actionOrderGridQueryBuilderModifier')
        && $module->registerHook('displayBackOfficeHeader');
}
