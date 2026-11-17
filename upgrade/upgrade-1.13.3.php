<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_13_3($module)
{
    return $module instanceof Wilden_manager;
}
