<?php

declare(strict_types=1);

namespace App\Services\ArchivioSmart;

use App\Models\DocumentChecklistCategory;

/**
 * Builds the ArchivioSmart PUT /categories/{id} body from the current category state.
 */
class DocumentChecklistCategoryPayloadBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(DocumentChecklistCategory $category): array
    {
        return [
            'name' => $category->name,
            'description' => filled($category->description) ? $category->description : null,
            'sort_order' => (int) $category->sort_order,
        ];
    }
}
