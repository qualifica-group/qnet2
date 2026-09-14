<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Minimal, searchable, paginated Task list for the for-select standard
 * (ADR 0011), mirroring ReferentService::forSelect. The rows are restricted
 * by the visibility scope like every other read (spec 0101 D-9); the endpoint
 * itself carries no resource permission gate.
 *
 * Read path only, split out of App\Services\TaskService so that class holds
 * the write paths and their in-transaction guards alone.
 */
final class TaskForSelectService
{
    /**
     * $excludeId (data_contract) drops one id from the list — the Task's own
     * id in the parent picker, so a Task is never offered as its own parent
     * (AC-082).
     */
    public function forSelect(ForSelectQuery $query, ?int $excludeId = null): ForSelectResult
    {
        $base = $this->forSelectBase();

        if ($excludeId !== null) {
            $base->whereKeyNot($excludeId);
        }

        if ($query->hasSearch()) {
            $base->where('tasks.title', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, Task> $page */
        $page = $base->orderBy('tasks.title')
            ->orderBy('tasks.id')
            ->offset($query->offset)
            ->limit($query->limit)
            ->get();

        return new ForSelectResult(
            items: $this->appendHydratedIds($page, $query),
            total: $total,
            offset: $query->offset,
            limit: $query->limit,
        );
    }

    /**
     * The scoped, minimally-projected for-select base query. `taskStatus` is
     * eager-loaded because TaskForSelectResource exposes the status name as
     * the item subtitle.
     *
     * @return Builder<Task>
     */
    private function forSelectBase(): Builder
    {
        return TaskVisibilityScope::scopeToActor(
            Task::query()->select(['tasks.id', 'tasks.title', 'tasks.task_status_id'])->with('taskStatus'),
            Auth::user(),
        );
    }

    /**
     * Append the explicitly-requested `ids[]` (edit-mode hydration) that are
     * not already on the page, deduplicated. They bypass search — but NOT the
     * visibility scope, which is a security boundary, not a filter. Total is
     * unaffected.
     *
     * @param  Collection<int, Task>  $page
     * @return Collection<int, Task>
     */
    private function appendHydratedIds(Collection $page, ForSelectQuery $query): Collection
    {
        if (! $query->hasIds()) {
            return $page;
        }

        $missingIds = array_values(array_diff($query->ids, $page->pluck('id')->all()));

        if ($missingIds === []) {
            return $page;
        }

        return $page->concat($this->forSelectBase()->whereKey($missingIds)->get());
    }
}
