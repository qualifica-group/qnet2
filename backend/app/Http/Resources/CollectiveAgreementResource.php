<?php

namespace App\Http\Resources;

use App\Models\CollectiveAgreement;
use App\Models\CollectiveAgreementLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Expects `levels` loaded with `contracts_count` (spec 0272, see
 * CollectiveAgreementService::withRelations).
 *
 * @mixin CollectiveAgreement
 */
class CollectiveAgreementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $actor = $request->user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'levels' => $this->levels
                ->map(static fn (CollectiveAgreementLevel $level): array => [
                    'id' => $level->id,
                    'code' => $level->code,
                    'description' => $level->description,
                    'sort_order' => $level->sort_order,
                    'contracts_count' => (int) $level->contracts_count,
                ])
                ->all(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'abilities' => [
                'update' => (bool) $actor?->can('collective-agreements.update'),
                'delete' => (bool) $actor?->can('collective-agreements.delete'),
            ],
        ];
    }
}
