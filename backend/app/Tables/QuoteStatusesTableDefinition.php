<?php

namespace App\Tables;

use App\Models\QuoteStatus;
use App\Models\User;
use App\Services\QuoteStatusService;
use App\Tables\QuoteStatuses\QuoteStatusAdvancedFilterCatalog;
use App\Tables\QuoteStatuses\QuoteStatusColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `quote-statuses` domain (spec 0065): a plain
 * clone of OpportunityStatusesTableDefinition.
 *
 * Every column (name, color, sort_order, group, created_at) is a real DB
 * column handled entirely by the generic engine.
 */
class QuoteStatusesTableDefinition extends AbstractTableDefinition
{
    public function __construct(private readonly QuoteStatusService $service) {}

    public function domain(): string
    {
        return 'quote-statuses';
    }

    /**
     * @return class-string<QuoteStatus>
     */
    public function modelClass(): string
    {
        return QuoteStatus::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives QuoteStatusPolicy::viewAny
    // from modelClass() (quote-statuses.viewAny).

    /**
     * @return Builder<QuoteStatus>
     */
    public function baseQuery(): Builder
    {
        return QuoteStatus::query();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return QuoteStatusColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return QuoteStatusColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return QuoteStatusColumnCatalog::actions();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function advancedFilters(): array
    {
        return QuoteStatusAdvancedFilterCatalog::advancedFilters();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'sort_order', 'direction' => 'asc'],
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
     * Map a QuoteStatus to the row payload. `actions` is attached by the
     * generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var QuoteStatus $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'color' => $row->color,
            'sort_order' => $row->sort_order,
            // spec 0065: the mandatory system rows (D-2) and the fixed
            // 3-value classification (`group`).
            'system_key' => $row->system_key,
            'group' => $row->group->value,
            'created_at' => $row->created_at,
        ];
    }

    /**
     * Allowed action keys for a single row, via QuoteStatusPolicy. `delete`
     * is OMITTED for a system row (spec 0065, D-2 — never deletable); `edit`
     * REMAINS (name/color are still editable).
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var QuoteStatus $row */
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
        }

        if (Gate::forUser($actor)->allows('update', $row)) {
            $allowed[] = 'edit';
        }

        if (! $row->isSystem() && Gate::forUser($actor)->allows('delete', $row)) {
            $allowed[] = 'delete';
        }

        if (Gate::forUser($actor)->allows('viewActivity', $row)) {
            $allowed[] = 'activity';
        }

        return $allowed;
    }

    /**
     * Delegate to QuoteStatusService::delete() so the generic bulk-delete
     * endpoint respects the SAME guards (system-row protection spec 0065
     * D-2, referenced-by guard) as the single DELETE
     * /quote-statuses/{quoteStatus} endpoint.
     */
    public function deleteModel(Model $model): void
    {
        /** @var QuoteStatus $model */
        $this->service->delete($model);
    }
}
