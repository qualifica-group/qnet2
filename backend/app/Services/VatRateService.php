<?php

namespace App\Services;

use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\DataObjects\VatRates\CreateVatRateData;
use App\DataObjects\VatRates\UpdateVatRateData;
use App\Models\VatRate;
use Illuminate\Support\Collection;

/**
 * Business logic for the `vat-rates` resource: a plain lookup entity (id,
 * name, rate). The controller stays thin; this Service is the single
 * authority, mirroring SourceService.
 */
class VatRateService
{
    public function create(CreateVatRateData $data): VatRate
    {
        return VatRate::create($data->attributes());
    }

    public function update(VatRate $vatRate, UpdateVatRateData $data): VatRate
    {
        $attributes = $data->submittedAttributes();

        // Unconditional save: fire the model's saved event even when no native
        // attribute changed, so the HasCustomFields write pipeline (spec 0021)
        // persists a custom-fields-only edit. A clean save runs no UPDATE query.
        $vatRate->fill($attributes)->save();

        return $vatRate->fresh();
    }

    /**
     * Unguarded delete: products.vat_rate_id is nullOnDelete, so no other
     * resource restricts removal of a VAT rate.
     */
    public function delete(VatRate $vatRate): void
    {
        $vatRate->delete();
    }

    /**
     * Minimal, searchable, paginated VAT rate list for the for-select
     * standard (ADR 0011), mirroring SourceService::forSelect.
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = VatRate::query()->select(['id', 'name', 'rate']);

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, VatRate> $page */
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
     * @param  Collection<int, VatRate>  $page
     * @return Collection<int, VatRate>
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

        /** @var Collection<int, VatRate> $hydrated */
        $hydrated = VatRate::query()
            ->select(['id', 'name', 'rate'])
            ->whereIn('id', $missingIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }
}
