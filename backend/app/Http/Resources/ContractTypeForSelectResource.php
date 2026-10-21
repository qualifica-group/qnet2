<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use Illuminate\Http\Request;

/**
 * For-select projection of a contract type (spec 0290): label = name.
 *
 * @property int $id
 * @property string $name
 */
class ContractTypeForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return ['id' => $this->id, 'label' => $this->name];
    }
}
