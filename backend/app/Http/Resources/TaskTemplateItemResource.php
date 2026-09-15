<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TaskTemplateItem;
use App\RichText\RichText;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of a TaskTemplate's `items` array (spec 0124, data_contract). Never
 * returned on its own (D-1: no dedicated endpoint) — always nested under
 * TaskTemplateResource, ordered by `sort_order` (TaskTemplate::items()).
 *
 * `attachments` reuses AttachmentResource as-is: a superset of the
 * data_contract's `{id, original_name, mime_type, extension, size,
 * created_at}` shape, carrying `download_url`/`view_url` the row's own file
 * management (D-9) needs — the same resource every other attachable already
 * exposes, not a second bespoke projection. The `rich_text` collection (spec
 * 0128, D-6) is filtered OUT of the already eager-loaded `attachments`
 * Collection (in-memory `where()`, no extra query): those images are
 * reachable only through the row's own `description` content, never through
 * this generic file list.
 *
 * @mixin TaskTemplateItem
 */
class TaskTemplateItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'estimated_minutes' => $this->estimated_minutes,
            'task_status_id' => $this->task_status_id,
            'task_status' => $this->whenLoaded('taskStatus', fn () => $this->taskStatus === null ? null : [
                'id' => $this->taskStatus->id,
                'name' => $this->taskStatus->name,
                'color' => $this->taskStatus->color,
                'group' => $this->taskStatus->group->value,
            ]),
            'due_offset_days' => $this->due_offset_days,
            'sort_order' => $this->sort_order,
            'attachments' => AttachmentResource::collection($this->whenLoaded(
                'attachments',
                fn () => $this->attachments->where('collection', '!=', RichText::ATTACHMENT_COLLECTION)->values(),
            )),
        ];
    }
}
