<?php

namespace App\Models;

use App\Enums\ImportRowResolution;
use App\Enums\ImportRowStatus;
use App\Models\Abstracts\BaseModel;
use Database\Factories\ImportRunRowFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One staged file row of the unified import wizard (spec 0033), written by
 * StageImportJob (raw/mapped/extra values, recognizer/dedup output in
 * `resolved`, per-row `status`/`messages`) and later read back by
 * ProcessImportJob to commit — the process phase never re-parses the source
 * file. Also backs the SSRM review grid, where `is_edited` flags a row
 * corrected inline before confirm.
 *
 * `product_ids` (spec 0094, D-4/AC-054): a plain JSON array column (no
 * pivot — these are staged input, not yet a Lead's own products),
 * three-state like `operator_id`/`operational_site_id` but for a
 * collection: `null` inherits the run's global `product_ids`, `[]` means
 * this row carries none, a non-empty array is the row's own explicit
 * override. Resolved to labels by ImportRunRowResource, batched per page.
 */
class ImportRunRow extends BaseModel
{
    /** @use HasFactory<ImportRunRowFactory> */
    use HasFactory;

    protected $fillable = [
        'import_run_id',
        'row_number',
        'raw_values',
        'mapped_values',
        'extra_values',
        'resolved',
        'status',
        'messages',
        'duplicate_of_id',
        'duplicate_meta',
        'resolution',
        'is_edited',
        'operator_id',
        'operational_site_id',
        'product_ids',
    ];

    protected $casts = [
        'import_run_id' => 'int',
        'row_number' => 'int',
        'raw_values' => 'array',
        'mapped_values' => 'array',
        'extra_values' => 'array',
        'resolved' => 'array',
        'status' => ImportRowStatus::class,
        'messages' => 'array',
        'duplicate_of_id' => 'int',
        'duplicate_meta' => 'array',
        'resolution' => ImportRowResolution::class,
        'is_edited' => 'bool',
        'operator_id' => 'int',
        'operational_site_id' => 'int',
        'product_ids' => 'array',
    ];

    public function importRun(): BelongsTo
    {
        return $this->belongsTo(ImportRun::class);
    }

    /**
     * Per-row Operator override (spec 0045): when set, overrides the run's
     * global `operator_id` for this staged row only.
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Per-row Operational Site override, mirroring operator(): when set,
     * overrides the run's global `operational_site_id` for this staged row
     * only.
     */
    public function operationalSite(): BelongsTo
    {
        return $this->belongsTo(OperationalSite::class);
    }
}
