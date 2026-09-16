<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_10_0($module)
{
    if (!$module instanceof Wilden_manager) {
        return false;
    }

    $query = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . "wilden_manager_profile_permission` (
        `id_profile` INT UNSIGNED NOT NULL,
        `can_export` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
        `can_view_diagnostics` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
        `date_add` DATETIME NOT NULL,
        `date_upd` DATETIME NOT NULL,
        PRIMARY KEY (`id_profile`)
    ) ENGINE=" . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    if (!Db::getInstance()->execute($query)) {
        return false;
    }

    $context = Context::getContext();
    $idLang = isset($context->language) ? (int) $context->language->id : (int) Configuration::get('PS_LANG_DEFAULT');

    return WmProfilePermission::installDefaults($idLang)
        && $module->registerHook('actionOrderGridDefinitionModifier')
        && $module->registerHook('actionOrderGridQueryBuilderModifier')
        && $module->registerHook('displayBackOfficeHeader');
}
