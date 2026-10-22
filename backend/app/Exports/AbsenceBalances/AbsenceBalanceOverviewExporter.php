<?php

declare(strict_types=1);

namespace App\Exports\AbsenceBalances;

use App\Enums\ExportFormat;
use App\Exports\ExportWriterFactory;
use App\Services\Absences\Balances\AbsenceBalanceOverviewBuilder;

/**
 * Writes the HR balance overview as a file (spec 0304 D-3): one row per
 * person and type, amounts as decimal hours and as days of the person.
 */
final class AbsenceBalanceOverviewExporter
{
    private const int CHUNK = 100;

    private const int MINUTES_PER_HOUR = 60;

    private const int DECIMALS = 2;

    /** Amount columns, in order: the balance row key and its header label key. */
    private const array AMOUNTS = [
        'annual_minutes' => 'absence_balances.export.annual',
        'carried_over_minutes' => 'absence_balances.export.carried_over',
        'accrued_minutes' => 'absence_balances.export.accrued',
        'used_minutes' => 'absence_balances.export.used',
        'pending_minutes' => 'absence_balances.export.pending',
        'residual_minutes' => 'absence_balances.export.residual',
    ];

    public function __construct(
        private readonly AbsenceBalanceOverviewBuilder $overview,
        private readonly ExportWriterFactory $writers,
    ) {}

    /**
     * @param  array{business_function_id: int|null, operational_site_id: int|null, search: string|null}  $filters
     */
    public function write(ExportFormat $format, string $path, int $year, array $filters): void
    {
        // Step 1: header row
        $writer = $this->writers->make($format);
        $writer->open($path);
        $writer->writeHeaders($this->headers());

        // Step 2: people in chunks, one row per person and type
        $types = $this->overview->types();
        foreach ($this->overview->people($filters)->get()->chunk(self::CHUNK) as $users) {
            foreach ($this->overview->rowsFor($users, $types, $year) as $person) {
                foreach ($person['balances'] as $balance) {
                    $writer->writeRow($this->cells($person['user']['name'], $person['daily_minutes'], $balance));
                }
            }
        }

        $writer->close();
    }

    /**
     * @return list<string>
     */
    private function headers(): array
    {
        $headers = [__('absence_balances.export.person'), __('absence_balances.export.type')];

        foreach (self::AMOUNTS as $label) {
            $headers[] = __("{$label}_hours");
            $headers[] = __("{$label}_days");
        }

        return $headers;
    }

    /**
     * @param  array<string, mixed>  $balance
     * @return list<string|float>
     */
    private function cells(string $person, int $dailyMinutes, array $balance): array
    {
        $cells = [$person, $balance['absence_type']['name']];

        foreach (array_keys(self::AMOUNTS) as $key) {
            $minutes = (int) $balance[$key];
            $cells[] = round($minutes / self::MINUTES_PER_HOUR, self::DECIMALS);
            $cells[] = $dailyMinutes > 0 ? round($minutes / $dailyMinutes, self::DECIMALS) : 0.0;
        }

        return $cells;
    }
}
