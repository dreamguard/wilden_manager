<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_13_1($module)
{
    return $module instanceof Wilden_manager;
}
