<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

use App\Enums\FieldChangeRequestStatus;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Sort and `number` filter for the "Richieste di modifica in attesa" column
 * of the `request-management` grid (user directive 2026-11-09: every column
 * of the worklist sorts and filters). The displayed value is the OFFER's own
 * still-open requests (spec 0086 D-10, `withCount('pendingFieldChangeRequests')`
 * in RequestManagementTableDefinition::baseQuery()); both hooks here rebuild
 * that same count as a correlated subquery, because a `withCount` alias is
 * usable neither in WHERE nor safely in the pagination COUNT query.
 *
 * The filter mirrors `App\Services\Table\FilterApplier`'s `number` semantics
 * (equals/notEqual/greaterThan(OrEqual)/lessThan(OrEqual)/inRange, plus the
 * two-condition AND/OR combination) on the subquery instead of a real column:
 * the generic applier only addresses real columns of the row's table.
 * Table and column names below are constants, the compared values are bound.
 */
final class RequestPendingChangeRequestsColumn
{
    public const string COLUMN_ID = 'pending_change_requests';

    private const string TABLE = 'field_change_requests';

    /** AG Grid `number` filter type -> SQL comparison operator. */
    private const array OPERATORS = [
        'equals' => '=',
        'notEqual' => '!=',
        'lessThan' => '<',
        'lessThanOrEqual' => '<=',
        'greaterThan' => '>',
        'greaterThanOrEqual' => '>=',
    ];

    /**
     * @param  Builder<Model>  $query
     */
    public function applySort(Builder $query, string $direction): void
    {
        $query->orderBy($this->countSubquery(), $direction);
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, array $filter): void
    {
        $conditions = $filter['conditions'] ?? null;

        if (! is_array($conditions) || $conditions === []) {
            $this->applyCondition($query, $filter, 'and');

            return;
        }

        $boolean = ($filter['operator'] ?? null) === 'OR' ? 'or' : 'and';

        $query->where(function (Builder $nested) use ($conditions, $boolean): void {
            foreach ($conditions as $condition) {
                if (is_array($condition)) {
                    $this->applyCondition($nested, $condition, $boolean);
                }
            }
        });
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $condition
     */
    private function applyCondition(Builder $query, array $condition, string $boolean): void
    {
        $type = is_string($condition['type'] ?? null) ? $condition['type'] : 'equals';
        $value = $this->integerOrNull($condition['filter'] ?? null);

        if ($type === 'inRange') {
            $to = $this->integerOrNull($condition['filterTo'] ?? null);

            if ($value !== null && $to !== null) {
                $query->where(function (Builder $range) use ($value, $to): void {
                    $range->where($this->countSubquery(), '>=', $value)
                        ->where($this->countSubquery(), '<=', $to);
                }, boolean: $boolean);
            }

            return;
        }

        if ($value === null) {
            return;
        }

        $query->where($this->countSubquery(), self::OPERATORS[$type] ?? '=', $value, $boolean);
    }

    private function integerOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * The offer's still-pending requests, correlated against `quotes.id` —
     * the same rows `Quote::pendingFieldChangeRequests()` resolves.
     */
    private function countSubquery(): QueryBuilder
    {
        return DB::table(self::TABLE)
            ->selectRaw('count(*)')
            ->whereColumn(self::TABLE.'.subject_id', 'quotes.id')
            ->where(self::TABLE.'.subject_type', Relation::getMorphAlias(Quote::class))
            ->where(self::TABLE.'.status', FieldChangeRequestStatus::Pending->value);
    }
}
