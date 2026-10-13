<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\SectorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sector tree node (spec 0018): unlimited-depth parent/child hierarchy,
 * a standalone lookup used to classify Anagrafiche in the future (no such
 * relation exists yet — see spec 0018 scope).
 */
#[Fillable(['code', 'name', 'parent_id', 'is_active'])]
class Sector extends BaseModel
{
    /** @use HasFactory<SectorFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Spec 0013 — external data migration: the source system's id for a
            // migrated sector, guarded (not in #[Fillable]) so it is only ever
            // set by property assignment post-create. Also the remap key for the
            // self-referential `parent_id` (child → parent via old_id).
            'old_id' => 'integer',
            // Spec 0212 — this node's OWN flag, never rewritten on children:
            // the EFFECTIVE activity (an inactive ancestor deactivates the
            // whole subtree, D-1) is resolved at read time by SectorActivity.
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
