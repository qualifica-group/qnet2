<?php

namespace App\Models;

use App\Enums\MigrationStatus;
use App\Models\Abstracts\BaseModel;
use Database\Factories\MigrationRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per external-data migration run (spec 0013): tracks the two-phase
 * (read-only preview -> queued import) flow's status, per-row counters and
 * report for a given source (App\Migrations\MigrationRegistry /
 * config/migrations.php). `source` is the registry key — NOT a foreign key,
 * mirroring the generic Tables/Imports frameworks so the engine never
 * couples to a specific resource's schema.
 */
class MigrationRun extends BaseModel
{
    /** @use HasFactory<MigrationRunFactory> */
    use HasFactory;

    protected $fillable = [
        'source',
        'user_id',
        'mass_migration_run_id',
        'status',
        'total_rows',
        'created_rows',
        'skipped_rows',
        'failed_rows',
        'report',
        'updated_since',
    ];

    protected $casts = [
        'source' => 'string',
        'status' => MigrationStatus::class,
        'total_rows' => 'int',
        'created_rows' => 'int',
        'skipped_rows' => 'int',
        'failed_rows' => 'int',
        'report' => 'array',
        'updated_since' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The parent "Import all" run (spec 0046), or null for a single-source run.
     *
     * @return BelongsTo<MassMigrationRun, $this>
     */
    public function massMigrationRun(): BelongsTo
    {
        return $this->belongsTo(MassMigrationRun::class);
    }
}
