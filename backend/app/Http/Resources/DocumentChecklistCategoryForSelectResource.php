<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\DocumentChecklistCategory;
use Illuminate\Http\Request;

/**
 * For-select projection of a DocumentChecklistCategory (ADR 0011, spec 0410).
 *
 * @mixin DocumentChecklistCategory
 */
class DocumentChecklistCategoryForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->name,
        ];
    }
}
