<?php

declare(strict_types=1);

namespace App\Services\ManagementControl\Exposure;

use App\DataObjects\ManagementControl\ExposureFilters;
use App\Models\CostType;
use Illuminate\Support\Facades\DB;

/**
 * Money conversion and the label lookups shared by the sections of the report
 * (spec 0269). Amounts travel as integer cents and leave as numbers with two
 * decimals; every label type is loaded with one `whereIn` query.
 */
final class ExposureSupport
{
    public const string UNATTRIBUTED = 'unattributed';

    /** Dimension => [table, label column] of the exposure grouping. */
    private const array DIMENSIONS = [
        ExposureFilters::GROUP_COMPANY => ['companies', 'denomination'],
        ExposureFilters::GROUP_COMPANY_SITE => ['company_sites', 'name'],
        ExposureFilters::GROUP_OPERATIONAL_SITE => ['operational_sites', 'alias'],
    ];

    public static function cents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    public static function euros(int $cents): float
    {
        return round($cents / 100, 2);
    }

    /** Percentage with one decimal; 0 when there is nothing to share. */
    public static function share(int $part, int $total): float
    {
        return $total > 0 ? round($part / $total * 100, 1) : 0.0;
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, array{id: int, name: string}> keyed by registry id
     */
    public static function registries(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('registries')->whereIn('id', $ids)->get(['id', 'name'])
            ->mapWithKeys(fn (object $row): array => [(int) $row->id => ['id' => (int) $row->id, 'name' => (string) $row->name]])
            ->all();
    }

    /**
     * Rows by cost type in catalogue order, the "no type" row (id 0) last.
     *
     * @param  array<int, int>  $open  cents by cost type id (0 = no type)
     * @param  array<int, int>  $overdue  cents by cost type id
     * @return array<int, array{cost_type: array<string, mixed>|null, open: float, overdue: float}>
     */
    public static function costTypeRows(array $open, array $overdue): array
    {
        $ids = array_values(array_unique([...array_keys(array_filter($open)), ...array_keys(array_filter($overdue))]));
        $row = fn (?CostType $type, int $id): array => [
            'cost_type' => $type === null ? null : ['id' => $type->id, 'name' => $type->name, 'color' => $type->color, 'nature' => $type->nature?->value],
            'open' => self::euros($open[$id] ?? 0),
            'overdue' => self::euros($overdue[$id] ?? 0),
        ];

        $rows = CostType::query()->whereIn('id', $ids)->orderBy('sort_order')->orderBy('name')->orderBy('id')->get()
            ->map(fn (CostType $type): array => $row($type, $type->id))->all();

        return in_array(0, $ids, true) ? [...$rows, $row(null, 0)] : $rows;
    }

    /**
     * Label of each id of a dimension (operational sites fall back to their id).
     *
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    public static function dimensionLabels(string $groupBy, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        [$table, $column] = self::DIMENSIONS[$groupBy];

        return DB::table($table)->whereIn('id', $ids)->pluck($column, 'id')
            ->mapWithKeys(fn (mixed $label, mixed $id): array => [(int) $id => (string) ($label ?? $id)])
            ->all();
    }
}
