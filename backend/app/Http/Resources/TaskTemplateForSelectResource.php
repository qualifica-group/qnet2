<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\TaskTemplate;
use Illuminate\Http\Request;

/**
 * For-select projection of a TaskTemplate (GET /api/task-templates/for-select,
 * spec 0124). `meta.items_count` lets the picker preview the row count
 * before opening the model — the count comes off TaskTemplateService::
 * forSelect()'s own `withCount('items')` projection.
 *
 * @mixin TaskTemplate
 */
class TaskTemplateForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->name,
            'subtitle' => $this->description,
            'meta' => ['items_count' => (int) $this->items_count],
        ];
    }
}
