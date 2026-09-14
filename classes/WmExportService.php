<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class WmExportService
{
    public function download(array $orders, array $columns, array $labels, $format)
    {
        $filename = 'wilden-orders-' . date('Y-m-d-His');
        if ($format === 'xlsx') {
            $this->downloadXlsx($orders, $columns, $labels, $filename . '.xlsx');
        }

        $this->downloadCsv($orders, $columns, $labels, $filename . '.csv');
    }

    private function downloadCsv(array $orders, array $columns, array $labels, $filename)
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo "\xEF\xBB\xBF";

        $stream = fopen('php://output', 'w');
        fputcsv($stream, $this->getHeaders($columns, $labels), ';');
        foreach ($orders as $order) {
            $row = array();
            foreach ($columns as $column) {
                $row[] = $this->safeSpreadsheetText(isset($order[$column]) ? $order[$column] : '');
            }
            fputcsv($stream, $row, ';');
        }
        fclose($stream);
        exit;
    }

    private function downloadXlsx(array $orders, array $columns, array $labels, $filename)
    {
        if (!class_exists('ZipArchive')) {
            throw new PrestaShopException('XLSX export requires the PHP Zip extension.');
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'wm_export_');
        if (!$temporaryPath) {
            throw new PrestaShopException('The temporary XLSX file could not be created.');
        }

        $zip = new ZipArchive();
        if ($zip->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($temporaryPath);
            throw new PrestaShopException('The XLSX archive could not be created.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelationshipsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationshipsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString(
            'xl/worksheets/sheet1.xml',
            $this->worksheetXml($orders, $columns, $labels)
        );
        $zip->close();

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($temporaryPath));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        readfile($temporaryPath);
        @unlink($temporaryPath);
        exit;
    }

    private function worksheetXml(array $orders, array $columns, array $labels)
    {
        $rows = array($this->getHeaders($columns, $labels));
        foreach ($orders as $order) {
            $row = array();
            foreach ($columns as $column) {
                $row[] = isset($order[$column]) ? $order[$column] : '';
            }
            $rows[] = $row;
        }

        $xmlRows = array();
        foreach ($rows as $rowIndex => $row) {
            $cells = array();
            foreach ($row as $columnIndex => $value) {
                $reference = $this->columnName($columnIndex + 1) . ($rowIndex + 1);
                $style = $rowIndex === 0 ? ' s="1"' : '';
                $column = isset($columns[$columnIndex]) ? $columns[$columnIndex] : '';
                if ($rowIndex > 0 && in_array($column, array('id_order', 'total_paid_tax_incl'), true) && is_numeric($value)) {
                    $cells[] = '<c r="' . $reference . '"><v>' . (float) $value . '</v></c>';
                } else {
                    $cells[] = '<c r="' . $reference . '" t="inlineStr"' . $style . '><is><t xml:space="preserve">' .
                        $this->xml($this->safeSpreadsheetText($value)) . '</t></is></c>';
                }
            }
            $xmlRows[] = '<row r="' . ($rowIndex + 1) . '">' . implode('', $cells) . '</row>';
        }

        $lastColumn = $this->columnName(count($columns));
        $lastRow = count($rows);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
            '<dimension ref="A1:' . $lastColumn . $lastRow . '"/>' .
            '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>' .
            '<cols><col min="1" max="' . count($columns) . '" width="20" customWidth="1"/></cols>' .
            '<sheetData>' . implode('', $xmlRows) . '</sheetData>' .
            '<autoFilter ref="A1:' . $lastColumn . $lastRow . '"/>' .
            '</worksheet>';
    }

    private function getHeaders(array $columns, array $labels)
    {
        $headers = array();
        foreach ($columns as $column) {
            $headers[] = isset($labels[$column]) ? $labels[$column] : $column;
        }

        return $headers;
    }

    private function safeSpreadsheetText($value)
    {
        $value = (string) $value;
        if ($value !== '' && preg_match('/^[\x00-\x20]*[=+\-@]/', $value)) {
            return "'" . $value;
        }

        return $value;
    }

    private function xml($value)
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string) $value);

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function columnName($number)
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)) . $name;
            $number = (int) floor($number / 26);
        }

        return $name;
    }

    private function contentTypesXml()
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' .
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
            '<Default Extension="xml" ContentType="application/xml"/>' .
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
            '</Types>';
    }

    private function rootRelationshipsXml()
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
            '</Relationships>';
    }

    private function workbookXml()
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' .
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
            '<sheets><sheet name="Orders" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function workbookRelationshipsXml()
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
            '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' .
            '</Relationships>';
    }

    private function stylesXml()
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' .
            '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
            '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>' .
            '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>' .
            '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>' .
            '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' .
            '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>' .
            '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>' .
            '</styleSheet>';
    }
}
