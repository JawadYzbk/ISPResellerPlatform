<?php

namespace App\Domain\Spreadsheets;

use RuntimeException;
use ZipArchive;

final class XlsxReader
{
    /** @return list<list<string>> */
    public function rows(string $contents): array
    {
        $path = tempnam(sys_get_temp_dir(), 'isp-xlsx-');
        if ($path === false || file_put_contents($path, $contents) === false) {
            throw new RuntimeException('Unable to read the XLSX import file.');
        }

        try {
            $zip = new ZipArchive;
            if ($zip->open($path) !== true) {
                throw new RuntimeException('The XLSX import archive is invalid.');
            }
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            if (! is_string($sheet)) {
                throw new RuntimeException('The XLSX import has no first worksheet.');
            }
            $shared = $this->sharedStrings($zip->getFromName('xl/sharedStrings.xml'));
            $zip->close();

            return $this->parseSheet($sheet, $shared);
        } finally {
            @unlink($path);
        }
    }

    public function toCsv(string $contents): string
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new RuntimeException('Unable to create the XLSX conversion stream.');
        }
        foreach ($this->rows($contents) as $row) {
            fputcsv($stream, $row);
        }
        rewind($stream);
        $csv = stream_get_contents($stream) ?: '';
        fclose($stream);

        return $csv;
    }

    /** @return list<string> */
    private function sharedStrings(?string $xml): array
    {
        if (! is_string($xml) || $xml === '') {
            return [];
        }
        $document = new \DOMDocument;
        $document->loadXML($xml);
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $values = [];
        foreach ($xpath->query('//main:si') ?: [] as $item) {
            $values[] = $xpath->evaluate('string(.//main:t)', $item);
        }

        return $values;
    }

    /** @param list<string> $shared @return list<list<string>> */
    private function parseSheet(string $xml, array $shared): array
    {
        $document = new \DOMDocument;
        if (! $document->loadXML($xml)) {
            throw new RuntimeException('The XLSX worksheet XML is invalid.');
        }
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = [];
        foreach ($xpath->query('//main:sheetData/main:row') ?: [] as $row) {
            $values = [];
            foreach ($xpath->query('main:c', $row) ?: [] as $cell) {
                $attribute = $cell->attributes?->getNamedItem('r');
                $reference = $attribute instanceof \DOMAttr ? $attribute->value : '';
                preg_match('/^([A-Z]+)/', $reference, $match);
                $column = $this->columnNumber($match[1] ?? 'A');
                $type = $cell->attributes?->getNamedItem('t')?->nodeValue;
                $value = $type === 'inlineStr'
                    ? (string) $xpath->evaluate('string(main:is/main:t)', $cell)
                    : (string) $xpath->evaluate('string(main:v)', $cell);
                if ($type === 's') {
                    $value = $shared[(int) $value] ?? '';
                }
                $values[$column - 1] = $value;
            }
            ksort($values);
            $rows[] = array_values($values);
        }

        return $rows;
    }

    private function columnNumber(string $column): int
    {
        $number = 0;
        foreach (str_split($column) as $letter) {
            $number = $number * 26 + ord($letter) - 64;
        }

        return max(1, $number);
    }
}
