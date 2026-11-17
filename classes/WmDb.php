<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Small wrapper around PrestaShop's Db for the patterns that are easy to get wrong.
 *
 * Db::getRow() and Db::getValue() always append " LIMIT 1" to the query. Passing a
 * query that already ends with LIMIT or FOR UPDATE therefore produces invalid SQL
 * ("LIMIT 1 LIMIT 1" or "FOR UPDATE LIMIT 1"). These helpers refuse such queries
 * and provide a dedicated method for row locks.
 */
class WmDb
{
    /**
     * @param string $sql SELECT without LIMIT/FOR UPDATE
     */
    public static function exists($sql)
    {
        return (bool) Db::getInstance()->getValue(self::assertNoTail($sql), false);
    }

    /**
     * @param string $sql SELECT without LIMIT/FOR UPDATE
     *
     * @return array|false
     */
    public static function fetchRow($sql)
    {
        return Db::getInstance()->getRow(self::assertNoTail($sql), false);
    }

    /**
     * @param string $sql SELECT without LIMIT/FOR UPDATE
     *
     * @return string|false
     */
    public static function fetchValue($sql)
    {
        return Db::getInstance()->getValue(self::assertNoTail($sql), false);
    }

    /**
     * Locks and returns the first matching row inside the current transaction.
     *
     * @param string $sql SELECT without LIMIT/FOR UPDATE
     *
     * @return array|false
     */
    public static function lockRow($sql)
    {
        $rows = Db::getInstance()->executeS(self::assertNoTail($sql) . ' LIMIT 1 FOR UPDATE', true, false);

        return $rows ? $rows[0] : false;
    }

    /**
     * Exact-name check. SHOW TABLES LIKE or TABLE_NAME LIKE would treat "_" in the
     * prefix as a wildcard and could match another installation's tables.
     *
     * @param string $fullTableName prefixed table name
     */
    public static function tableExists($fullTableName)
    {
        return (bool) Db::getInstance()->getValue(
            'SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = \'' . pSQL((string) $fullTableName) . '\'',
            false
        );
    }

    /**
     * Escapes LIKE wildcards so user input is matched literally (MySQL default escape "\").
     */
    public static function escapeLike($value)
    {
        return addcslashes((string) $value, '\\%_');
    }

    /**
     * Quoted SQL literal for "column LIKE <literal>" that matches the value anywhere.
     */
    public static function likeContains($value)
    {
        return "'%" . pSQL(self::escapeLike($value)) . "%'";
    }

    private static function assertNoTail($sql)
    {
        $sql = rtrim((string) $sql, " \t\n\r\0\x0B;");
        if (preg_match('/\b(LIMIT\s+\d+(\s*,\s*\d+)?|FOR\s+UPDATE|LOCK\s+IN\s+SHARE\s+MODE)$/i', $sql)) {
            throw new InvalidArgumentException('WmDb queries must not end with LIMIT or a lock clause.');
        }

        return $sql;
    }
}
