<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_14_1($module)
{
    return $module instanceof Wilden_manager
        && $module->registerHook('actionGetAdminOrderButtons')
        && $module->registerHook('actionOrderGridDefinitionModifier')
        && $module->registerHook('actionOrderGridQueryBuilderModifier')
        && $module->registerHook('displayBackOfficeHeader');
}
