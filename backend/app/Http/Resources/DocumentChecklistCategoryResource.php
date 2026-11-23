<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DocumentChecklistCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Relies on the caller having loaded `checklists_count`
 * (DocumentChecklistCategoryService::loadDetail()).
 *
 * @mixin DocumentChecklistCategory
 */
class DocumentChecklistCategoryResource extends JsonResource
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
            'sort_order' => $this->sort_order,
            'checklists_count' => (int) $this->checklists_count,
            'sync_status' => $this->sync_status,
            'sync_error' => $this->sync_error,
            'synced_at' => $this->synced_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
