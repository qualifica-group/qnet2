<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsTaskBadgeRefs;
use App\Models\Task;
use App\RichText\RichTextPlainText;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * BoardTask shape (spec 0146 data_contract): the lean row the task board's
 * list/kanban views render, distinct from `TaskResource`'s full detail —
 * `task_type`/`task_priority`/`task_status` are rendered through the SAME
 * `FormatsTaskBadgeRefs` trait TaskResource uses (data_contract: "usano la
 * stessa forma di TaskResource::badgeRef()/statusRef()").
 *
 * `permissions` is NOT computed here: it is the SAME block `GET
 * /api/tasks/{id}` returns (data_contract), built once per row by the
 * caller (`ResourcePermissionsBuilder`, exactly like `TaskController::show`)
 * and handed in — a Resource has no business re-deriving authorization.
 *
 * `actual_minutes`/`attachments_count` come off the two aggregates
 * `App\Services\WorkOrders\TaskBoardQuery` attaches
 * (`withSum`/`withCount`): `actual_minutes` is coalesced to 0 because SQL
 * `SUM()` over zero rows is `NULL`, not `0` — the data_contract's own `int`,
 * never nullable.
 *
 * `description_excerpt` (user directive 2026-09-22) is the rich text
 * description flattened to one plain line and cut at
 * DESCRIPTION_EXCERPT_LIMIT characters, `null` when there is no visible text:
 * the board shows it under the title, never the raw HTML.
 *
 * @mixin Task
 */
class BoardTaskResource extends JsonResource
{
    use FormatsTaskBadgeRefs;

    private const int DESCRIPTION_EXCERPT_LIMIT = 160;

    /**
     * @param  array<string, mixed>  $permissions
     */
    public function __construct(Task $resource, private readonly array $permissions)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description_excerpt' => $this->descriptionExcerpt(),
            'parent_task_id' => $this->parent_task_id,
            'work_order_stage_id' => $this->work_order_stage_id,
            'stage_position' => $this->stage_position,
            'task_status' => $this->statusRef(),
            'task_type' => $this->badgeRef($this->taskType),
            'task_priority' => $this->badgeRef($this->taskPriority),
            'requester' => $this->nameRef($this->requester),
            'assignees' => $this->summarizeUsers($this->assignees),
            'watchers' => $this->summarizeUsers($this->watchers),
            'start_date' => $this->formatDate($this->start_date),
            'end_date' => $this->formatDate($this->end_date),
            'estimated_minutes' => $this->estimated_minutes,
            'actual_minutes' => (int) ($this->actual_minutes ?? 0),
            'is_blocked' => $this->is_blocked,
            'attachments_count' => (int) $this->attachments_count,
            'permissions' => $this->permissions,
        ];
    }

    private function descriptionExcerpt(): ?string
    {
        $excerpt = RichTextPlainText::excerpt($this->description, self::DESCRIPTION_EXCERPT_LIMIT);

        return $excerpt === '' ? null : $excerpt;
    }
}
