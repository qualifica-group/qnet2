<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\WorkOrderStage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * WorkOrderStage shape (spec 0146 data_contract): `{ id, name, sort_order,
 * closed_at, closed_by }`. `closed_at` is handed through as the raw Carbon
 * instance (ISO-8601 on serialization, the data_contract's own "string|null
 * (ISO)") — unlike `TaskResource`'s DATE fields, this is a genuine timestamp,
 * not a plain calendar date, so no `Y-m-d` reformatting applies here.
 *
 * @mixin WorkOrderStage
 */
class WorkOrderStageResource extends JsonResource
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
            'closed_at' => $this->closed_at,
            'closed_by' => $this->closedBy === null
                ? null
                : ['id' => $this->closedBy->id, 'name' => $this->closedBy->name],
        ];
    }
}
