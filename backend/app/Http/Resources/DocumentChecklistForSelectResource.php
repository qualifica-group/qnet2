<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DocumentChecklist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Option of the "Richiedi documenti" checklist select (spec 0428). Relies on the
 * caller having loaded `category`.
 *
 * @mixin DocumentChecklist
 */
class DocumentChecklistForSelectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->name,
            'code' => $this->code,
            'category_name' => $this->category?->name,
        ];
    }
}
