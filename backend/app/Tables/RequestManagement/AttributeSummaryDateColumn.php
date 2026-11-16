<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

use App\CustomFields\Table\TableFieldSchema;
use App\CustomFields\Types\Concerns\AppliesSetFilter;
use App\CustomFields\Types\Concerns\ResolvesDistinctJsonValues;
use App\CustomFields\Types\Concerns\ResolvesJsonColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A `table` attribute whose config names a `summary.grid_label` over a date
 * summary column: its grid column is that date alone, under that label
 * (e.g. "Data verifica ispettiva" = the date of the active inspection row),
 * blank when the stored `summary` is null. Values, filter and distinct list
 * read only `<code>->summary`, never the rows.
 *
 * The code and the JSON column come from the server-side attribute row and
 * grid target (allow-list), never from request input (backend.md §8).
 */
final class AttributeSummaryDateColumn
{
    use AppliesSetFilter {
        applyFilter as private applySetFilter;
    }
    use ResolvesDistinctJsonValues;
    use ResolvesJsonColumn;

    private const string TABLE_TYPE = 'table';

    private const string SUMMARY_PATH = 'summary';

    public function __construct(private readonly AttributeDateFilterApplier $dateFilterApplier) {}

    /**
     * The summary column type (`date`|`datetime`) when $attributeRow is shown
     * as its summary date, else null.
     *
     * @param  array<string, mixed>  $attributeRow
     */
    public function dateType(array $attributeRow): ?string
    {
        $summary = $attributeRow['config']['summary'] ?? null;

        if (($attributeRow['type'] ?? null) !== self::TABLE_TYPE || ! is_array($summary) || $this->gridLabelOf($summary) === null) {
            return null;
        }

        foreach ($attributeRow['config']['columns'] ?? [] as $column) {
            if (is_array($column) && ($column['key'] ?? null) === ($summary['column'] ?? null)) {
                $type = $column['type'] ?? null;

                return in_array($type, TableFieldSchema::GRID_LABEL_COLUMN_TYPES, true) ? $type : null;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $attributeRow
     */
    public function applies(array $attributeRow): bool
    {
        return $this->dateType($attributeRow) !== null;
    }

    /**
     * @param  array<string, mixed>  $attributeRow
     */
    public function label(array $attributeRow): string
    {
        return (string) $this->gridLabelOf($attributeRow['config']['summary']);
    }

    /**
     * The cell value: the stored summary, or null.
     */
    public function value(mixed $stored): string|int|float|null
    {
        $summary = is_array($stored) ? ($stored[self::SUMMARY_PATH] ?? null) : null;

        return is_string($summary) || is_int($summary) || is_float($summary) ? $summary : null;
    }

    /**
     * The Set sub-filter matches exact summary values (blank included), the
     * date sub-filter a range on the summary; a multi envelope ANDs both.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $attributeRow
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, string $valuesColumn, array $attributeRow, array $filter): void
    {
        if (($filter['filterType'] ?? null) === 'multi') {
            foreach (is_array($filter['filterModels'] ?? null) ? $filter['filterModels'] : [] as $subFilter) {
                if (is_array($subFilter) && $subFilter !== []) {
                    $this->applyFilter($query, $valuesColumn, $attributeRow, $subFilter);
                }
            }

            return;
        }

        $summaryKey = $this->summaryKey($attributeRow);

        if (($filter['filterType'] ?? null) === 'set' || array_key_exists('values', $filter)) {
            $this->applySetFilter($query, $valuesColumn, $summaryKey, $filter);

            return;
        }

        $this->dateFilterApplier->apply(
            $query,
            $this->jsonColumn($valuesColumn, $summaryKey),
            $this->dateType($attributeRow) === 'datetime',
            $filter,
        );
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $attributeRow
     * @return array<int, scalar|null>
     */
    public function summaryValues(Builder $query, string $valuesColumn, array $attributeRow): array
    {
        return $this->distinctValues($query, $valuesColumn, $this->summaryKey($attributeRow));
    }

    /**
     * @param  array<string, mixed>  $attributeRow
     */
    private function summaryKey(array $attributeRow): string
    {
        return $attributeRow['code'].'->'.self::SUMMARY_PATH;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function gridLabelOf(array $summary): ?string
    {
        $label = $summary['grid_label'] ?? null;

        return is_string($label) && trim($label) !== '' ? trim($label) : null;
    }
}
