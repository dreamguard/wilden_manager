<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

$prefix = _DB_PREFIX_;

return array(
    "DROP TABLE IF EXISTS `{$prefix}wildenmanager_integrity_review`",
    "DROP TABLE IF EXISTS `{$prefix}wildenmanager_profile_permission`",
    "DROP TABLE IF EXISTS `{$prefix}wildenmanager_grid_preference`",
    "DROP TABLE IF EXISTS `{$prefix}wildenmanager_audit`",
    "DROP TABLE IF EXISTS `{$prefix}wildenmanager_order_note`"
);
