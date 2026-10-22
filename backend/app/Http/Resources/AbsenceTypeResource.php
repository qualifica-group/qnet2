<?php

namespace App\Http\Resources;

use App\Models\AbsenceType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AbsenceType
 */
class AbsenceTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'deducts_balance' => $this->deducts_balance,
            'requires_approval' => $this->requires_approval,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'allows_full_day' => $this->allows_full_day,
            'allows_half_day' => $this->allows_half_day,
            'allows_hourly' => $this->allows_hourly,
            'min_notice_days' => $this->min_notice_days,
            'color' => $this->color,
            'default_annual_days' => $this->default_annual_days === null ? null : (float) $this->default_annual_days,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
