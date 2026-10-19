<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use Illuminate\Http\Request;

/**
 * For-select projection of the skills catalogue (spec 0286): label = name,
 * `meta.category` groups the options.
 *
 * @property int $id
 * @property string $name
 * @property string|null $category
 */
class SkillForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return ['id' => $this->id, 'label' => $this->name, 'meta' => ['category' => $this->category]];
    }
}
