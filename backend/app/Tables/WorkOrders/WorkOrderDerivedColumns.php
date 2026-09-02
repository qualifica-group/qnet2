<?php

declare(strict_types=1);

namespace App\Tables\WorkOrders;

use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Services\Table\FilterApplier;
use App\Services\WorkOrders\WorkOrderStatusResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The derived-column machinery for the `work-orders` domain (spec 0093),
 * extracted out of WorkOrdersTableDefinition (file-size split,
 * engineering.md §6): `contract_number`/`quote` (D-2, plain `quotes.code`/
 * `quotes.title` scalars reached via the `quote` relation), `status` (D-3,
 * delegated entirely to WorkOrderStatusResolver so the badge and the filter
 * can never disagree — AC-034), `is_force_closed` (a real boolean column
 * rendered as `badge`+`set`, D-4) and `type` (a real WorkOrderType-cast
 * column, D-10) and `supervisors` (spec 0096 D-7, the Responsabili reached
 * through the `work_order_supervisor` pivot — a to-many rendered as an avatar
 * stack, not sortable, `set`-filtered via whereHas, exactly like
 * QuoteRelationColumns' own `managers`).
 *
 * Every column id reaching this class comes from the definition's own static
 * catalogue (WorkOrderColumnCatalog) — never client input.
 */
final class WorkOrderDerivedColumns
{
    private const string CONTRACT_NUMBER_COLUMN = 'contract_number';

    private const string QUOTE_COLUMN = 'quote';

    private const string TYPE_COLUMN = 'type';

    private const string IS_FORCE_CLOSED_COLUMN = 'is_force_closed';

    private const string STATUS_COLUMN = 'status';

    /** The Responsabili (spec 0096, D-7): a to-many over `work_order_supervisor`. */
    private const string SUPERVISORS_COLUMN = 'supervisors';

    private const string SUPERVISORS_RELATION = 'supervisors';

    private const string SUPERVISORS_PIVOT = 'work_order_supervisor';

    private const string USERS_TABLE = 'users';

    private const string USER_LABEL_COLUMN = 'name';

    /** `work_orders.quote_id` -> `quotes` column, keyed by the public column id. */
    private const array QUOTE_SCALAR_COLUMNS = [
        self::CONTRACT_NUMBER_COLUMN => 'code',
        self::QUOTE_COLUMN => 'title',
    ];

    public function __construct(
        private readonly WorkOrderStatusResolver $statusResolver,
        private readonly FilterApplier $filterApplier,
    ) {}

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if ($columnId === self::STATUS_COLUMN) {
            $this->statusResolver->applyFilter($query, $this->setFilterValues($filter));

            return true;
        }

        if ($columnId === self::IS_FORCE_CLOSED_COLUMN) {
            $this->applyIsForceClosedFilter($query, $this->setFilterValues($filter));

            return true;
        }

        if ($columnId === self::SUPERVISORS_COLUMN) {
            $this->applySupervisorsFilter($query, $this->setFilterValues($filter));

            return true;
        }

        $quoteColumn = self::QUOTE_SCALAR_COLUMNS[$columnId] ?? null;

        if ($quoteColumn === null) {
            return false;
        }

        $query->whereHas('quote', function (Builder $quoteQuery) use ($quoteColumn, $columnConfig, $filter): void {
            $this->filterApplier->apply($quoteQuery, $quoteColumn, $columnConfig, $filter);
        });

        return true;
    }

    /**
     * `set` filter on the responsabili's own `users.name`, via `whereHas`
     * with BOUND values — never a raw fragment built from client input
     * (backend.md §8). Matches a commessa when ANY of its responsabili is
     * among the picked names, the same semantics the Offerta's `managers`
     * column already has. The column id itself comes from the static
     * catalogue, never from the request.
     *
     * @param  Builder<Model>  $query
     * @param  array<int, string>  $values
     */
    private function applySupervisorsFilter(Builder $query, array $values): void
    {
        if ($values === []) {
            return;
        }

        $query->whereHas(self::SUPERVISORS_RELATION, static function (Builder $userQuery) use ($values): void {
            $userQuery->whereIn(self::USER_LABEL_COLUMN, $values);
        });
    }

    /**
     * ORDER BY `quotes.code`/`quotes.title` via a correlated subquery — never
     * a row-multiplying JOIN on the main query. `supervisors` never reaches
     * here: a to-many has no single sort key, so the catalogue declares it
     * `sortable: false`. `status`/`is_force_closed`/
     * `type` never reach here: `status` is not sortable (excluded from the
     * whitelist upstream) and the other two are real columns, sorted by the
     * generic engine's plain ORDER BY.
     *
     * @param  Builder<Model>  $query
     */
    public function applySort(Builder $query, string $columnId, string $direction): bool
    {
        $quoteColumn = self::QUOTE_SCALAR_COLUMNS[$columnId] ?? null;

        if ($quoteColumn === null) {
            return false;
        }

        $query->orderBy($this->quoteScalarSubquery($quoteColumn), $direction);

        return true;
    }

    /**
     * Excel-like distinct values (spec 0004/0005): the FULL declared value
     * set for `type`/`status`/`is_force_closed` — every valid option the set
     * filter can ever match, not merely the ones PRESENT in the current
     * data (a work order of a type/status with zero current rows must stay
     * pickable). Each returns `array<int, string>` of RAW values (mirrors
     * `OpportunityStatusColumn`/`ContractsTableDefinition::alertDistinctValues`),
     * the same contract `DistinctValuesResult::$values` declares: the
     * frontend re-derives each checkbox's LABEL client-side via the
     * column's own `enumKey` (WorkOrdersTableDefinition::enumKeyFor()) —
     * this endpoint is not the place for the richer `EnumMeta` shape
     * (`badges`/`config.form_enums` already carry that, a different
     * contract). `contract_number`/`quote` declare `hasFilterValues: false`
     * (WorkOrderColumnCatalog), so this is never reached for them.
     *
     * @return array<int, string>|null
     */
    public function distinctValues(string $columnId, ?string $search, Builder $query, int $limit): ?array
    {
        return match ($columnId) {
            self::TYPE_COLUMN => $this->filterOptions($search, WorkOrderType::values()),
            self::IS_FORCE_CLOSED_COLUMN => $this->filterOptions($search, ['true', 'false']),
            self::STATUS_COLUMN => $this->filterOptions($search, WorkOrderStatus::values()),
            self::SUPERVISORS_COLUMN => $this->distinctSupervisorNames($search, $query, $limit),
            default => null,
        };
    }

    /**
     * Distinct responsabile names among the work orders matching $query —
     * scoped by every OTHER active filter (Excel-like distinct values, spec
     * 0004/0005), unlike the enum columns above which list their full
     * declared set. Mirrors QuoteRelationColumns::distinctManagerNames(), a
     * join through the pivot rather than a subquery on an own FK.
     *
     * @param  Builder<Model>  $query
     * @return array<int, string>
     */
    private function distinctSupervisorNames(?string $search, Builder $query, int $limit): array
    {
        $workOrderIds = (clone $query)->select('work_orders.id');

        return DB::table(self::USERS_TABLE)
            ->join(self::SUPERVISORS_PIVOT, self::SUPERVISORS_PIVOT.'.user_id', '=', self::USERS_TABLE.'.id')
            ->whereIn(self::SUPERVISORS_PIVOT.'.work_order_id', $workOrderIds)
            ->when($search !== null && $search !== '', function ($builder) use ($search): void {
                $builder->where(self::USERS_TABLE.'.'.self::USER_LABEL_COLUMN, 'like', '%'.$this->escapeLike($search).'%');
            })
            ->distinct()
            ->orderBy(self::USERS_TABLE.'.'.self::USER_LABEL_COLUMN)
            ->limit($limit)
            ->pluck(self::USERS_TABLE.'.'.self::USER_LABEL_COLUMN)
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }

    /**
     * Derived quick-search (spec 0009): `contract_number` matches the
     * related quote's own `code`. `quote` is deliberately NOT searchable
     * (data_contract).
     *
     * @param  Builder<Model>  $query
     */
    public function applySearch(Builder $query, string $columnId, string $pattern): bool
    {
        if ($columnId !== self::CONTRACT_NUMBER_COLUMN) {
            return false;
        }

        $query->orWhereHas('quote', function (Builder $quoteQuery) use ($pattern): void {
            $quoteQuery->where('code', 'like', $pattern);
        });

        return true;
    }

    /**
     * `is_force_closed`'s `set` filter carries the STRING values
     * `distinctValues()` handed out ("true"/"false", the declared
     * data_contract shape for a `badge`+`set` column) — never the raw
     * boolean the generic FilterApplier's own `set` branch would compare
     * as-is against the tinyint column. Parsed back to real booleans here.
     *
     * @param  Builder<Model>  $query
     * @param  array<int, string>  $values
     */
    private function applyIsForceClosedFilter(Builder $query, array $values): void
    {
        $wantsTrue = in_array('true', $values, true);
        $wantsFalse = in_array('false', $values, true);

        if ($wantsTrue === $wantsFalse) {
            return; // neither requested, or both — no constraint either way.
        }

        $query->where(self::IS_FORCE_CLOSED_COLUMN, $wantsTrue);
    }

    /**
     * @param  array<int, string>  $options
     * @return array<int, string>
     */
    private function filterOptions(?string $search, array $options): array
    {
        if ($search === null || $search === '') {
            return $options;
        }

        return array_values(array_filter($options, static fn (string $option): bool => stripos($option, $search) !== false));
    }

    /**
     * @param  array<string, mixed>  $filter
     * @return array<int, string>
     */
    private function setFilterValues(array $filter): array
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter($values, static fn ($value): bool => is_string($value) && $value !== ''));
    }

    private function quoteScalarSubquery(string $column): QueryBuilder
    {
        return DB::table('quotes')
            ->select($column)
            ->whereColumn('quotes.id', 'work_orders.quote_id')
            ->limit(1);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
