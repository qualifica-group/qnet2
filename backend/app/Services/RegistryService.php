<?php

namespace App\Services;

use App\DataObjects\Registries\CreateRegistryData;
use App\DataObjects\Registries\UpdateRegistryData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\DataObjects\Users\ProfileData;
use App\Models\Registry;
use App\Models\User;
use App\Services\Notifications\AssignmentNotifier;
use App\Support\ManagerPositions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Business logic for the `registries` resource (spec 0020): create/update/
 * delete, mirroring ReferentService's nested personal-data write discipline
 * plus the business-specific relations (source, sectors, referents,
 * internal managers, supervisor/commercial/reporter) and the
 * is_qualified_supplier normalization.
 */
class RegistryService
{
    /**
     * Relations eager-loaded on the registry returned by create()/update()/
     * loadProfileTree() so the RegistryResource can emit the full nested tree
     * without a second request/N+1.
     *
     * @var array<int, string>
     */
    private const array WRITE_RESULT_RELATIONS = [
        'source',
        'supervisor',
        'commercial',
        'reporter',
        'sectors',
        'referents',
        'managers',
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
        // Cards of the responsible people + the referent/manager lists so the
        // detail can render their PRIMARY contacts beside the name
        // (RegistryResource filters is_primary). Managers keep their pivot
        // `position` order (the belongsToMany already orderByPivot).
        'supervisor.personalData.contacts',
        'commercial.personalData.contacts',
        'reporter.personalData.contacts',
        'referents.personalData.contacts',
        'managers.personalData.contacts',
    ];

    public function __construct(
        private readonly RegistryProfileWriter $profileWriter,
        private readonly AssignmentNotifier $assignmentNotifier,
    ) {}

    /**
     * Eager-load the nested read tree so a plain GET /registries/{registry}
     * returns the SAME shape create()/update() do. Single source of truth
     * for the relation set (WRITE_RESULT_RELATIONS).
     */
    public function loadProfileTree(Registry $registry): Registry
    {
        return $registry->load(self::WRITE_RESULT_RELATIONS);
    }

    /**
     * Create a new registry. `personal_data` is REQUIRED (it is the only
     * source of the derived `registries.name` — mirrors ReferentService::create).
     */
    public function create(User $actor, CreateRegistryData $data, ?ProfileData $profile): Registry
    {
        if ($profile === null) {
            // StoreRegistryRequest guarantees a profile (it is the only source
            // of the derived name); a null here is a programming error/bypass.
            throw new InvalidArgumentException('A personal-data profile is required to create a registry (its name is derived from the card).');
        }

        $registry = DB::transaction(function () use ($actor, $data, $profile): Registry {
            $attributes = $data->attributes();
            // `registries.name` is NOT NULL but the authoritative value is
            // derived by RegistryProfileWriter from the card (single
            // derivation point). Seed only a non-null placeholder here to
            // satisfy the constraint at INSERT.
            $attributes['name'] = '';

            $registry = Registry::create($attributes);

            $this->profileWriter->write($registry, $profile);
            $attachedManagers = $this->syncPivots($registry, $data);
            $this->normalizeQualifiedSupplier($registry);

            // spec 0081: notified after the name is derived from the card, so
            // the message carries the real label and not the INSERT placeholder.
            $this->assignmentNotifier->notify(
                $registry,
                $actor,
                $registry->supervisor_id,
                $attachedManagers,
            );

            return $registry;
        });

        return $registry->load(self::WRITE_RESULT_RELATIONS);
    }

    /**
     * Update an existing registry. Only keys present in $data are touched, so
     * partial (PATCH) updates leave untouched fields as-is. When $profile is
     * provided, the card is upserted/synced in the same transaction; a null
     * $profile leaves the card untouched. Each pivot array is synced ONLY
     * when submitted (an omitted key leaves the current associations
     * untouched, an explicit empty array detaches all — mirrors
     * SectorService::update).
     */
    public function update(User $actor, Registry $registry, UpdateRegistryData $data, ?ProfileData $profile): Registry
    {
        $registry = DB::transaction(function () use ($actor, $registry, $data, $profile): Registry {
            $attributes = $data->submittedAttributes();

            // fill+save unconditionally (never guarded behind a non-empty
            // attribute set): a custom-fields-only edit submits no native
            // attribute, yet the HasCustomFields write pipeline (spec 0021)
            // hooks the model's `saved` event — skipping save() would silently
            // drop those values. A clean save fires no UPDATE query, bumps no
            // timestamp and logs no activity, so this is a no-op for the
            // native path when nothing changed.
            $registry->fill($attributes)->save();

            // Captured right after THIS save: the writes below save() again
            // and would reset wasChanged()'s diff (same discipline as the
            // reporter_id check in OpportunityService::update).
            $newSupervisorId = $registry->wasChanged('supervisor_id') ? $registry->supervisor_id : null;

            $this->profileWriter->write($registry, $profile);
            $attachedManagers = $this->syncPivots($registry, $data);
            $this->normalizeQualifiedSupplier($registry);

            $this->assignmentNotifier->notify(
                $registry,
                $actor,
                $newSupervisorId,
                $attachedManagers,
            );

            return $registry;
        });

        return $registry->load(self::WRITE_RESULT_RELATIONS);
    }

    /**
     * Delete the registry. Its personal-data card (and the card's own
     * contacts/addresses) cascade away via HasPersonalData; the 3 pivot
     * rows cascade away via their own cascadeOnDelete foreign keys.
     *
     * Restrictive (spec 0040, BR-3): a registry referenced by at least one
     * opportunity cannot be removed, mirroring the other 9 guards this rule
     * introduces. Restrictive (spec 0041, D-1/BR-2): a registry referenced by
     * at least one lead cannot be removed either — this guard replaces the
     * one that used to live on ReferentService (the Lead's contact is now an
     * Anagrafica, not a Referent).
     */
    public function delete(Registry $registry): void
    {
        if ($registry->leads()->exists()) {
            abort(409, 'This registry has leads and cannot be deleted.');
        }

        if ($registry->opportunities()->exists()) {
            abort(409, 'This registry has opportunities and cannot be deleted.');
        }

        $registry->delete();
    }

    /**
     * Minimal, searchable, paginated registry list for the for-select
     * standard (spec 0023, ADR 0011), mirroring SourceService::forSelect.
     * commercial/reporter/supervisor are eager-loaded so
     * RegistryForSelectResource's `meta` (spec 0040 BR-4) never N+1s.
     *
     * $onlySuppliers (product supplier picker) constrains the base query to
     * `is_supplier = true` when true; false/omitted is BYTE-IDENTICAL to the
     * pre-existing behavior (e.g. the opportunity registry select), so this
     * extra param never changes the default result set.
     */
    public function forSelect(ForSelectQuery $query, bool $onlySuppliers = false): ForSelectResult
    {
        $base = $this->forSelectBaseQuery();

        if ($onlySuppliers) {
            $base->where('is_supplier', true);
        }

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, Registry> $page */
        $page = $base->orderBy('name')
            ->orderBy('id')
            ->offset($query->offset)
            ->limit($query->limit)
            ->get();

        $items = $this->appendHydratedForSelectIds($page, $query);

        return new ForSelectResult(
            items: $items,
            total: $total,
            offset: $query->offset,
            limit: $query->limit,
        );
    }

    /**
     * Base for-select query: registries with commercial/reporter/supervisor
     * eager-loaded, so RegistryForSelectResource's `meta` never N+1s.
     *
     * @return Builder<Registry>
     */
    private function forSelectBaseQuery(): Builder
    {
        return Registry::query()
            ->select(['id', 'name', 'commercial_id', 'reporter_id', 'supervisor_id'])
            ->with(['commercial:id,name', 'reporter:id,name', 'supervisor:id,name', 'managers:id,name']);
    }

    /**
     * Append the explicitly-requested `ids[]` (edit-mode hydration) that are
     * not already on the page, deduplicated. They bypass search and the same
     * id/name/meta projection applies. Total is unaffected.
     *
     * @param  Collection<int, Registry>  $page
     * @return Collection<int, Registry>
     */
    private function appendHydratedForSelectIds(Collection $page, ForSelectQuery $query): Collection
    {
        if (! $query->hasIds()) {
            return $page;
        }

        $presentIds = $page->pluck('id')->all();
        $missingIds = array_values(array_diff($query->ids, $presentIds));

        if ($missingIds === []) {
            return $page;
        }

        /** @var Collection<int, Registry> $hydrated */
        $hydrated = $this->forSelectBaseQuery()
            ->whereIn('id', $missingIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }

    /**
     * Sync the 3 to-many relations from the DTO's submitted id arrays, each
     * independently guarded by its own hasXxxIds() (mirrors
     * SectorService::create/update's `if ($data->hasTagIds())` guard): an
     * omitted key leaves that relation untouched, a submitted array
     * (including empty) is an authoritative sync.
     *
     * @return array<int, int> the managers this sync ATTACHED, userId => position
     *                         (spec 0081): the notification set, empty when no
     *                         manager slot was submitted or nobody is new
     */
    private function syncPivots(Registry $registry, CreateRegistryData|UpdateRegistryData $data): array
    {
        if ($data->hasSectorIds()) {
            $registry->sectors()->sync($data->sectorIds);
        }

        if ($data->hasReferentIds()) {
            $registry->referents()->sync($data->referentIds);
        }

        if (! $data->hasManagerSlots()) {
            return [];
        }

        $syncMap = ManagerPositions::syncMap($data->managerSlots);

        return ManagerPositions::attachedPositions($syncMap, $registry->managers()->sync($syncMap));
    }

    /**
     * Business rule (spec 0020): `is_qualified_supplier` is meaningless when
     * the registry is not a supplier — force it back to false whenever the
     * registry's EFFECTIVE (post-write) `is_supplier` is false, regardless of
     * whether this request touched either flag.
     */
    private function normalizeQualifiedSupplier(Registry $registry): void
    {
        if (! $registry->is_supplier && $registry->is_qualified_supplier) {
            $registry->update(['is_qualified_supplier' => false]);
        }
    }
}
