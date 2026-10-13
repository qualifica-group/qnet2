<?php

namespace App\Http\Resources\Migration;

use App\Migrations\MigrationOrder;
use App\Migrations\MigrationSource;
use App\Models\MigrationRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin MigrationSource
 *
 * The `sources` item shape (spec 0013 data_contract): `{key, label}`, the
 * 1-based MigrationOrder `phase` it is imported in, and the source's migration
 * state — `last_run` (its latest run, any status, or null when it never ran)
 * and `migrated_at` (when a run last completed, or null).
 */
class MigrationSourceResource extends JsonResource
{
    public function __construct(
        MigrationSource $source,
        private readonly ?MigrationRun $lastRun = null,
        private readonly ?Carbon $migratedAt = null,
    ) {
        parent::__construct($source);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MigrationSource $source */
        $source = $this->resource;

        return [
            'key' => $source->key(),
            'label' => $source->label(),
            'phase' => MigrationOrder::phaseOf($source->key()),
            'last_run' => $this->lastRun !== null ? new MigrationRunResource($this->lastRun) : null,
            'migrated_at' => $this->migratedAt,
        ];
    }
}
