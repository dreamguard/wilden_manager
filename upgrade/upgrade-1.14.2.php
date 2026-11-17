<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_14_2($module)
{
    return $module->registerHook('actionAdminControllerSetMedia')
        && $module->registerHook('actionGetAdminOrderButtons');
}
