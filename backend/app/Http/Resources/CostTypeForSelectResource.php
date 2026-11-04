<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\CostType;
use Illuminate\Http\Request;

/**
 * For-select projection of a CostType (GET /api/cost-types/for-select, spec 0210).
 *
 * @mixin CostType
 */
class CostTypeForSelectResource extends ForSelectResource
{
    /**
     * The placement keys stay present as null for an unplaced type (spec 0350,
     * D-12), which the base class would otherwise strip.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $account = $this->chartAccount;
        $group = $account?->parent;

        return [
            ...parent::toArray($request),
            'code' => $this->code,
            'path' => $account === null ? null : "{$group?->code} {$group?->name} > {$account->code} {$account->name}",
            'group' => $group === null ? null : ['code' => $group->code, 'name' => $group->name],
            'account' => $account === null ? null : ['code' => $account->code, 'name' => $account->name],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->name,
            'meta' => [
                'color' => $this->color,
                'nature' => $this->nature->value,
            ],
        ];
    }
}
