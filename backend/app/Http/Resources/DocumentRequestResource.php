<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DocumentRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The files of the snapshot are summarized as a count: names and links stay in
 * ArchivioSmart. `permissions` follow the `update` permission and the state; the
 * caller has already checked the work order.
 *
 * @mixin DocumentRequest
 */
class DocumentRequestResource extends JsonResource
{
    private const array ITEM_KEYS = ['id', 'section_index', 'name', 'description', 'is_required', 'min_files', 'max_files', 'status', 'status_reason'];

    private const array VALIDATION_KEYS = ['outcome', 'reason_for_customer', 'decided_by', 'created_at'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $snapshot = $this->snapshot ?? [];
        $canUpdate = (bool) $request->user()?->can('document-requests.update');

        return [
            'id' => $this->id,
            'work_order_id' => $this->work_order_id,
            'checklist' => ['id' => $this->document_checklist_id, 'code' => $this->checklist_code, 'name' => $this->checklist_name],
            'category_name' => $this->category_name,
            'due_date' => $this->due_date?->toDateString(),
            'recipient_email' => $this->recipient_email,
            'message' => $this->message,
            'status' => $this->status,
            'title' => $this->title,
            'items_count' => $this->items_count,
            'items_uploaded_count' => $this->items_uploaded_count,
            'items_satisfied_count' => $this->items_satisfied_count,
            'sections' => array_map(
                static fn (array $section): array => ['name' => $section['name'] ?? null, 'description' => $section['description'] ?? null],
                $snapshot['sections'] ?? [],
            ),
            'items' => array_map($this->item(...), $snapshot['items'] ?? []),
            'sent_at' => $this->sent_at,
            'last_reminded_at' => $this->last_reminded_at,
            'completed_at' => $this->completed_at,
            'cancelled_at' => $this->cancelled_at,
            'expired_at' => $this->expired_at,
            'link_expires_at' => $this->link_expires_at,
            'remote_updated_at' => $this->remote_updated_at,
            'delivery_status' => $this->delivery_status,
            'pending_operation' => $this->pending_operation,
            'delivery_error' => $this->delivery_error,
            'created_at' => $this->created_at,
            'permissions' => [
                'update' => $canUpdate && ! $this->isClosed(),
                'remind' => $canUpdate && $this->isRemindable(),
                'cancel' => $canUpdate && ! $this->isClosed(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function item(array $item): array
    {
        $validation = $item['latest_validation'] ?? null;

        return [
            ...array_merge(array_fill_keys(self::ITEM_KEYS, null), array_intersect_key($item, array_flip(self::ITEM_KEYS))),
            'latest_validation' => is_array($validation)
                ? array_merge(array_fill_keys(self::VALIDATION_KEYS, null), array_intersect_key($validation, array_flip(self::VALIDATION_KEYS)))
                : null,
            'documents_count' => count($item['documents'] ?? []),
        ];
    }
}
