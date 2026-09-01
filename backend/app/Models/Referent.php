<?php

namespace App\Models;

use App\Enums\ReferentContactScopeEnum;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\HasPersonalData;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\ReferentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A contact person/entity (spec 0016) that reuses the `users` anagraphic
 * stack unchanged via `HasPersonalData` (morph `personable`): the referent
 * owns a personal-data card, which in turn owns its own contacts/addresses.
 *
 * `name` is denormalized display data derived from the card (mirrors
 * `users.name`, see ReferentProfileWriter). `referent_type_id` is an
 * optional classification; `contact_scope` distinguishes internal/external
 * referents.
 */
#[Fillable(['name', 'referent_type_id', 'contact_scope', 'notes', 'user_id'])]
class Referent extends BaseModel
{
    /** @use HasFactory<ReferentFactory> */
    use HasFactory, HasPersonalData, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contact_scope' => ReferentContactScopeEnum::class,
            // Spec 0013 — external data migration: the source system's id for a
            // migrated referent, guarded (not in #[Fillable]) so it is only ever
            // set by property assignment post-create.
            'old_id' => 'integer',
        ];
    }

    /**
     * The referent's classification, if any (nullOnDelete: removing the type
     * just clears it here, it never cascades a delete).
     */
    public function referentType(): BelongsTo
    {
        return $this->belongsTo(ReferentType::class);
    }

    /**
     * The system user this referent IS, if the link has been declared (spec
     * 0090, D-1/D-2): unique both ways (a user backs at most one referent),
     * nullOnDelete — the referent is an anagraphic in its own right and
     * survives the deletion of the linked account (AC-004). Feeds the
     * identity set `CommissionRecipientIdentities` builds when resolving
     * commission rules (D-5).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The registries (anagrafiche) this referent is associated to ("Referenti
     * per azienda" — spec 0020), inverse of Registry::referents(). Feeds the
     * referents/for-select `registry_id` scope (spec 0040 BR-4).
     */
    public function registries(): BelongsToMany
    {
        return $this->belongsToMany(Registry::class, 'referent_registry');
    }

    /**
     * The opportunities that name this referent as their own contact person
     * (spec 0040, BR-3: restrict-on-delete — ReferentService::delete() guards
     * on this, PLUS the commercial/reporter roles below, before deleting).
     *
     * @return HasMany<Opportunity, $this>
     */
    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    /**
     * @return HasMany<Opportunity, $this>
     */
    public function opportunitiesAsCommercial(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'commercial_id');
    }

    /**
     * @return HasMany<Opportunity, $this>
     */
    public function opportunitiesAsReporter(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'reporter_id');
    }

    /**
     * The vouchers/rewards/incentives assigned to this referent as
     * beneficiary (spec 0059, D-5 — a plain FK, not polymorphic; the
     * polymorphism lives on `Reward::source()`). CASCADE on delete: a
     * deleted referent takes its assignments with it (AC-003).
     *
     * @return HasMany<Reward, $this>
     */
    public function rewards(): HasMany
    {
        return $this->hasMany(Reward::class);
    }
}
