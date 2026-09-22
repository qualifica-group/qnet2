<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TaskTemplateStage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One "Fase" of a TaskTemplate's `stages` array (spec 0146, data_contract).
 * Never returned on its own (D-2: no dedicated endpoint) — always nested
 * under TaskTemplateResource, ordered by `sort_order` (TaskTemplate::stages()).
 *
 * @mixin TaskTemplateStage
 */
class TaskTemplateStageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sort_order' => $this->sort_order,
        ];
    }
}
