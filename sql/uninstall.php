<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

$prefix = _DB_PREFIX_;

return array(
    "DROP TABLE IF EXISTS `{$prefix}wilden_manager_audit`",
    "DROP TABLE IF EXISTS `{$prefix}wilden_manager_saved_view`",
    "DROP TABLE IF EXISTS `{$prefix}wilden_manager_order_note`"
);
