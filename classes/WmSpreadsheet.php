<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Spreadsheet-formula injection protection shared by every CSV/XLSX export.
 */
class WmSpreadsheet
{
    /**
     * Prefixes a quote when the text could be interpreted as a formula: it starts,
     * possibly after spaces or control characters (tab, CR, LF...), with = + - @.
     * Plain numbers such as "-2" or "-12.50" are kept so they stay numeric.
     */
    public static function neutralize($value)
    {
        $value = (string) $value;
        if ($value === '' || preg_match('/^-?\d+(?:[.,]\d+)?$/', $value)) {
            return $value;
        }
        if (preg_match('/^[\x00-\x20]*[=+\-@]/', $value)) {
            return "'" . $value;
        }

        return $value;
    }

    public static function neutralizeRow(array $row)
    {
        return array_map(array('WmSpreadsheet', 'neutralize'), $row);
    }
}
