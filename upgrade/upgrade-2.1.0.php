<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * 2.1.0: per-profile permission to edit or delete order status history entries.
 * The new permission is granted only to SuperAdmin.
 */
function upgrade_module_2_1_0($module)
{
    if (!$module instanceof Wildenmanager) {
        return false;
    }

    $table = _DB_PREFIX_ . 'wildenmanager_profile_permission';
    $hasColumn = (bool) Db::getInstance()->getValue(
        'SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = \'' . pSQL($table) . '\'
           AND COLUMN_NAME = \'can_edit_history\'',
        false
    );
    if (!$hasColumn && !Db::getInstance()->execute(
        'ALTER TABLE `' . bqSQL($table) . '`
         ADD `can_edit_history` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `can_view_diagnostics`'
    )) {
        return false;
    }

    return WmProfilePermission::ensureSuperAdmin();
}
