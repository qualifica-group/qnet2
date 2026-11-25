<?php

declare(strict_types=1);

namespace App\Imports\Support;

use DateTimeInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Raw records of an import file, shared by the payroll imports (specs 0430,
 * 0431). csv/txt: the separator (tab, `;` or `,`) comes from the header line, a
 * non UTF-8 content is taken as Windows-1252 and quoted fields (with doubled
 * quotes inside) are honoured; xlsx goes through openspout. Throws when the
 * file cannot be read.
 */
final class SpreadsheetRecords
{
    private const array DELIMITERS = ["\t", ';', ','];

    /**
     * @return array<int, array<int, string>> non-blank trimmed records keyed by file line (header = 1)
     */
    public function read(string $path): array
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'xlsx'
            ? $this->xlsxRecords($path)
            : $this->textRecords($path);
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function textRecords(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $delimiter = $this->detectDelimiter($content);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $records = [];
        $line = 0;
        while (($cells = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
            $line++;
            $cells = array_map(static fn (?string $cell): string => trim((string) $cell), $cells);
            if (array_filter($cells, static fn (string $cell): bool => $cell !== '') !== []) {
                $records[$line] = $cells;
            }
        }
        fclose($stream);

        return $records;
    }

    private function detectDelimiter(string $content): string
    {
        $firstLine = strtok($content, "\r\n") ?: '';
        $best = ',';
        $bestCount = -1;
        foreach (self::DELIMITERS as $delimiter) {
            $count = substr_count($firstLine, $delimiter);
            if ($count > $bestCount) {
                [$best, $bestCount] = [$delimiter, $count];
            }
        }

        return $best;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function xlsxRecords(string $path): array
    {
        $reader = new XlsxReader;
        $reader->open($path);
        $records = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $line => $row) {
                    $cells = $this->cellsToStrings($row);
                    if (array_filter($cells, static fn (string $cell): bool => $cell !== '') !== []) {
                        $records[(int) $line] = $cells;
                    }
                }

                break;
            }
        } finally {
            $reader->close();
        }

        return $records;
    }

    /**
     * @return array<int, string>
     */
    private function cellsToStrings(Row $row): array
    {
        return array_map(static fn (mixed $value): string => trim(match (true) {
            $value === null => '',
            $value instanceof DateTimeInterface => $value->format('d/m/Y'),
            is_bool($value) => $value ? '1' : '0',
            default => (string) $value,
        }), array_values($row->toArray()));
    }
}
