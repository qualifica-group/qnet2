<?php

namespace App\Services;

use App\DataObjects\Referents\CreateReferentData;
use App\DataObjects\Referents\UpdateReferentData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\DataObjects\Users\ProfileData;
use App\Models\Referent;
use App\Models\User;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Business logic for the `referents` resource (spec 0016): create/update/
 * delete, mirroring UserService's nested personal-data write discipline but
 * without roles/employment — a referent is only the anagraphic + the
 * classification/scope/notes.
 */
class ReferentService
{
    /**
     * Relations eager-loaded on the referent returned by create()/update()/
     * loadProfileTree() so the ReferentResource can emit the nested
     * referent_type + personal_data tree without a second request/N+1.
     *
     * @var array<int, string>
     */
    private const array WRITE_RESULT_RELATIONS = [
        'referentType',
        'personalData.contacts',
        'personalData.addresses',
        // The comuni of birth and residence, so PersonalDataResource can emit
        // their names.
        'personalData.birthCity',
        'personalData.residenceCity',
        // Geo names for the full address display (AddressResource emits them
        // via whenLoaded); the raw *_id columns already ship without these.
        'personalData.addresses.city',
        'personalData.addresses.province',
        'personalData.addresses.state',
        'personalData.addresses.country',
    ];

    public function __construct(private readonly ReferentProfileWriter $profileWriter) {}

    /**
     * Eager-load the nested read tree so a plain GET /referents/{referent}
     * returns the SAME shape create()/update() do. Single source of truth
     * for the relation set (WRITE_RESULT_RELATIONS).
     */
    public function loadProfileTree(Referent $referent): Referent
    {
        return $referent->load(self::WRITE_RESULT_RELATIONS);
    }

    /**
     * Create a new referent. `personal_data` is REQUIRED (it is the only
     * source of the derived `referents.name` — mirrors UserService::create).
     */
    public function create(User $actor, CreateReferentData $data, ?ProfileData $profile): Referent
    {
        if ($profile === null) {
            // StoreReferentRequest guarantees a profile (it is the only source
            // of the derived name); a null here is a programming error/bypass.
            throw new InvalidArgumentException('A personal-data profile is required to create a referent (its name is derived from the card).');
        }

        $referent = DB::transaction(function () use ($data, $profile): Referent {
            $attributes = $data->attributes();
            // `referents.name` is NOT NULL but the authoritative value is
            // derived by ReferentProfileWriter from the card (single
            // derivation point). Seed only a non-null placeholder here to
            // satisfy the constraint at INSERT.
            $attributes['name'] = '';

            $referent = Referent::create($attributes);

            $this->profileWriter->write($referent, $profile);

            return $referent;
        });

        return $referent->load(self::WRITE_RESULT_RELATIONS);
    }

    /**
     * Update an existing referent. Only keys present in $data are touched, so
     * partial (PATCH) updates leave untouched fields as-is. When $profile is
     * provided, the card is upserted/synced in the same transaction; a null
     * $profile leaves the card untouched.
     */
    public function update(User $actor, Referent $referent, UpdateReferentData $data, ?ProfileData $profile): Referent
    {
        $referent = DB::transaction(function () use ($referent, $data, $profile): Referent {
            $attributes = $data->submittedAttributes();

            // Unconditional save: fire the model's saved event even when no native
            // attribute changed, so the HasCustomFields write pipeline (spec 0021)
            // persists a custom-fields-only edit. A clean save runs no UPDATE query.
            $referent->fill($attributes)->save();

            $this->profileWriter->write($referent, $profile);

            return $referent;
        });

        return $referent->load(self::WRITE_RESULT_RELATIONS);
    }

    /**
     * Restrictive delete (spec 0040, BR-3): a referent referenced as an
     * opportunity's own referent, commercial OR reporter cannot be removed.
     * Otherwise its personal-data card (and the card's own contacts/
     * addresses) cascade away via HasPersonalData. The former lead guard
     * (spec 0024 BR-2/D-4) moved to RegistryService::delete() — spec 0041
     * D-1: a Lead's contact is now an Anagrafica, not a Referent.
     */
    public function delete(Referent $referent): void
    {
        if ($referent->opportunities()->exists() || $referent->opportunitiesAsCommercial()->exists() || $referent->opportunitiesAsReporter()->exists()) {
            abort(409, 'This referent has opportunities and cannot be deleted.');
        }

        $referent->delete();
    }

    /**
     * Minimal, searchable, paginated referent list for the for-select
     * standard (ADR 0011, spec 0020), mirroring SourceService::forSelect.
     * $registryId (spec 0040 BR-4), when given, restricts the list to
     * referents linked to that registry via the `referent_registry` pivot.
     */
    public function forSelect(ForSelectQuery $query, ?int $registryId = null): ForSelectResult
    {
        $base = Referent::query()->select(['id', 'name'])->with($this->forSelectContactEagerLoad());

        if ($registryId !== null) {
            $base->whereHas('registries', function ($registryQuery) use ($registryId): void {
                $registryQuery->whereKey($registryId);
            });
        }

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, Referent> $page */
        $page = $base->orderBy('name')
            ->orderBy('id')
            ->offset($query->offset)
            ->limit($query->limit)
            ->get();

        $items = $this->appendHydratedIds($page, $query);

        return new ForSelectResult(
            items: $items,
            total: $total,
            offset: $query->offset,
            limit: $query->limit,
        );
    }

    /**
     * Append the explicitly-requested `ids[]` (edit-mode hydration) that are
     * not already on the page, deduplicated. They bypass search and the same
     * id/name projection applies. Total is unaffected.
     *
     * @param  Collection<int, Referent>  $page
     * @return Collection<int, Referent>
     */
    private function appendHydratedIds(Collection $page, ForSelectQuery $query): Collection
    {
        if (! $query->hasIds()) {
            return $page;
        }

        $presentIds = $page->pluck('id')->all();
        $missingIds = array_values(array_diff($query->ids, $presentIds));

        if ($missingIds === []) {
            return $page;
        }

        /** @var Collection<int, Referent> $hydrated */
        $hydrated = Referent::query()
            ->select(['id', 'name'])
            ->with($this->forSelectContactEagerLoad())
            ->whereIn('id', $missingIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }

    /**
     * Eager-load spec for the for-select `meta.contacts` (spec 0040 A-4): only
     * the PRIMARY contacts of the referent's personal-data card, so
     * ReferentForSelectResource's `meta` never N+1s and the recap-by-ids path
     * (appendHydratedIds) carries the same meta. Empty when the referent has no
     * card / no primary contacts.
     *
     * @return array<string, Closure>
     */
    private function forSelectContactEagerLoad(): array
    {
        return ['personalData.contacts' => static fn ($contactQuery) => $contactQuery->where('is_primary', true)];
    }
}
