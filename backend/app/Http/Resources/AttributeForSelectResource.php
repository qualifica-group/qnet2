<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\Attribute;
use Illuminate\Http\Request;

/**
 * For-select projection of an Attribute (GET /api/attributes/for-select, spec
 * 0429): the code disambiguates fields with the same name (ISO and [OLD] ISO).
 *
 * @mixin Attribute
 */
class AttributeForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => "{$this->name} ({$this->code})",
        ];
    }
}
