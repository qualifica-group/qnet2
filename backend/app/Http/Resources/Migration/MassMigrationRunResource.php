<?php

namespace App\Http\Resources\Migration;

use App\Models\MassMigrationRun;
use App\Models\MigrationRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MassMigrationRun
 *
 * The frozen `mass_migration_run` shape (spec 0046 data_contract): the aggregate
 * status + the ordered snapshot of planned sources + the per-source child runs
 * (each in its `withReport()` variant, so the polling UI shows warnings/errors).
 * Children are ordered by creation (id), i.e. execution order.
 */
class MassMigrationRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MassMigrationRun $run */
        $run = $this->resource;

        return [
            'id' => $run->id,
            'status' => $run->status->value,
            'sources' => $run->sources,
            'updated_since' => $run->updated_since?->format('Y-m-d'),
            'update_existing' => $run->update_existing,
            'created_at' => $run->created_at,
            'runs' => $run->runs
                ->sortBy('id')
                ->values()
                ->map(fn (MigrationRun $child): MigrationRunResource => (new MigrationRunResource($child))->withReport()),
        ];
    }
}
