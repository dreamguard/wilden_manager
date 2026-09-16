<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_11_0($module)
{
    return $module instanceof Wilden_manager
        && WmProfilePermission::ensureSuperAdmin()
        && $module->registerHook('actionOrderGridDefinitionModifier')
        && $module->registerHook('actionOrderGridQueryBuilderModifier')
        && $module->registerHook('displayBackOfficeHeader');
}
