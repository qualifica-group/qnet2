<?php

declare(strict_types=1);

namespace App\Imports\PayrollCosts;

use App\Imports\ChartOfAccounts\ImportRow;
use App\Imports\Support\SpreadsheetRecords;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Reads the cost file into rows keyed by column key (spec 0430, D-5, D-6).
 * csv/txt: the separator (tab, `;` or `,`) comes from the header line and a
 * non UTF-8 content is taken as Windows-1252; xlsx goes through openspout.
 * A file that cannot be read, is empty, lacks a required column or exceeds the
 * row limit is refused as a whole (422 on `file`).
 */
final class PayrollCostFileReader
{
    public const int MAX_ROWS = 2000;

    /** @var array<string, string> column key => header as written in the file */
    private array $headers = [];

    public function __construct(private readonly SpreadsheetRecords $spreadsheet) {}

    /**
     * @return list<ImportRow> numbered by file line (header = 1)
     */
    public function read(string $path): array
    {
        try {
            // Step 1: raw records of the file, numbered by line
            $records = $this->spreadsheet->read($path);
        } catch (Throwable) {
            $this->refuse('unreadable_file');
        }

        // Step 2: header and columns
        $header = array_key_first($records);
        if ($header === null || count($records) < 2) {
            $this->refuse('empty_file');
        }
        $columns = $this->mapColumns($records[$header]);

        // Step 3: data rows
        unset($records[$header]);
        if (count($records) > self::MAX_ROWS) {
            $this->refuse('too_many_rows', ['max' => self::MAX_ROWS]);
        }

        $rows = [];
        foreach ($records as $number => $cells) {
            $mapped = [];
            foreach ($columns as $index => $key) {
                $mapped[$key] = $cells[$index] ?? '';
            }
            $rows[] = new ImportRow($number, $mapped);
        }

        return $rows;
    }

    /**
     * Column key => header as written in the file, for the last read.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Cell index => column key; the first of two same-named headers wins.
     *
     * @param  array<int, string>  $header
     * @return array<int, string>
     */
    private function mapColumns(array $header): array
    {
        $lookup = PayrollCostColumns::lookup();
        $columns = [];
        $this->headers = [];

        foreach ($header as $index => $name) {
            $key = $lookup[PayrollCostColumns::normalize($name)] ?? null;
            if ($key !== null && ! isset($this->headers[$key])) {
                $columns[$index] = $key;
                $this->headers[$key] = $name;
            }
        }

        $missing = array_diff_key(PayrollCostColumns::REQUIRED, $this->headers);
        if ($missing !== []) {
            $this->refuse('missing_columns', ['columns' => implode(', ', $missing)]);
        }

        return $columns;
    }

    /**
     * @param  array<string, scalar>  $replace
     */
    private function refuse(string $messageKey, array $replace = []): never
    {
        throw ValidationException::withMessages(['file' => [__("payroll_costs.import.errors.{$messageKey}", $replace)]]);
    }
}
