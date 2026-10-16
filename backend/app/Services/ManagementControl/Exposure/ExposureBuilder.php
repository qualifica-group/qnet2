<?php

declare(strict_types=1);

namespace App\Services\ManagementControl\Exposure;

use App\DataObjects\ManagementControl\ExposureFilters;

/**
 * The schedule and exposure report (spec 0269, D-12): receivable and payable
 * schedules with aging, open commitments, net exposure by dimension and
 * customer concentration. Read-only, computed with a constant number of
 * aggregate queries; amounts are summed as integer cents and leave as numbers
 * with two decimals. Visibility is not scoped (D-7 of spec 0268).
 */
final class ExposureBuilder
{
    public function __construct(
        private readonly ReceivableScheduleAggregator $receivable,
        private readonly PayableScheduleAggregator $payable,
        private readonly CommittedExposureAggregator $committed,
        private readonly CustomerConcentrationAggregator $concentration,
    ) {}

    /**
     * @param  string  $today  "Y-m-d", the day the snapshot sections refer to
     * @return array<string, mixed>
     */
    public function handle(ExposureFilters $filters, string $today): array
    {
        // Step 1: the two schedules and the commitments
        $receivable = $this->receivable->side($filters, $today);
        $payable = $this->payable->side($filters, $today);
        $committed = $this->committed->section($filters);

        // Step 2: the net exposure, in total and by dimension
        $totals = [
            'receivable_open' => ExposureSupport::cents($receivable['open_total']),
            'payable_open' => ExposureSupport::cents($payable['open_total']),
            'committed' => ExposureSupport::cents($committed['total']),
        ];

        // Step 3: the concentration of the customers
        return [
            'today' => $today,
            'receivable' => $receivable,
            'payable' => $payable,
            'committed' => $committed,
            'exposure' => [
                'receivable_open' => ExposureSupport::euros($totals['receivable_open']),
                'payable_open' => ExposureSupport::euros($totals['payable_open']),
                'committed_total' => ExposureSupport::euros($totals['committed']),
                'net' => ExposureSupport::euros($totals['receivable_open'] - $totals['payable_open'] - $totals['committed']),
                'by_dimension' => $this->byDimension($filters),
            ],
            'concentration' => $this->concentration->section($filters, $today, ExposureSupport::cents($receivable['overdue_total'])),
        ];
    }

    /**
     * One row per dimension value, ordered by label, the rows with no value last (D-9).
     *
     * @return array<int, array<string, mixed>>
     */
    private function byDimension(ExposureFilters $filters): array
    {
        $sources = [
            'receivable_open' => $this->receivable->openCentsBy($filters),
            'payable_open' => $this->payable->openCentsBy($filters),
            'committed' => $this->committed->centsBy($filters),
        ];
        $ids = array_values(array_filter(
            array_unique(array_merge(...array_map(array_keys(...), array_values($sources)))),
            fn (int $id): bool => array_sum(array_map(fn (array $source): int => abs($source[$id] ?? 0), $sources)) > 0,
        ));
        $labels = ExposureSupport::dimensionLabels($filters->groupBy, array_filter($ids));

        $row = function (int $id, string $key, ?string $label) use ($sources): array {
            $cents = array_map(fn (array $source): int => $source[$id] ?? 0, $sources);

            return [
                'key' => $key,
                'label' => $label,
                ...array_map(ExposureSupport::euros(...), $cents),
                'net' => ExposureSupport::euros($cents['receivable_open'] - $cents['payable_open'] - $cents['committed']),
            ];
        };

        $rows = array_map(fn (int $id): array => $row($id, (string) $id, $labels[$id] ?? (string) $id), array_values(array_filter($ids)));
        usort($rows, fn (array $a, array $b): int => strcasecmp((string) $a['label'], (string) $b['label']));

        return in_array(0, $ids, true) ? [...$rows, $row(0, ExposureSupport::UNATTRIBUTED, null)] : $rows;
    }
}
