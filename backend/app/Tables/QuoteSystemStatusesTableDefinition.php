<?php

declare(strict_types=1);

namespace App\Tables;

use App\Models\QuoteSystemStatus;
use App\Models\User;
use App\Services\QuoteSystemStatuses\QuoteSystemStatusService;
use App\Tables\QuoteSystemStatuses\QuoteSystemStatusColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition of the `quote-system-statuses` configurator (spec 0404
 * part A). viewAny comes from QuoteSystemStatusPolicy via modelClass().
 */
class QuoteSystemStatusesTableDefinition extends AbstractTableDefinition
{
    public function __construct(private readonly QuoteSystemStatusService $service) {}

    public function domain(): string
    {
        return 'quote-system-statuses';
    }

    /**
     * @return class-string<QuoteSystemStatus>
     */
    public function modelClass(): string
    {
        return QuoteSystemStatus::class;
    }

    /**
     * @return Builder<QuoteSystemStatus>
     */
    public function baseQuery(): Builder
    {
        return QuoteSystemStatus::query()->withCount('workflowStatuses');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return QuoteSystemStatusColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return QuoteSystemStatusColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return QuoteSystemStatusColumnCatalog::actions();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [['columnId' => 'sort_order', 'direction' => 'asc']];
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
        /** @var QuoteSystemStatus $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'color' => $row->color,
            'behavior' => $row->behavior->value,
            'system_key' => $row->system_key,
            'sort_order' => $row->sort_order,
            'workflow_statuses_count' => (int) ($row->workflow_statuses_count ?? 0),
            'created_at' => $row->created_at,
        ];
    }

    /**
     * `delete` is never offered on a system row (A-D3).
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var QuoteSystemStatus $row */
        $gate = Gate::forUser($actor);
        $allowed = [];

        if ($gate->allows('view', $row)) {
            $allowed[] = 'view';
        }

        if (! $row->isSystem() && $gate->allows('delete', $row)) {
            $allowed[] = 'delete';
        }

        if ($gate->allows('viewActivity', $row)) {
            $allowed[] = 'activity';
        }

        return $allowed;
    }

    /**
     * Bulk delete goes through the same guards as DELETE /quote-system-statuses/{id}.
     */
    public function deleteModel(Model $model): void
    {
        /** @var QuoteSystemStatus $model */
        $this->service->delete($model);
    }
}
