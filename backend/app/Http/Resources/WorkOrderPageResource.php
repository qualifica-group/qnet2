<?php

namespace App\Http\Resources;

use App\Models\Task;
use App\Services\WorkOrders\Page\WorkOrderPageView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * WorkOrderPagePayload shape (spec 0380 data_contract). `tasks` and `polls` are
 * maps keyed by id and always JSON objects, even when empty.
 *
 * @mixin WorkOrderPageView
 */
class WorkOrderPageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $page = $this->page;

        return [
            'content' => $page?->content,
            'version' => $page?->version ?? 0,
            'updated_at' => $page?->updated_by_id === null ? null : $page->updated_at?->toIso8601String(),
            'updated_by' => $page?->updatedBy === null ? null : ['id' => $page->updatedBy->id, 'name' => $page->updatedBy->name],
            'can_edit' => $this->canEdit,
            'tasks' => (object) collect($this->tasks)
                ->map(fn (?Task $task, int $id): array => $this->taskRef($id, $task))
                ->all(),
            'polls' => (object) $this->polls
                ->mapWithKeys(fn ($poll): array => [$poll->id => WorkOrderPagePollResource::make($poll)->resolve($request)])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function taskRef(int $id, ?Task $task): array
    {
        if ($task === null) {
            return [
                'id' => $id,
                'available' => false,
                'title' => null,
                'status' => null,
                'end_date' => null,
                'completed' => false,
                'assignees' => [],
            ];
        }

        return [
            'id' => $task->id,
            'available' => true,
            'title' => $task->title,
            'status' => $task->taskStatus === null ? null : [
                'id' => $task->taskStatus->id,
                'name' => $task->taskStatus->name,
                'color' => $task->taskStatus->color,
            ],
            'end_date' => $task->end_date?->format('Y-m-d'),
            'completed' => $task->completion_date !== null,
            'assignees' => $task->assignees->map(fn ($user): array => ['id' => $user->id, 'name' => $user->name])->values()->all(),
        ];
    }
}
