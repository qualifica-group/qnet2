<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DocumentChecklist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Relies on the caller having loaded `items` (DocumentChecklistService::loadDetail()).
 *
 * @mixin DocumentChecklist
 */
class DocumentChecklistResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'code_locked' => $this->isCodeLocked(),
            'sync_status' => $this->sync_status,
            'sync_error' => $this->sync_error,
            'synced_at' => $this->synced_at,
            'items' => DocumentChecklistItemResource::collection($this->items),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
