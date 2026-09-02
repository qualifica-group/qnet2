<?php

namespace App\Services;

use App\DataObjects\ProductTypologies\CreateProductTypologyData;
use App\DataObjects\ProductTypologies\UpdateProductTypologyData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\ProductTypology;
use Illuminate\Support\Collection;

/**
 * Business logic for the `product-typologies` resource (spec 0099): a lean,
 * full-CRUD lookup (code/name/description) classifying a Product. The
 * controller stays thin; this Service is the single authority, mirroring
 * UnitOfMeasureService.
 */
class ProductTypologyService
{
    public function create(CreateProductTypologyData $data): ProductTypology
    {
        return ProductTypology::create($data->attributes());
    }

    public function update(ProductTypology $productTypology, UpdateProductTypologyData $data): ProductTypology
    {
        $attributes = $data->submittedAttributes();

        // Unconditional save: fire the model's saved event even when no native
        // attribute changed, so the HasCustomFields write pipeline (spec 0021)
        // persists a custom-fields-only edit. A clean save runs no UPDATE query.
        $productTypology->fill($attributes)->save();

        return $productTypology->fresh();
    }

    /**
     * Restrictive delete (spec 0099, D-8): a typology still referenced by a
     * Product cannot be removed — never a cascade, so no product is ever
     * removed as a side effect. Single guard, unlike UnitOfMeasureService's
     * double one: the typology is NOT frozen onto quote lines (D-5), so
     * `products` is its only referenced-by set.
     */
    public function delete(ProductTypology $productTypology): void
    {
        if ($productTypology->products()->exists()) {
            abort(409, 'This product typology is used by a product and cannot be deleted.');
        }

        $productTypology->delete();
    }

    /**
     * Minimal, searchable, paginated typology list for the for-select
     * standard (ADR 0011), mirroring UnitOfMeasureService::forSelect.
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = ProductTypology::query()->select(['id', 'code', 'name']);

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, ProductTypology> $page */
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
     * @param  Collection<int, ProductTypology>  $page
     * @return Collection<int, ProductTypology>
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

        /** @var Collection<int, ProductTypology> $hydrated */
        $hydrated = ProductTypology::query()
            ->select(['id', 'code', 'name'])
            ->whereIn('id', $missingIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }
}
