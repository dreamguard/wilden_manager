<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

$engine = _MYSQL_ENGINE_;
$prefix = _DB_PREFIX_;

return array(
    "CREATE TABLE IF NOT EXISTS `{$prefix}wilden_manager_order_note` (
        `id_wilden_manager_order_note` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `id_order` INT UNSIGNED NOT NULL,
        `note` TEXT NOT NULL,
        `id_employee` INT UNSIGNED NOT NULL,
        `date_add` DATETIME NOT NULL,
        `date_upd` DATETIME NOT NULL,
        PRIMARY KEY (`id_wilden_manager_order_note`),
        UNIQUE KEY `uniq_wm_order_note` (`id_order`),
        KEY `idx_wm_note_employee` (`id_employee`)
    ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS `{$prefix}wilden_manager_saved_view` (
        `id_wilden_manager_saved_view` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `id_employee` INT UNSIGNED NOT NULL,
        `id_shop` INT UNSIGNED NOT NULL,
        `name` VARCHAR(128) NOT NULL,
        `filters_json` TEXT NOT NULL,
        `columns_json` TEXT NOT NULL,
        `is_default` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
        `date_add` DATETIME NOT NULL,
        `date_upd` DATETIME NOT NULL,
        PRIMARY KEY (`id_wilden_manager_saved_view`),
        KEY `idx_wm_view_owner` (`id_employee`, `id_shop`)
    ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS `{$prefix}wilden_manager_audit` (
        `id_wilden_manager_audit` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `id_employee` INT UNSIGNED NOT NULL,
        `id_shop` INT UNSIGNED NOT NULL,
        `id_order` INT UNSIGNED DEFAULT NULL,
        `action` VARCHAR(64) NOT NULL,
        `details_json` TEXT NOT NULL,
        `date_add` DATETIME NOT NULL,
        PRIMARY KEY (`id_wilden_manager_audit`),
        KEY `idx_wm_audit_order` (`id_order`, `date_add`),
        KEY `idx_wm_audit_employee` (`id_employee`, `date_add`),
        KEY `idx_wm_audit_action` (`action`, `date_add`)
    ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);
