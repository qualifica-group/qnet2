<?php

declare(strict_types=1);

namespace App\Migrations\Realign;

use Illuminate\Support\Facades\File;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Writes the five sheets of the report (spec 0375). The file holds personal data:
 * it stays where the operator's server puts it and is never logged.
 */
final class LegacyRealignReportWriter
{
    private const int MAX_CELL_LENGTH = 32000;

    private const string KNOWN_LIMIT = 'orderiso_details e orderiso_verificas non hanno date: una loro modifica entra solo se la commessa risulta comunque cambiata.';

    public function write(LegacyRealignReport $report, string $path, bool $applied): void
    {
        File::ensureDirectoryExists(dirname($path));

        $writer = new Writer;
        $writer->openToFile($path);

        $this->sheet($writer, 'Riepilogo', $this->summaryRows($report, $applied), first: true);
        $this->sheet($writer, 'Modifiche', [
            ['module', 'legacy_id', 'qnet2_id', 'code_or_title', 'field', 'current_value', 'legacy_value'],
            ...$report->changes,
        ]);
        $this->sheet($writer, 'Modificati su qnet-2', [
            ['module', 'legacy_id', 'qnet2_id', 'code_or_title', 'last_change_at', 'last_change_by', 'field', 'current_value', 'legacy_value'],
            ...$report->touched,
        ]);
        $this->sheet($writer, 'Cancellati nel legacy', [
            ['module', 'legacy_id', 'qnet2_id', 'code_or_title', 'deleted_at'],
            ...$report->deleted,
        ]);
        $this->sheet($writer, 'Avvisi ed errori', [
            ['module', 'legacy_id', 'level', 'message'],
            ...$report->messages,
        ]);

        $writer->close();
    }

    /**
     * @param  list<list<string|int|float|bool|null>>  $rows
     */
    private function sheet(Writer $writer, string $name, array $rows, bool $first = false): void
    {
        if (! $first) {
            $writer->addNewSheetAndMakeItCurrent();
        }

        $writer->getCurrentSheet()->setName($name);

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues(array_map($this->cell(...), $row)));
        }
    }

    private function cell(string|int|float|bool|null $value): string|int|float|bool|null
    {
        return is_string($value) ? mb_substr($value, 0, self::MAX_CELL_LENGTH) : $value;
    }

    /**
     * @return list<list<string|int|float|bool|null>>
     */
    private function summaryRows(LegacyRealignReport $report, bool $applied): array
    {
        $rows = [['Esecuzione', $applied ? 'Scrittura (--apply)' : 'Simulazione: nessuna scrittura'], []];
        $rows[] = ['module', ...LegacyRealignReport::COUNTERS];

        foreach ($report->counters as $module => $counters) {
            $rows[] = [$module, ...array_values($counters)];
        }

        return [
            ...$rows,
            [],
            ['Configurazioni create'],
            ...array_map(static fn (string $name): array => [$name], array_keys($report->configurations)),
            [],
            ['Offerte con importi cambiati (provvigioni non ricalcolate)'],
            ...array_map(static fn (string $code): array => [$code], array_keys($report->amountChanges)),
            [],
            ['Limite noto'],
            [self::KNOWN_LIMIT],
        ];
    }
}
