<?php

namespace App\Services;

use App\DataObjects\Companies\CreateCompanyData;
use App\DataObjects\Companies\UpdateCompanyData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `companies` resource (spec 0010): create/update/
 * delete, including the single-address invariant delegated to AddressService
 * (createFor on the first write, update thereafter — never a second row).
 * The controller stays thin; this Service is the single authority.
 */
class CompanyService
{
    /**
     * Relations eager-loaded on every returned model, so CompanyResource never
     * N+1s while hydrating the address' geo names.
     *
     * @var array<int, string>
     */
    private const array HYDRATED_RELATIONS = ['addresses.country', 'addresses.state', 'addresses.province', 'addresses.city'];

    public function __construct(private readonly AddressService $addresses) {}

    public function create(User $actor, CreateCompanyData $data): Company
    {
        return DB::transaction(function () use ($data): Company {
            /** @var Company $company */
            $company = Company::create($data->attributes());

            if ($data->hasAddress()) {
                $this->addresses->createFor($company, $data->address);
            }

            return $company->fresh(self::HYDRATED_RELATIONS);
        });
    }

    public function update(User $actor, Company $company, UpdateCompanyData $data): Company
    {
        return DB::transaction(function () use ($company, $data): Company {
            $attributes = $data->submittedAttributes();

            // Unconditional save: fire the model's saved event even when no native
            // attribute changed, so the HasCustomFields write pipeline (spec 0021)
            // persists a custom-fields-only edit. A clean save runs no UPDATE query.
            $company->fill($attributes)->save();

            if ($data->hasAddress()) {
                $this->writeAddress($company, $data);
            }

            return $company->fresh(self::HYDRATED_RELATIONS);
        });
    }

    /**
     * Delete: no restrictive guard remains on Company (spec 0040's
     * opportunity-referenced restriction was removed per user directive
     * 2026-07-17). Its owned address cascades away
     * (HasAddresses::bootHasAddresses).
     */
    public function delete(Company $company): void
    {
        $company->delete();
    }

    /**
     * Minimal, searchable, paginated company list for the for-select standard
     * (spec 0015, ADR 0011), mirroring UserService::forSelect: search by
     * `denomination`/`vat_number`, order by denomination/id, ids[] hydrated
     * without inflating total.
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = Company::query()->select(['id', 'denomination', 'vat_number']);

        if ($query->hasSearch()) {
            $term = '%'.$query->search.'%';
            $base->where(function ($q) use ($term): void {
                $q->where('denomination', 'like', $term)
                    ->orWhere('vat_number', 'like', $term);
            });
        }

        $window = $query->page($base, static fn ($ordered) => $ordered->orderBy('denomination')->orderBy('id'));

        /** @var Collection<int, Company> $page */
        $page = $window->items;

        $items = $this->appendHydratedIds($page, $query);

        return $window->withItems($items);
    }

    /**
     * Append the explicitly-requested `ids[]` (edit-mode hydration) that are not
     * already on the page, deduplicated. They bypass search and the same id/
     * denomination/vat_number projection applies. Total is unaffected.
     *
     * @param  Collection<int, Company>  $page
     * @return Collection<int, Company>
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

        /** @var Collection<int, Company> $hydrated */
        $hydrated = Company::query()
            ->select(['id', 'denomination', 'vat_number'])
            ->whereIn('id', $missingIds)
            ->orderBy('denomination')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }

    /**
     * A company owns AT MOST one address (invariant enforced here, not the
     * schema): update the existing row when there is one, else create it.
     */
    private function writeAddress(Company $company, UpdateCompanyData $data): void
    {
        $existing = $company->addresses()->first();

        if ($existing !== null) {
            $this->addresses->update($existing, $data->address);

            return;
        }

        $this->addresses->createFor($company, $data->address);
    }
}
