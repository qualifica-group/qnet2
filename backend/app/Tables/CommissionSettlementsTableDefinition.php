<?php

namespace App\Tables;

use App\Authorization\AuthorizationRegistry;
use App\DataObjects\Enums\EnumMeta;
use App\Enums\CommissionSettlementStatus;
use App\Models\CommissionSettlement;
use App\Models\User;
use App\Services\Table\RowGroupQuery;
use App\Tables\CommissionSettlements\CommissionSettlementColumnCatalog as Catalog;
use App\Tables\CommissionSettlements\CommissionSettlementDerivedQuery;
use App\Tables\CommissionSettlements\CommissionSettlementSql;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `commission-settlements` domain (spec 0213): the
 * agent settlement statements, readable flat or grouped server-side (status,
 * referent, company, period month; sum of every amount per group).
 *
 * The amount columns follow the `commission-settlements` field permissions: a
 * column the actor cannot see is removed from columns() itself, so config,
 * sort/filter allow-lists, export and preferences drop it together; mapRow(),
 * the groupable columns and the aggregates drop it per actor. Nothing is
 * edited or deleted through the grid: the state transitions are the dedicated
 * endpoints', so a bulk delete is refused for every row.
 */
class CommissionSettlementsTableDefinition extends AbstractTableDefinition
{
    private const string RESOURCE = 'commission-settlements';

    /** @var array<int, array<string, bool>> field => visible, memoized per actor (mapRow runs per row) */
    private array $visibleFields = [];

    public function __construct(
        private readonly AuthorizationRegistry $authorization,
        private readonly RowGroupQuery $rowGroups,
    ) {}

    public function domain(): string
    {
        return self::RESOURCE;
    }

    /**
     * @return class-string<CommissionSettlement>
     */
    public function modelClass(): string
    {
        return CommissionSettlement::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default derives CommissionSettlementPolicy::viewAny from modelClass().

    /**
     * @return Builder<CommissionSettlement>
     */
    public function baseQuery(): Builder
    {
        return CommissionSettlementSql::withJoins(CommissionSettlement::query())
            ->select('commission_settlements.*')
            ->selectRaw('referents.name as referent_name, companies.denomination as company_name');
    }

    /**
     * The catalogue minus the columns whose field the current user cannot see.
     * The user comes from the auth guard (set by the request, and by the
     * export job): this method feeds the allow-lists, which take no actor.
     *
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        $actor = Auth::user();

        return array_values(array_filter(
            Catalog::columns(),
            fn (array $column): bool => ! $actor instanceof User || $this->columnVisible($actor, $column['id']),
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return Catalog::filters($this->columns());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return Catalog::actions();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'period_to', 'direction' => 'desc'],
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
     * Frontend `enums.*` keys: cells and Set Filter checklists read the same labels.
     */
    protected function enumKeyFor(string $columnId, User $actor): ?string
    {
        return $columnId === 'status' ? 'commission_settlement_status' : null;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    protected function badgesFor(string $columnId, User $actor): ?array
    {
        return $columnId === 'status'
            ? array_map(static fn (EnumMeta $meta): array => $meta->toArray(), CommissionSettlementStatus::options())
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var CommissionSettlement $row */
        $payload = [
            'id' => $row->id,
            'referent_id' => $row->referent_id,
            'company_id' => $row->company_id,
            'code' => $row->code(),
            'status' => $row->status->value,
            'referent' => $row->getAttribute('referent_name'),
            'company' => $row->getAttribute('company_name'),
            'period_from' => $row->period_from->toDateString(),
            'period_to' => $row->period_to->toDateString(),
            'period_month' => $row->period_to->format('Y-m'),
            'taxable_amount' => $row->taxable_amount,
            'vat_amount' => $row->vat_amount,
            'withholding_amount' => $row->withholding_amount,
            'enasarco_agent_amount' => $row->enasarco_agent_amount,
            'enasarco_principal_amount' => $row->enasarco_principal_amount,
            'net_payable' => $row->net_payable,
            'issued_at' => $row->issued_at,
            'abilities' => $this->abilities($actor, $row),
        ];

        foreach (Catalog::FIELD_BY_COLUMN as $column => $field) {
            if (! $this->fieldVisible($actor, $field)) {
                unset($payload[$column]);
            }
        }

        return $payload;
    }

    /**
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        $allowed = [];

        foreach (['view' => 'view', 'viewActivity' => 'activity'] as $ability => $action) {
            if (Gate::forUser($actor)->allows($ability, $row)) {
                $allowed[] = $action;
            }
        }

        return $allowed;
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
     * Footer totals over the whole filtered set, for the amounts the actor can see.
     *
     * @param  Builder<CommissionSettlement>  $query
     * @return array<string, string>
     */
    public function aggregates(Builder $query): array
    {
        $actor = Auth::user();

        return $actor instanceof User ? $this->rowGroups->totals($this, $actor, $query) : [];
    }

    /**
     * @param  Builder<CommissionSettlement>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        return CommissionSettlementDerivedQuery::applyFilter($query, $columnId, $columnConfig, $filter);
    }

    /**
     * @param  Builder<CommissionSettlement>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        return CommissionSettlementDerivedQuery::applySort($query, $columnId, $direction);
    }

    /**
     * @param  Builder<CommissionSettlement>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string|null>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        return CommissionSettlementDerivedQuery::distinctValues($columnId, $search, $query, $limit);
    }

    public function supportsRowGroups(): bool
    {
        return true;
    }

    /**
     * @return array<string, array{key: string, labels: array<int, string>}>
     */
    public function groupableColumns(User $actor): array
    {
        $status = CommissionSettlementSql::PLAIN_COLUMNS['status'];
        $month = CommissionSettlementSql::periodMonth();

        return [
            'status' => ['key' => $status, 'labels' => [$status]],
            'referent' => ['key' => 'commission_settlements.referent_id', 'labels' => [CommissionSettlementSql::PLAIN_COLUMNS['referent']]],
            'company' => ['key' => 'commission_settlements.company_id', 'labels' => [CommissionSettlementSql::PLAIN_COLUMNS['company']]],
            'period_month' => ['key' => $month, 'labels' => [$month]],
        ];
    }

    /**
     * @return array<string, array{func: string, expression: string}>
     */
    public function groupAggregates(User $actor): array
    {
        $aggregates = [];

        foreach (array_keys(Catalog::FIELD_BY_COLUMN) as $columnId) {
            if ($this->columnVisible($actor, $columnId)) {
                $aggregates[$columnId] = ['func' => 'sum', 'expression' => CommissionSettlementSql::PLAIN_COLUMNS[$columnId]];
            }
        }

        return $aggregates;
    }

    /**
     * Policy ability AND state: the grid only offers what the endpoint would accept.
     *
     * @return array{recalculate: bool, issue: bool, cancel: bool, delete: bool}
     */
    private function abilities(User $actor, CommissionSettlement $row): array
    {
        $gate = Gate::forUser($actor);
        $draft = $row->status === CommissionSettlementStatus::Draft;

        return [
            'recalculate' => $draft && $gate->allows('update', $row),
            'issue' => $draft && $gate->allows('issue', $row),
            'cancel' => $row->status === CommissionSettlementStatus::Issued && $gate->allows('cancel', $row),
            'delete' => $draft && $gate->allows('delete', $row),
        ];
    }

    private function columnVisible(User $actor, string $columnId): bool
    {
        $field = Catalog::FIELD_BY_COLUMN[$columnId] ?? null;

        return $field === null || $this->fieldVisible($actor, $field);
    }

    private function fieldVisible(User $actor, string $field): bool
    {
        if (! isset($this->visibleFields[$actor->id])) {
            $permissions = $this->authorization->resolve(self::RESOURCE)->fieldPermissions($actor, new CommissionSettlement);

            $this->visibleFields[$actor->id] = array_map(static fn ($permission): bool => $permission->visible, $permissions);
        }

        return $this->visibleFields[$actor->id][$field];
    }
}
