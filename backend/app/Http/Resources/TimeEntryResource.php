<?php

namespace App\Http\Resources;

use App\Models\Task;
use App\Models\TaskType;
use App\Models\TimeEntry;
use App\Models\WorkOrder;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TimeEntry shape (spec 0122, data_contract SHAPE TimeEntry), reused by
 * store/show/update and by the Task-scoped list/create (D-9).
 *
 * `permissions` is computed server-side for the CURRENT user via
 * TimeEntryPolicy (D-8's ownership rule), mirroring NoteResource's own
 * `can` block — the UI hides actions off it, the backend still re-checks on
 * every write (defense in depth, security.md).
 *
 * Every relation this resource reads is assumed eager-loaded by the caller
 * (TimeEntryService::loadDetail()) — nothing here triggers a lazy load
 * (backend.md §3, `preventLazyLoading`).
 *
 * @mixin TimeEntry
 */
class TimeEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TimeEntry $entry */
        $entry = $this->resource;
        $user = $request->user();

        return [
            'id' => $entry->id,
            'user' => $this->nameRef($entry->user),
            'date' => $this->formatDate($entry->date),
            'title' => $entry->title,
            'task_type' => $this->taskTypeRef($entry->taskType),
            'start_time' => $this->formatTime($entry->start_time),
            'end_time' => $this->formatTime($entry->end_time),
            'minutes' => $entry->minutes,
            'notes' => $entry->notes,
            'registry' => $this->nameRef($entry->registry),
            'opportunity' => $this->nameRef($entry->opportunity),
            'work_order' => $this->workOrderRef($entry->workOrder),
            'task' => $this->taskRef($entry->task),
            'work_order_stage' => $this->nameRef($entry->workOrderStage),
            'created_at' => $entry->created_at?->toIso8601String(),
            'updated_at' => $entry->updated_at?->toIso8601String(),
            'permissions' => [
                'update' => $user !== null && $user->can('update', $entry),
                'delete' => $user !== null && $user->can('delete', $entry),
            ],
        ];
    }

    /**
     * @return array{id: int, name: string, color: string|null, icon: string|null}|null
     */
    private function taskTypeRef(?TaskType $taskType): ?array
    {
        if ($taskType === null) {
            return null;
        }

        return [
            'id' => $taskType->id,
            'name' => $taskType->name,
            'color' => $taskType->color,
            'icon' => $taskType->icon,
        ];
    }

    /**
     * @return array{id: int, code: string, title: string}|null
     */
    private function workOrderRef(?WorkOrder $workOrder): ?array
    {
        if ($workOrder === null) {
            return null;
        }

        return ['id' => $workOrder->id, 'code' => $workOrder->code, 'title' => $workOrder->title];
    }

    /**
     * @return array{id: int, title: string}|null
     */
    private function taskRef(?Task $task): ?array
    {
        if ($task === null) {
            return null;
        }

        return ['id' => $task->id, 'title' => $task->title];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function nameRef(?Model $related): ?array
    {
        return $related === null ? null : ['id' => $related->id, 'name' => $related->name];
    }

    /**
     * `Y-m-d`, the shape the data_contract declares (same reasoning as
     * TaskResource::formatDate — the `date:Y-m-d` cast governs the MODEL's
     * own serialization, not what a Resource hands to json_encode).
     */
    private function formatDate(?CarbonInterface $date): ?string
    {
        return $date?->format('Y-m-d');
    }

    /**
     * `H:i`, the shape the form submits and MySQL/SQLite may not echo back
     * identically (same reasoning as TaskResource::formatTime).
     */
    private function formatTime(?string $time): ?string
    {
        return $time === null || $time === '' ? null : substr($time, 0, 5);
    }
}
