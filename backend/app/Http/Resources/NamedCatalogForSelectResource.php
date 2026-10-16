<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use Illuminate\Http\Request;

/**
 * For-select projection of the HR catalogues (job role, job
 * activity; spec 0271): label = name, nothing else.
 *
 * @property int $id
 * @property string $name
 */
class NamedCatalogForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return ['id' => $this->id, 'label' => $this->name];
    }
}
