<?php

declare(strict_types=1);

namespace App\Tables;

use App\Enums\WorkOrderType;
use App\Enums\WorkOrderWorkPhase;
use App\Models\Registry;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrders\WorkOrderSourceQuotes;
use App\Services\WorkOrders\WorkOrderStatusResolver;
use App\Services\WorkOrders\WorkOrderVisibilityScope;
use App\Services\WorkOrderService;
use App\Tables\Concerns\SearchesByIdNotLegacyId;
use App\Tables\Shared\DocumentsCountColumn;
use App\Tables\Shared\WorkOrderWorkflowStatusOptions;
use App\Tables\WorkOrders\WorkOrderAdvancedFilterApplier;
use App\Tables\WorkOrders\WorkOrderCellWriter;
use App\Tables\WorkOrders\WorkOrderColumnCatalog;
use App\Tables\WorkOrders\WorkOrderDerivedColumns;
use App\Tables\WorkOrders\WorkOrderProformaStatusColumn;
use App\Tables\WorkOrders\WorkOrderRenewalColumn;
use App\Tables\WorkOrders\WorkOrderWorkflowStatusColumn;
use App\Tables\WorkOrders\WorkOrderWorkPhaseColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `work-orders` domain (spec 0093).
 *
 * `code`/`title`/`type`/`callback_date`/`is_force_closed`/`created_at`/
 * `updated_at` are real `work_orders` columns, handled entirely by the
 * generic engine. `contract_number`/`quote` are DERIVED through the `quote`
 * relation (D-2: `quotes.code`/`quotes.title`, never copied), `supervisors`
 * is a to-many derived through the `work_order_supervisor` pivot (spec 0096,
 * D-7), `registry` is the client reached through `quote.opportunity` and
 * `completion_percentage` is COMPUTED from the manual progress entries
 * (spec 0321) by WorkOrderStatusResolver, sorted through it too; `status` is
 * the persisted working phase (spec 0395, WorkOrderWorkPhaseColumn), filtered
 * through WorkOrderDerivedColumns (file-size split, engineering.md §6).
 *
 * baseQuery() is scoped by WorkOrderVisibilityScope (user directive
 * 2026-09-02): without `work-orders.viewAll` the actor lists only the
 * commesse where they are Responsabile or Partecipante. Rows, exports and
 * distinct filter values all derive from this one query, so they are scoped
 * by construction.
 */
class WorkOrdersTableDefinition extends AbstractTableDefinition
{
    use SearchesByIdNotLegacyId;

    /** Computed from the manual progress entries (spec 0321), sorted by WorkOrderStatusResolver. */
    private const string COMPLETION_PERCENTAGE_COLUMN = 'completion_percentage';

    /** Joins the distinct offers / clients of a multi-offer commessa in one cell (spec 0267, D-8). */
    private const string MULTI_SEPARATOR = ' · ';

    public function __construct(
        private readonly WorkOrderService $service,
        private readonly WorkOrderStatusResolver $statusResolver,
        private readonly WorkOrderDerivedColumns $derivedColumns,
        private readonly WorkOrderCellWriter $cellWriter,
        private readonly WorkOrderSourceQuotes $sourceQuotes,
        private readonly WorkOrderWorkflowStatusColumn $workflowStatusColumn,
        private readonly WorkOrderRenewalColumn $renewalColumn,
        private readonly WorkOrderAdvancedFilterApplier $advancedFilterApplier,
        private readonly WorkOrderWorkPhaseColumn $workPhaseColumn,
        private readonly WorkOrderProformaStatusColumn $proformaStatusColumn,
    ) {}

    /**
     * Spec 0206, D-2: the inline cell edit follows the form's rules —
     * UpdateWorkOrderRequest + WorkOrderService::update() through
     * WorkOrderCellWriter, never the generic `$row->update()` (which could
     * not sync `supervisor_ids` and would skip the form's own validation).
     */
    public function updateCell(Model $row, string $columnId, mixed $value): Model
    {
        /** @var WorkOrder $row */
        /** @var User $actor */
        $actor = Auth::user();

        return $this->cellWriter->write($row, $columnId, $value, $actor);
    }

    public function domain(): string
    {
        return 'work-orders';
    }

    /**
     * @return class-string<WorkOrder>
     */
    public function modelClass(): string
    {
        return WorkOrder::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives WorkOrderPolicy::viewAny
    // from modelClass() (work-orders.viewAny).

    /**
     * @return Builder<WorkOrder>
     */
    public function baseQuery(): Builder
    {
        // `supervisors.avatar`/`participants.avatar` are eager-loaded so the
        // Responsabili and Partecipanti cells can render real avatars and not
        // just initials (spec 0096, AC-052), mirroring QuotesTableDefinition's
        // own managers eager load. The loaded `participants` also let
        // WorkOrderVisibilityScope::isVisibleTo() answer actionsFor()'s
        // per-row Gate calls in memory instead of querying (user
        // directive 2026-09-02).
        // `quote.opportunity.registry` feeds the Anagrafica column,
        // `quote.contract` the contract expiry date.
        // The progress subqueries feed `status`/`completion_percentage`/
        // `completion_recorded_on` (spec 0321) without one query per row.
        $query = $this->statusResolver->withProgress(WorkOrderVisibilityScope::withCompetenceVerdict(WorkOrder::query(), Auth::user())->with([
            'quote.opportunity.registry',
            'quote.contract',
            'supervisors.avatar',
            'participants.avatar',
            // Spec 0322: the "Stato lavorazione" cell, and its per-row set
            // (WorkOrderWorkflowResolver reads `quoteLines.product`).
            'workOrderWorkflowStatus',
            'quoteLines.product',
            // Spec 0267, D-8: only multi-offer rows read their lines' offers.
            'quoteLines.quote.opportunity.registry',
        ]));

        // Spec 0363: badges of the `documents` and `notes` row actions.
        $query->withCount([...DocumentsCountColumn::own(), 'notes']);

        // Spec 0407: the "€" state, only for the actors who see that column.
        if (WorkOrderProformaStatusColumn::isVisibleTo(Auth::user())) {
            $this->proformaStatusColumn->withFlags($query);
        }

        return WorkOrderVisibilityScope::scopeToActor($query, Auth::user());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        // Spec 0407: without the "€" column the actor can neither sort nor filter by it.
        return WorkOrderProformaStatusColumn::isVisibleTo(Auth::user())
            ? [...WorkOrderColumnCatalog::columns(), WorkOrderColumnCatalog::proformaStatusColumn()]
            : WorkOrderColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return WorkOrderProformaStatusColumn::isVisibleTo(Auth::user())
            ? [...WorkOrderColumnCatalog::filters(), ['columnId' => WorkOrderProformaStatusColumn::COLUMN, 'type' => 'set']]
            : WorkOrderColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return WorkOrderColumnCatalog::actions();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function advancedFilters(): array
    {
        return $this->advancedFilterApplier->commessePageFilters();
    }

    /**
     * @param  Builder<WorkOrder>  $query
     * @param  array<string, mixed>  $descriptor
     */
    public function applyAdvancedFilter(Builder $query, string $name, array $descriptor, mixed $value): bool
    {
        /** @var User|null $actor */
        $actor = Auth::user();

        return $this->advancedFilterApplier->apply($query, $name, $value, $actor)
            || parent::applyAdvancedFilter($query, $name, $descriptor, $value);
    }

    /**
     * @param  Builder<Model>  $query
     * @return array<string, int>
     */
    public function aggregates(Builder $query): array
    {
        return $this->workPhaseColumn->counts($query);
    }

    /**
     * @return array<int, string>
     */
    public function filtersIgnoredByAggregates(): array
    {
        return [WorkOrderWorkPhaseColumn::COLUMN];
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'created_at', 'direction' => 'desc'],
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
     * Badge metadata for the `type`/`status` columns, driven by
     * WorkOrderType/WorkOrderWorkPhase (mirrors ProductsTableDefinition's own
     * `product_type`).
     *
     * @return array<int, array<string, mixed>>|null
     */
    protected function badgesFor(string $columnId, User $actor): ?array
    {
        return match ($columnId) {
            'type' => array_map(static fn ($meta): array => $meta->toArray(), WorkOrderType::options()),
            WorkOrderWorkPhaseColumn::COLUMN => array_map(static fn ($meta): array => $meta->toArray(), WorkOrderWorkPhase::options()),
            default => null,
        };
    }

    /**
     * The `select` editor catalogue of the "Stato lavorazione" column
     * (spec 0322); the per-row narrowing rides on the row itself.
     *
     * @return array<int, array<string, mixed>>|null
     */
    protected function optionsFor(string $columnId, User $actor): ?array
    {
        return $columnId === WorkOrderWorkflowStatusColumn::COLUMN ? WorkOrderWorkflowStatusOptions::catalog() : null;
    }

    /**
     * The `type`/`status` badges are driven by WorkOrderType/WorkOrderWorkPhase,
     * exposed to the frontend config under the `work_order_type`/
     * `work_order_work_phase` enum keys (config/config.php form_enums), so the
     * client localizes the badge label from its own i18n resources instead
     * of the backend-supplied one. `proforma_status` (spec 0407) has no enum
     * class: its key only names the i18n labels of the filter values.
     */
    protected function enumKeyFor(string $columnId, User $actor): ?string
    {
        return match ($columnId) {
            'type' => 'work_order_type',
            WorkOrderWorkPhaseColumn::COLUMN => 'work_order_work_phase',
            WorkOrderProformaStatusColumn::COLUMN => WorkOrderProformaStatusColumn::ENUM_KEY,
            default => null,
        };
    }

    /**
     * Map a WorkOrder to the row payload. `actions` is attached by the
     * generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var WorkOrder $row */
        $progress = $this->statusResolver->progress($row);

        $multi = $row->isMultiQuote();
        $sourceQuotes = $multi ? $this->sourceQuotes->fromLoadedLines($row) : null;
        $proforma = WorkOrderProformaStatusColumn::isVisibleTo($actor)
            ? [WorkOrderProformaStatusColumn::COLUMN => $this->proformaStatusColumn->project($row)]
            : [];

        return [
            ...$proforma,
            'id' => $row->id,
            'code' => $row->code,
            'title' => $row->title,
            'contract_number' => $sourceQuotes === null ? $row->quote?->code : $this->joinDistinct($sourceQuotes->pluck('code')),
            'quote' => $sourceQuotes === null ? $row->quote?->title : $this->joinDistinct($sourceQuotes->pluck('title')),
            'contract_expiry_date' => $row->quote?->contract?->expiry_date,
            WorkOrderRenewalColumn::COLUMN => $this->renewalColumn->project($row),
            'registry' => $sourceQuotes === null
                ? $this->registrySummary($row->quote?->opportunity?->registry)
                : $this->joinDistinct($sourceQuotes->map(static fn ($quote) => $quote->opportunity?->registry?->name)->sort()),
            'type' => $row->type?->value,
            'start_date' => $row->start_date,
            'supervisors' => $row->supervisors->map(fn (User $user): array => $this->userSummary($user))->all(),
            'participants' => $row->participants->map(fn (User $user): array => $this->userSummary($user))->all(),
            'callback_date' => $row->callback_date,
            'is_force_closed' => $row->is_force_closed,
            WorkOrderWorkPhaseColumn::COLUMN => $this->workPhaseColumn->project($row),
            ...$this->workflowStatusColumn->project($row),
            'completion_percentage' => $progress->completionPercentage,
            'completion_recorded_on' => $progress->recordedOn,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
            DocumentsCountColumn::FIELD => DocumentsCountColumn::project($row),
            'notes_count' => (int) ($row->notes_count ?? 0),
        ];
    }

    /**
     * Distinct non-empty values joined by " · " (spec 0267, D-8); null when none.
     *
     * @param  Collection<int, string|null>  $values
     */
    private function joinDistinct(Collection $values): ?string
    {
        $joined = $values->filter(static fn ($value): bool => $value !== null && $value !== '')->unique()->implode(self::MULTI_SEPARATOR);

        return $joined === '' ? null : $joined;
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function registrySummary(?Registry $registry): ?array
    {
        return $registry === null ? null : ['id' => $registry->id, 'name' => $registry->name];
    }

    /**
     * A person summary carrying the inline avatar (data URI) so the
     * Responsabili column renders real avatars, not just initials — the same
     * shape and helper QuotesTableDefinition already emits (spec 0096).
     *
     * @return array{id: int, name: string, avatar_url: string|null}
     */
    private function userSummary(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar_url' => $user->avatarDataUri(),
        ];
    }

    /**
     * Allowed action keys for a single row (spec 0405, the legacy "Azioni"):
     * everything opens the commessa, so `view` gates them all; each tab of
     * the menu keeps its own detail-tab gate. `delete` is never offered per
     * row: the catalog keeps it only for the bulk delete, which the
     * TableBulkDeleteService authorizes per row.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var WorkOrder $row */
        if (! Gate::forUser($actor)->allows('view', $row)) {
            return [];
        }

        $allowed = ['view', 'team', 'info', 'notes'];

        if ($actor->can('tasks.viewAny')) {
            $allowed[] = 'tasks';
        }

        if (Gate::forUser($actor)->allows('viewDocuments', $row)) {
            $allowed[] = 'documents';
        }

        return $allowed;
    }

    /**
     * Delegate to WorkOrderService::delete() so the generic bulk-delete
     * endpoint respects the SAME guard (409 on accounting documents, spec
     * 0243) as the single DELETE /work-orders/{workOrder} endpoint.
     */
    public function deleteModel(Model $model): void
    {
        /** @var WorkOrder $model */
        $this->service->delete($model);
    }

    /**
     * @param  Builder<WorkOrder>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        return $this->derivedColumns->applyFilter($query, $columnId, $columnConfig, $filter);
    }

    /**
     * @param  Builder<WorkOrder>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if ($columnId === self::COMPLETION_PERCENTAGE_COLUMN) {
            $this->statusResolver->applyCompletionSort($query, $direction);

            return true;
        }

        return $this->derivedColumns->applySort($query, $columnId, $direction);
    }

    /**
     * @param  Builder<WorkOrder>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        return $this->derivedColumns->distinctValues($columnId, $search, $query, $limit);
    }

    /**
     * @param  Builder<WorkOrder>  $query
     */
    public function applyDerivedSearch(Builder $query, string $columnId, string $pattern): bool
    {
        return $this->derivedColumns->applySearch($query, $columnId, $pattern);
    }
}
