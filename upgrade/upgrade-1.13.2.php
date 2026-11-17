<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_13_2($module)
{
    return $module instanceof Wilden_manager;
}
