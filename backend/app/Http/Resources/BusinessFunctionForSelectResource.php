<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\BusinessFunction;
use Illuminate\Http\Request;

/**
 * For-select projection of a BusinessFunction (GET /api/business-functions/for-select).
 *
 * Minimal by design (ADR 0011): label = name, no subtitle/avatar. Mirrors
 * RoleForSelectResource.
 *
 * @mixin BusinessFunction
 */
class BusinessFunctionForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->name,
            // Spec 0208, D-4: the form proposes the function's manager.
            'manager' => $this->manager === null ? null : ['id' => $this->manager->id, 'name' => $this->manager->name],
        ];
    }
}
