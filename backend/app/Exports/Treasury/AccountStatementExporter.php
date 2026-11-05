<?php

declare(strict_types=1);

namespace App\Exports\Treasury;

use App\Exports\XlsxExportWriter;

/**
 * Writes an account statement payload (AccountStatementService::statement) to
 * an xlsx file: one row per statement row with the running balance, preceded
 * by the period opening balance row (spec 0360).
 */
final class AccountStatementExporter
{
    /**
     * @param  array<string, mixed>  $statement
     */
    public function write(array $statement, string $path): void
    {
        $writer = new XlsxExportWriter;
        $writer->open($path);
        $writer->writeHeaders(array_map(fn (string $key): string => __("treasury_statements.columns.{$key}"),
            ['date', 'type', 'description', 'registry', 'document', 'work_order', 'inflow', 'outflow', 'balance']));
        $writer->writeRow(['', '', '', '', '', '', '', '', $statement['period_opening_balance']]);

        foreach ($statement['rows'] as $row) {
            $writer->writeRow([
                $row['date'],
                __("treasury_statements.types.{$row['type']}"),
                $row['description'],
                $row['registry']['name'] ?? '',
                $row['document']['number'] ?? '',
                $row['work_order']['code'] ?? '',
                $row['inflow'] ?? '',
                $row['outflow'] ?? '',
                $row['balance'],
            ]);
        }

        $writer->close();
    }
}
