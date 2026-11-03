<?php

namespace App\Models;

use App\Enums\ImportStatus;
use App\Models\Abstracts\BaseModel;
use Database\Factories\ImportRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row per CSV import upload (spec 0012): tracks the two-phase
 * (validate dry-run -> confirm commit) flow's status, counts, bounded preview
 * and the downloadable errors report. `resource` is the `{domain}` key
 * (App\Imports\ImportRegistry / config/imports.php) the run was started
 * against — NOT a foreign key, mirroring the generic Tables framework.
 */
class ImportRun extends BaseModel
{
    /** @use HasFactory<ImportRunFactory> */
    use HasFactory;

    protected $fillable = [
        'resource',
        'user_id',
        'status',
        'original_filename',
        'stored_path',
        'total_rows',
        'valid_rows',
        'invalid_rows',
        'imported_rows',
        'error_report_path',
        'preview',
        'detected_columns',
        'column_mapping',
        'global_config',
        'value_mappings',
        'dedup_strategy',
        'warning_rows',
        'duplicate_rows',
        'modified_rows',
        'notified_at',
        'error_count',
        'convert_to_opportunity',
    ];

    protected $casts = [
        'resource' => 'string',
        'status' => ImportStatus::class,
        'original_filename' => 'string',
        'stored_path' => 'string',
        'total_rows' => 'int',
        'valid_rows' => 'int',
        'invalid_rows' => 'int',
        'imported_rows' => 'int',
        'error_report_path' => 'string',
        'preview' => 'array',
        'detected_columns' => 'array',
        'column_mapping' => 'array',
        'global_config' => 'array',
        'value_mappings' => 'array',
        'dedup_strategy' => 'string',
        'warning_rows' => 'int',
        'duplicate_rows' => 'int',
        'modified_rows' => 'int',
        'notified_at' => 'datetime',
        'error_count' => 'int',
        'convert_to_opportunity' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRunRow::class);
    }
}
