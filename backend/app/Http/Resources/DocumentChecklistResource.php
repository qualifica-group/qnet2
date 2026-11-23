<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DocumentChecklist;
use App\Models\DocumentChecklistItem;
use App\Models\DocumentChecklistSection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Relies on the caller having loaded `items`, `sections` and `category` (DocumentChecklistService::loadDetail()).
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
        $sectionIndexes = $this->sections->pluck('id')->flip();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'category' => $this->category === null ? null : ['id' => $this->category->id, 'name' => $this->category->name],
            'code_locked' => $this->isCodeLocked(),
            'sync_status' => $this->sync_status,
            'sync_error' => $this->sync_error,
            'synced_at' => $this->synced_at,
            'sections' => $this->sections->map(fn (DocumentChecklistSection $section): array => [
                'id' => $section->id,
                'name' => $section->name,
                'description' => $section->description,
            ])->all(),
            'items' => $this->items->map(fn (DocumentChecklistItem $item): array => [
                ...(new DocumentChecklistItemResource($item))->resolve($request),
                'section_index' => $sectionIndexes[$item->section_id] ?? null,
            ])->all(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
