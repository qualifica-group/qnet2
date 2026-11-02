<?php

namespace App\Models;

use App\Enums\MigrationStatus;
use App\Models\Abstracts\BaseModel;
use Database\Factories\MassMigrationRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row per "Import all" run (spec 0046): the aggregate over the per-source
 * child MigrationRuns it executes in order (`runs()`). `sources` is the ordered
 * snapshot of enabled source keys taken at launch; `status` reuses
 * MigrationStatus (pending -> processing -> completed | failed, where `failed`
 * means the chain stopped at the first failing source).
 */
class MassMigrationRun extends BaseModel
{
    /** @use HasFactory<MassMigrationRunFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'sources',
        'status',
        'updated_since',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sources' => 'array',
            'status' => MigrationStatus::class,
            'updated_since' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<MigrationRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(MigrationRun::class);
    }
}
