<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\WorkOrderProgressEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * WorkOrderProgressEntry shape (spec 0321 data_contract). `creator` must be
 * eager-loaded by the caller.
 *
 * @mixin WorkOrderProgressEntry
 */
class WorkOrderProgressEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'percentage' => $this->percentage,
            'recorded_on' => $this->recorded_on->format('Y-m-d'),
            'created_by' => $this->creator === null
                ? null
                : ['id' => $this->creator->id, 'name' => $this->creator->name],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
