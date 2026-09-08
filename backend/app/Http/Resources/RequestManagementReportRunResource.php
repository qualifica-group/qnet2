<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ExportRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The `export_run` shape frozen by spec 0106 data_contract: DELIBERATELY
 * narrower than the generic ExportRunResource (spec 0014) — no
 * `resource`/`format`/`row_count`/`has_file`, and `file_name` instead of
 * `original_filename`.
 *
 * @mixin ExportRun
 */
class RequestManagementReportRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ExportRun $exportRun */
        $exportRun = $this->resource;

        return [
            'id' => $exportRun->id,
            'status' => $exportRun->status->value,
            'file_name' => $exportRun->original_filename,
            'created_at' => $exportRun->created_at,
        ];
    }
}
