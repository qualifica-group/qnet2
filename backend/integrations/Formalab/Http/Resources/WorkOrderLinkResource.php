<?php

declare(strict_types=1);

namespace Integrations\Formalab\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Integrations\Formalab\Models\FormalabWorkOrderLink;

/**
 * The Formalab tab of a work order. Wraps
 * `['eligible' => bool, 'can_manage' => bool, 'link' => ?FormalabWorkOrderLink]`.
 */
class WorkOrderLinkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var FormalabWorkOrderLink|null $link */
        $link = $this->resource['link'];

        return [
            'eligible' => $this->resource['eligible'],
            'can_manage' => $this->resource['can_manage'],
            'link' => $link === null ? null : [
                'edition_id' => $link->edition_id,
                'linked_at' => $link->created_at?->toIso8601String(),
                'linked_by' => $link->linkedBy === null ? null : ['id' => $link->linkedBy->id, 'name' => $link->linkedBy->name],
                'synced_at' => $link->synced_at?->toIso8601String(),
                'sync_error' => $link->sync_error,
                'class' => $link->formalabClass === null ? null : new FormalabClassResource($link->formalabClass),
            ],
        ];
    }
}
