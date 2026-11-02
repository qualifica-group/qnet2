<?php

declare(strict_types=1);

namespace App\Tables;

use App\Models\SupportTopic;
use App\Models\User;
use App\Services\SupportTopicService;
use App\Tables\SupportTopics\SupportTopicColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `support-topics` domain (spec 0334). viewAny is
 * derived from modelClass() (SupportTopicPolicy::viewAny).
 */
class SupportTopicsTableDefinition extends AbstractTableDefinition
{
    public function __construct(private readonly SupportTopicService $service) {}

    public function domain(): string
    {
        return 'support-topics';
    }

    /**
     * @return class-string<SupportTopic>
     */
    public function modelClass(): string
    {
        return SupportTopic::class;
    }

    /**
     * @return Builder<SupportTopic>
     */
    public function baseQuery(): Builder
    {
        return SupportTopic::query()->with(['taskPriority:id,name', 'assignees:id,name']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return SupportTopicColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return SupportTopicColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return SupportTopicColumnCatalog::actions();
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
        /** @var SupportTopic $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'icon' => $row->icon,
            'task_priority' => $row->taskPriority?->name,
            'assignees' => $row->assignees->pluck('name')->implode(', '),
            'due_days' => $row->due_days,
            'sort_order' => $row->sort_order,
            'is_active' => $row->is_active,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        $allowed = [];

        foreach (['view' => 'view', 'delete' => 'delete', 'viewActivity' => 'activity'] as $ability => $key) {
            if (Gate::forUser($actor)->allows($ability, $row)) {
                $allowed[] = $key;
            }
        }

        return $allowed;
    }

    public function deleteModel(Model $model): void
    {
        /** @var SupportTopic $model */
        $this->service->delete($model);
    }
}
