<?php

namespace App\Tables;

use App\Models\CommissionSettlement;
use App\Models\CommissionSettlementRun;
use App\Models\User;
use App\Services\Table\FilterApplier;
use App\Tables\CommissionSettlementRuns\CommissionSettlementRunColumnCatalog as Catalog;
use App\Tables\CommissionSettlementRuns\CommissionSettlementRunSql as Sql;
use App\Tables\Support\SetExpressionQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Table definition for the `commission-settlement-runs` domain (spec 0213): the
 * read-only history of the bulk launches. Runs are not a permission resource
 * of their own (D-8): they are read and exported with the
 * `commission-settlements` abilities, which is why modelClass() names the
 * settlement (it feeds the viewAny / export Gate checks) while the rows are runs.
 */
class CommissionSettlementRunsTableDefinition extends AbstractTableDefinition
{
    /** Set columns: filter / distinct values run on the joined name. */
    private const array SET_COLUMNS = ['company', 'created_by'];

    public function domain(): string
    {
        return 'commission-settlement-runs';
    }

    /**
     * @return class-string<CommissionSettlement>
     */
    public function modelClass(): string
    {
        return CommissionSettlement::class;
    }

    /**
     * @return Builder<CommissionSettlementRun>
     */
    public function baseQuery(): Builder
    {
        return Sql::withJoins(CommissionSettlementRun::query())
            ->select('commission_settlement_runs.*')
            ->selectRaw('companies.denomination as company_name, users.name as creator_name')
            ->selectRaw(Sql::skippedCount().' as skipped_total');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return Catalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return Catalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return [];
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'created_at', 'direction' => 'desc'],
            ['columnId' => 'id', 'direction' => 'desc'],
        ];
    }

    /**
     * @return array{limit: int}
     */
    public function defaultPagination(): array
    {
        return ['limit' => 25];
    }

    /**
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var CommissionSettlementRun $row */
        return [
            'id' => $row->id,
            'created_at' => $row->created_at,
            'period_from' => $row->period_from->toDateString(),
            'period_to' => $row->period_to->toDateString(),
            'company' => $row->getAttribute('company_name'),
            'settlements_count' => $row->settlements_count,
            'skipped_count' => (int) $row->getAttribute('skipped_total'),
            'created_by' => $row->getAttribute('creator_name'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        return [];
    }

    public function authorizeUpdate(User $actor, Model $row): bool
    {
        return false;
    }

    public function authorizeDelete(User $actor, Model $row): bool
    {
        return false;
    }

    /**
     * @param  Builder<CommissionSettlementRun>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if (in_array($columnId, self::SET_COLUMNS, true)) {
            SetExpressionQuery::apply($query, Sql::PLAIN_COLUMNS[$columnId], $filter);

            return true;
        }

        if (! isset(Sql::PLAIN_COLUMNS[$columnId])) {
            return false;
        }

        app(FilterApplier::class)->apply($query, Sql::PLAIN_COLUMNS[$columnId], $columnConfig, $filter);

        return true;
    }

    /**
     * @param  Builder<CommissionSettlementRun>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        if ($columnId === 'skipped_count') {
            $query->orderByRaw(Sql::skippedCount()." {$direction}");

            return true;
        }

        if (! isset(Sql::PLAIN_COLUMNS[$columnId])) {
            return false;
        }

        $query->orderBy(Sql::PLAIN_COLUMNS[$columnId], $direction);

        return true;
    }

    /**
     * @param  Builder<CommissionSettlementRun>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string|null>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        if (! in_array($columnId, self::SET_COLUMNS, true)) {
            return null;
        }

        return SetExpressionQuery::distinct($query, Sql::PLAIN_COLUMNS[$columnId], $search, $limit);
    }
}
