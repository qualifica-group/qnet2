<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SupportTopic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Configuration detail of a support topic (spec 0334). Expects the relations
 * loaded by SupportTopicService::loadDetail().
 *
 * @mixin SupportTopic
 */
class SupportTopicResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'icon' => $this->icon,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'task_type' => $this->summary($this->taskType),
            'task_category' => $this->summary($this->taskCategory),
            'task_priority' => $this->summary($this->taskPriority),
            'urgent_task_priority' => $this->summary($this->urgentTaskPriority),
            'task_importance' => $this->summary($this->taskImportance),
            'due_days' => $this->due_days,
            'urgent_due_days' => $this->urgent_due_days,
            'is_private' => $this->is_private,
            'requires_closure_feedback' => $this->requires_closure_feedback,
            'assignees' => $this->assignees->map(fn (Model $user): array => $this->summary($user))->values()->all(),
            'watchers' => $this->watchers->map(fn (Model $user): array => $this->summary($user))->values()->all(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function summary(?Model $model): ?array
    {
        return $model === null ? null : ['id' => $model->id, 'name' => $model->name];
    }
}
