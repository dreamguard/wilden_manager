<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_13_0($module)
{
    if (!$module instanceof Wilden_manager) {
        return false;
    }

    $engine = _MYSQL_ENGINE_;
    $query = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . "wilden_manager_integrity_review` (
        `id_wilden_manager_integrity_review` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `id_shop` INT UNSIGNED NOT NULL,
        `scope` VARCHAR(32) NOT NULL,
        `issue_key` VARCHAR(191) NOT NULL,
        `issue_type` VARCHAR(64) NOT NULL,
        `status` VARCHAR(16) NOT NULL,
        `note` TEXT NOT NULL,
        `snapshot_hash` CHAR(64) NOT NULL,
        `id_employee` INT UNSIGNED NOT NULL,
        `date_add` DATETIME NOT NULL,
        `date_upd` DATETIME NOT NULL,
        PRIMARY KEY (`id_wilden_manager_integrity_review`),
        UNIQUE KEY `uniq_wm_integrity_review` (`id_shop`, `scope`, `issue_key`),
        KEY `idx_wm_integrity_status` (`id_shop`, `scope`, `status`, `date_upd`)
    ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    return Db::getInstance()->execute($query)
        && WmProfilePermission::ensureSuperAdmin();
}
