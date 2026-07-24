<?php

namespace App\Services;

use App\DataObjects\CompanySites\CreateCompanySiteData;
use App\DataObjects\CompanySites\UpdateCompanySiteData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\DataObjects\Users\ProfileData;
use App\Models\CompanySite;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `company-sites` resource (spec 0020): create/
 * update/delete/setDefault, delegating the nested personal-data card (contacts
 * + address) to CompanySiteProfileWriter and the banks diff to BankService —
 * the controller stays thin, this Service is the single authority.
 *
 * The "preferred bank" is a per-row `is_primary` flag on the banks themselves
 * (single-primary invariant owned by BankService), not a site-level FK.
 */
class CompanySiteService
{
    /**
     * Relations eager-loaded on every returned model, so CompanySiteResource
     * never N+1s while hydrating the card's contacts, the address' geo names,
     * the banks and the company reference. Mirrors
     * RegistryService::WRITE_RESULT_RELATIONS' card tree, plus the geo tree the
     * PersonalDataResource/AddressResource read.
     *
     * @var array<int, string>
     */
    private const array HYDRATED_RELATIONS = [
        'personalData.contacts',
        'personalData.addresses',
        'personalData.addresses.country', 'personalData.addresses.state',
        'personalData.addresses.province', 'personalData.addresses.city',
        'banks',
        'company',
    ];

    public function __construct(
        private readonly CompanySiteProfileWriter $profileWriter,
        private readonly BankService $banks,
        private readonly LogoService $logos,
    ) {}

    /**
     * Create a new site. `name` is the site's OWN required column (from the
     * scalar payload, NOT derived from the card, unlike Registry); the optional
     * `personal_data` profile carries the card + its contacts/address.
     */
    public function create(User $actor, CreateCompanySiteData $data, ?ProfileData $profile): CompanySite
    {
        return DB::transaction(function () use ($data, $profile): CompanySite {
            /** @var CompanySite $companySite */
            $companySite = CompanySite::create($data->attributes());

            $this->profileWriter->write($companySite, $profile);

            $this->banks->sync($companySite, $data->banks);

            if ($data->hasLogo()) {
                $this->logos->set($companySite, $data->logo);
            }

            return $this->loadTree($companySite);
        });
    }

    public function update(User $actor, CompanySite $companySite, UpdateCompanySiteData $data, ?ProfileData $profile): CompanySite
    {
        return DB::transaction(function () use ($companySite, $data, $profile): CompanySite {
            $attributes = $data->submittedAttributes();

            // Unconditional save: fire the model's saved event even when no native
            // attribute changed, so the HasCustomFields write pipeline (spec 0021)
            // persists a custom-fields-only edit. A clean save runs no UPDATE query.
            $companySite->fill($attributes)->save();

            $this->profileWriter->write($companySite, $profile);

            if ($data->banksSubmitted) {
                $this->banks->sync($companySite, $data->banks);
            }

            return $this->loadTree($companySite);
        });
    }

    /**
     * Delete: no restrictive guard remains on CompanySite (spec 0040's
     * opportunity-referenced restriction was removed per user directive
     * 2026-07-17). The owned personal-data card (and its own contacts/
     * address, via HasPersonalData), banks (FK cascade) and logo
     * (HasAttachments) all cascade away with the site.
     */
    public function delete(CompanySite $companySite): void
    {
        $companySite->delete();
    }

    /**
     * Exclusively promote $companySite to the default site: in one
     * transaction, demote every other site and set this one — an invariant
     * that cannot live in the schema (a boolean column has no "at most one
     * true" constraint), so it belongs here.
     */
    public function setDefault(CompanySite $companySite): void
    {
        DB::transaction(function () use ($companySite): void {
            CompanySite::query()
                ->where('is_default', true)
                ->whereKeyNot($companySite->id)
                ->update(['is_default' => false]);

            $companySite->update(['is_default' => true]);
        });
    }

    /**
     * Eager-load every relation CompanySiteResource reads, so the controller
     * never triggers a lazy load while building the response.
     */
    public function loadTree(CompanySite $companySite): CompanySite
    {
        return $companySite->fresh(self::HYDRATED_RELATIONS);
    }

    /**
     * Minimal, searchable, paginated company-site list for the for-select
     * standard (spec 0040, ADR 0011), mirroring CompanyService::forSelect.
     * $companyId (spec 0040 BR-4), when given, restricts the list to sites of
     * that company — mirrors BusinessFunctionForSelectRequest's
     * exclude_descendants_of: never widening the generic ForSelectQuery
     * contract.
     */
    public function forSelect(ForSelectQuery $query, ?int $companyId = null): ForSelectResult
    {
        $base = $this->forSelectBaseQuery();

        if ($companyId !== null) {
            $base->where('company_id', $companyId);
        }

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, CompanySite> $page */
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
     * Base for-select query: sites with their owning company eager-loaded, so
     * CompanySiteForSelectResource never N+1s while reading the subtitle.
     *
     * @return Builder<CompanySite>
     */
    private function forSelectBaseQuery(): Builder
    {
        return CompanySite::query()->select(['id', 'name', 'company_id'])->with('company:id,denomination');
    }

    /**
     * Append the explicitly-requested `ids[]` (edit-mode hydration) that are
     * not already on the page, deduplicated. They bypass search AND the
     * company_id scope, mirroring every other for-select's ids[] contract.
     * Total is unaffected.
     *
     * @param  Collection<int, CompanySite>  $page
     * @return Collection<int, CompanySite>
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

        /** @var Collection<int, CompanySite> $hydrated */
        $hydrated = $this->forSelectBaseQuery()
            ->whereIn('id', $missingIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }
}
