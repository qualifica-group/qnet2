<?php

namespace App\Http\Resources\Migration;

use App\Models\MigrationRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MigrationRun
 *
 * The frozen `migration_run` shape (spec 0013 data_contract) — two variants
 * of the SAME resource, toggled by withReport():
 *
 * - POST .../import (default): {..., has_report: bool} — the run has just
 *   been created, so a derived flag is enough.
 * - GET .../runs/{id} (withReport()): {..., report: array|null} — the actual
 *   per-row warnings/errors for the polling summary.
 */
class MigrationRunResource extends JsonResource
{
    private bool $includeReport = false;

    public function withReport(): self
    {
        $this->includeReport = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MigrationRun $run */
        $run = $this->resource;

        $data = [
            'id' => $run->id,
            'source' => $run->source,
            'status' => $run->status->value,
            'total_rows' => $run->total_rows,
            'created_rows' => $run->created_rows,
            'skipped_rows' => $run->skipped_rows,
            'failed_rows' => $run->failed_rows,
            'updated_since' => $run->updated_since?->format('Y-m-d'),
            'created_at' => $run->created_at,
        ];

        return $this->includeReport
            ? [...$data, 'report' => $run->report]
            : [...$data, 'has_report' => $run->report !== null];
    }
}
