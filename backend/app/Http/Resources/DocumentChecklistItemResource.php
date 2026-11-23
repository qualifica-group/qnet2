<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DocumentChecklistItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DocumentChecklistItem
 */
class DocumentChecklistItemResource extends JsonResource
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
            'section' => $this->section,
            'is_required' => $this->is_required,
            'allowed_file_types' => $this->allowed_file_types,
            'max_file_size_kb' => $this->max_file_size_kb,
            'min_files' => $this->min_files,
            'max_files' => $this->max_files,
            'validation_prompt' => $this->validation_prompt,
            'max_age_days' => $this->max_age_days,
        ];
    }
}
