<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\SourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Source lookup entity (spec 0018): a full-CRUD classification (name only)
 * used to classify the provenance of registry records ("Anagrafiche"). Also
 * referenced by Lead (spec 0024, BR-2/D-4: restrict-on-delete).
 */
#[Fillable(['name'])]
class Source extends BaseModel
{
    /** @use HasFactory<SourceFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Spec 0013 — external data migration: the source system's id for a
            // migrated source, guarded (not in #[Fillable]) so it is only ever
            // set by property assignment post-create.
            'old_id' => 'integer',
        ];
    }

    /**
     * The leads that name this source (spec 0024, BR-2/D-4: restrict-on-
     * delete — SourceService::delete() guards on this before deleting).
     *
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * The campaigns naming this source (spec 0176, D-1: restrict-on-delete —
     * SourceService::delete() guards on this before deleting).
     *
     * @return HasMany<Campaign, $this>
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /**
     * The opportunities against this source (spec 0040, BR-3: restrict-on-
     * delete — SourceService::delete() guards on this before deleting).
     *
     * @return HasMany<Opportunity, $this>
     */
    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }
}
