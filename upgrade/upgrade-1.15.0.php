<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_15_0($module)
{
    if (!$module instanceof Wilden_manager) {
        return false;
    }

    // Stock reinjection on deletion is opt-in: keep the previous behaviour by default.
    if (Configuration::getGlobalValue('WM_DELETE_REINJECT_STOCK') === false) {
        return (bool) Configuration::updateGlobalValue('WM_DELETE_REINJECT_STOCK', 0);
    }

    return true;
}
