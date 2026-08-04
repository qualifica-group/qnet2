<?php

namespace App\Models;

use App\Enums\AgreementStatusEnum;
use App\Enums\SizeClassEnum;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\HasPersonalData;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\RegistryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A client/supplier record (spec 0020, "Anagrafiche") that reuses the
 * `users`/`referents` anagraphic stack unchanged via `HasPersonalData` (morph
 * `personable`): the registry owns a personal-data card, which in turn owns
 * its own contacts/addresses.
 *
 * `name` is denormalized display data derived from the card (mirrors
 * `referents.name`, see RegistryProfileWriter). A registry represents
 * primarily a client but, via `is_supplier`, the SAME entity can also be
 * managed as a supplier (no duplication).
 */
#[Fillable([
    'name',
    'source_id',
    'vat_group',
    'is_supplier',
    'is_qualified_supplier',
    'agreement_status',
    'agreement_notes',
    'size_class',
    'supervisor_id',
    'commercial_id',
    'reporter_id',
    'employee_count',
])]
class Registry extends BaseModel
{
    /** @use HasFactory<RegistryFactory> */
    use HasFactory, HasPersonalData, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_supplier' => 'bool',
            'is_qualified_supplier' => 'bool',
            'agreement_status' => AgreementStatusEnum::class,
            'size_class' => SizeClassEnum::class,
            'employee_count' => 'integer',
        ];
    }

    /**
     * The registry's provenance classification, if any (nullOnDelete:
     * removing the source just clears it here, it never cascades a delete).
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /**
     * The internal user supervising the relationship ("Supervisore"): an
     * employee, not an external referent — unlike commercial/reporter.
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function commercial(): BelongsTo
    {
        return $this->belongsTo(Referent::class, 'commercial_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Referent::class, 'reporter_id');
    }

    /**
     * The sectors / competences this registry is classified under
     * ("Settore EA / Competenze", multi).
     */
    public function sectors(): BelongsToMany
    {
        return $this->belongsToMany(Sector::class, 'sector_registry');
    }

    /**
     * The referents (contact people) associated with this registry
     * ("Referenti per azienda", multi).
     */
    public function referents(): BelongsToMany
    {
        return $this->belongsToMany(Referent::class, 'referent_registry');
    }

    /**
     * The internal users managing this registry ("Gestori interni", max
     * App\Support\ManagerPositions::MAX — validation-layer only, see
     * StoreRegistryRequest). Each membership carries
     * a 1-based `position` = its static "G.A. n" slot; always read in that
     * order so the numbering (and the order of importance it encodes) is stable.
     */
    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'registry_user')
            ->withPivot('position')
            ->orderByPivot('position');
    }

    /**
     * The opportunities against this registry (spec 0040, BR-3: restrict-on-
     * delete — RegistryService::delete() guards on this before deleting).
     *
     * @return HasMany<Opportunity, $this>
     */
    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    /**
     * The leads that name this registry as their contact (spec 0041, D-1/
     * BR-2: restrict-on-delete — RegistryService::delete() guards on this
     * before deleting).
     *
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
