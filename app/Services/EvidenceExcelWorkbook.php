<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

class EvidenceExcelWorkbook
{
    /**
     * @param  array<int, array{name: string, rows: array<int, array<int, scalar|null>>}>  $sheets
     */
    public function create(array $sheets, string $filePath): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('La extensión ZIP de PHP es necesaria para generar archivos Excel.');
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo Excel.');
        }

        $sheetNames = $this->uniqueSheetNames($sheets);
        $zip->addFromString('[Content_Types].xml', $this->contentTypes(count($sheets)));
        $zip->addFromString('_rels/.rels', $this->rootRelationships());
        $zip->addFromString('xl/workbook.xml', $this->workbook($sheetNames));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships(count($sheets)));
        $zip->addFromString('xl/styles.xml', $this->styles());

        foreach ($sheets as $index => $sheet) {
            $zip->addFromString('xl/worksheets/sheet'.($index + 1).'.xml', $this->worksheet($sheet['rows']));
        }

        $zip->close();
    }

    private function uniqueSheetNames(array $sheets): array
    {
        $names = [];
        foreach ($sheets as $sheet) {
            $base = trim(preg_replace('/[\\\\\/\?\*\[\]:]/u', ' ', $sheet['name']) ?? 'Hoja');
            $base = mb_substr($base !== '' ? $base : 'Hoja', 0, 31);
            $name = $base;
            $suffix = 2;

            while (in_array(mb_strtolower($name), array_map('mb_strtolower', $names), true)) {
                $ending = ' ('.$suffix++.')';
                $name = mb_substr($base, 0, 31 - mb_strlen($ending)).$ending;
            }
            $names[] = $name;
        }

        return $names;
    }

    private function worksheet(array $rows): string
    {
        $lastColumn = $this->columnName(max(1, count($rows[0] ?? [])));
        $lastRow = max(1, count($rows));
        $xmlRows = [];

        foreach ($rows as $rowIndex => $row) {
            $rowNumber = $rowIndex + 1;
            $cells = [];
            foreach (array_values($row) as $columnIndex => $value) {
                $reference = $this->columnName($columnIndex + 1).$rowNumber;
                $style = $rowIndex === 0 ? ' s="1"' : '';
                if (is_int($value) || is_float($value)) {
                    $cells[] = '<c r="'.$reference.'"'.$style.'><v>'.$value.'</v></c>';
                } else {
                    $text = htmlspecialchars((string) ($value ?? ''), ENT_XML1 | ENT_COMPAT, 'UTF-8');
                    $preserve = preg_match('/^\s|\s$/u', (string) ($value ?? '')) === 1 ? ' xml:space="preserve"' : '';
                    $cells[] = '<c r="'.$reference.'" t="inlineStr"'.$style.'><is><t'.$preserve.'>'.$text.'</t></is></c>';
                }
            }
            $xmlRows[] = '<row r="'.$rowNumber.'">'.implode('', $cells).'</row>';
        }

        $filter = $lastRow > 1 ? '<autoFilter ref="A1:'.$lastColumn.$lastRow.'"/>' : '';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="18"/><cols><col min="1" max="'.$this->columnNumber($lastColumn).'" width="22" customWidth="1"/></cols>'
            .'<sheetData>'.implode('', $xmlRows).'</sheetData>'.$filter.'</worksheet>';
    }

    private function workbook(array $sheetNames): string
    {
        $sheets = [];
        foreach ($sheetNames as $index => $name) {
            $sheets[] = '<sheet name="'.htmlspecialchars($name, ENT_XML1 | ENT_COMPAT, 'UTF-8').'" sheetId="'.($index + 1).'" r:id="rId'.($index + 1).'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.implode('', $sheets).'</sheets></workbook>';
    }

    private function workbookRelationships(int $sheetCount): string
    {
        $relationships = [];
        for ($index = 1; $index <= $sheetCount; $index++) {
            $relationships[] = '<Relationship Id="rId'.$index.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$index.'.xml"/>';
        }
        $relationships[] = '<Relationship Id="rId'.($sheetCount + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.implode('', $relationships).'</Relationships>';
    }

    private function contentTypes(int $sheetCount): string
    {
        $overrides = [
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>',
            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>',
        ];
        for ($index = 1; $index <= $sheetCount; $index++) {
            $overrides[] = '<Override PartName="/xl/worksheets/sheet'.$index.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'.implode('', $overrides).'</Types>';
    }

    private function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF166534"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)).$name;
            $number = intdiv($number, 26);
        }

        return $name;
    }

    private function columnNumber(string $name): int
    {
        $number = 0;
        foreach (str_split($name) as $character) {
            $number = ($number * 26) + ord($character) - 64;
        }

        return $number;
    }
}
