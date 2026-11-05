<?php

namespace App\Http\Resources;

use App\Models\ChartAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Expects the `parent` relation and the `children`/`costTypes`/`revenueTypes`
 * counts loaded (ChartAccountService::present()).
 *
 * @mixin ChartAccount
 */
class ChartAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $actor = $request->user();

        return [
            'id' => $this->id,
            'level' => $this->level->value,
            'parent_id' => $this->parent_id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'kind' => $this->effectiveKind()?->value,
            'is_active' => $this->is_active,
            'children_count' => (int) $this->children_count + (int) $this->cost_types_count + (int) $this->revenue_types_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'abilities' => [
                'update' => (bool) $actor?->can('chart-accounts.update'),
                'delete' => (bool) $actor?->can('chart-accounts.delete'),
            ],
        ];
    }
}
