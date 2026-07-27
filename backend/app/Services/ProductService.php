<?php

namespace App\Services;

use App\DataObjects\Products\CreateProductData;
use App\DataObjects\Products\UpdateProductData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Enums\AttributeContext;
use App\Enums\FormMode;
use App\Models\Product;
use App\Products\ProductAttributeResolver;
use App\RequestManagement\ApplicableAttribute;
use App\RequestManagement\AttributeValueNormalizer;
use App\RequestManagement\AttributeValueValidator;
use App\Services\ProductCategories\AttributeLayoutService;
use App\Services\ProductCategories\CategoryHierarchy;
use Illuminate\Support\Collection;

/**
 * Business logic for the `products` resource (spec 0017; spec 0061 for its
 * own `attribute_values`): create/update (generic fields, plus the
 * PRODUCT-context attribute values validated/normalized against
 * ProductAttributeResolver — reusing the SAME
 * AttributeValueValidator/AttributeValueNormalizer pipeline as the Opportunity
 * path, App\RequestManagement) and delete. The controller stays thin; this
 * Service is the single authority.
 */
class ProductService
{
    /**
     * Relations eager-loaded on every returned model, so ProductResource
     * never N+1s while hydrating the category summary.
     *
     * @var array<int, string>
     */
    private const array HYDRATED_RELATIONS = ['category', 'vatRate', 'supplier'];

    public function __construct(
        private readonly CategoryHierarchy $hierarchy,
        private readonly ProductAttributeResolver $attributeResolver,
        private readonly AttributeValueValidator $attributeValueValidator,
        private readonly AttributeValueNormalizer $attributeValueNormalizer,
        private readonly AttributeLayoutService $attributeLayoutService,
    ) {}

    /**
     * The product's category's EFFECTIVE business function (spec 0023),
     * read-only: `id`/`name` only — the category's own `inherited`/
     * `source_category` detail is a product-categories-only concern. Null
     * when the product has no category, or the category (and its ancestry)
     * has none.
     *
     * @return array{id: int, name: string}|null
     */
    public function effectiveBusinessFunction(Product $product): ?array
    {
        if ($product->category === null) {
            return null;
        }

        $effective = $this->hierarchy->effectiveBusinessFunction($product->category);

        if ($effective === null) {
            return null;
        }

        return ['id' => $effective['id'], 'name' => $effective['name']];
    }

    public function create(CreateProductData $data): Product
    {
        $product = new Product([
            'name' => $data->name,
            'description' => $data->description,
            'cost' => $data->cost,
            'price' => $data->price,
            'category_id' => $data->categoryId,
            'product_type' => $data->productType,
            'vat_rate_id' => $data->vatRateId,
            'supplier_id' => $data->supplierId,
        ]);

        if ($data->hasAttributeValues()) {
            $this->applyAttributeValues($product, $data->attributeValues);
        }

        $product->save();

        return $product->fresh(self::HYDRATED_RELATIONS);
    }

    public function update(Product $product, UpdateProductData $data): Product
    {
        $attributes = $data->submittedAttributes();

        // category_id (if submitted) is filled BEFORE resolving applicable
        // attributes below, so a same-request category change validates
        // attribute_values against the NEW category, never the stale one.
        $product->fill($attributes);

        if ($data->hasAttributeValues()) {
            $this->applyAttributeValues($product, $data->attributeValues);
        }

        // Unconditional save: fire the model's saved event even when no native
        // attribute changed, so the HasCustomFields write pipeline (spec 0021)
        // persists a custom-fields-only edit. A clean save runs no UPDATE query.
        $product->save();

        return $product->fresh(self::HYDRATED_RELATIONS);
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }

    /**
     * The product's PRODUCT-context applicable attribute set (spec 0061),
     * for the create/edit form's dynamic fields.
     *
     * @return array<int, array<string, mixed>>
     */
    public function applicableAttributes(Product $product): array
    {
        return $this->attributeResolver->resolve($product)
            ->map(fn (ApplicableAttribute $attribute): array => $attribute->toArray())
            ->values()
            ->all();
    }

    /**
     * The product's category's configured layout for the detail shape (spec
     * 0062 revised): FormMode::View with cross-mode fallback, so a single
     * saved layout (typically authored under `create`) also drives the
     * read-only detail. Null when the product has no category or the category
     * has no PRODUCT-context layout in any mode (flat fallback, AC-007).
     *
     * @return array{sections: array<int, array<string, mixed>>}|null
     */
    public function attributeLayout(Product $product): ?array
    {
        if ($product->category === null) {
            return null;
        }

        return $this->attributeLayoutService->resolveWithFallback($product->category, AttributeContext::Product, FormMode::View);
    }

    /**
     * Validates $submitted against the product's PRODUCT-context applicable
     * set (unknown code / required / per-type rules — the SAME
     * AttributeValueValidator the Opportunity path uses), normalizes it, and
     * merges it into the persisted map (sparse: an unset code keeps its
     * current value). $product is NOT saved here — the caller (create/update)
     * saves once, alongside its other native-field changes.
     *
     * @param  array<string, mixed>  $submitted
     */
    private function applyAttributeValues(Product $product, array $submitted): void
    {
        $applicable = $this->attributeResolver->resolve($product);
        $validated = $this->attributeValueValidator->validate($applicable, $submitted);
        $normalized = $this->attributeValueNormalizer->normalize($applicable, $validated);

        $current = $product->attribute_values ?? [];
        $merged = array_merge($current, $normalized);

        // `attribute_values` is NOT in Product::$fillable (mass-assignment
        // guard, same discipline as Opportunity.attribute_values): forceFill
        // is the deliberate, single write path.
        $product->forceFill(['attribute_values' => $merged]);
    }

    /**
     * Minimal, searchable, paginated product list for the for-select standard
     * (ADR 0011), mirroring VatRateService::forSelect. `category_ids` (user
     * directive 2026-07-22) scopes the page to the products of those exact
     * categories — how the "prodotti di interesse" picker stays aligned with
     * the opportunity's product lines; absent, the whole catalogue is
     * searchable (the picker's explicit "unlock").
     *
     * The category is projected as `meta.category` so the operator can tell
     * two same-named products apart, and so the unlocked picker shows what a
     * cross-category pick would add to the opportunity's product lines.
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = Product::query()->select(['id', 'name', 'category_id']);

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        if ($query->hasCategoryIds()) {
            $base->whereIn('category_id', $query->categoryIds);
        }

        $total = (clone $base)->count();

        /** @var Collection<int, Product> $page */
        $page = $base->orderBy('name')
            ->orderBy('id')
            ->offset($query->offset)
            ->limit($query->limit)
            ->get();

        $items = $this->appendHydratedIds($page, $query);
        $items->load('category:id,name');

        return new ForSelectResult(
            items: $items,
            total: $total,
            offset: $query->offset,
            limit: $query->limit,
        );
    }

    /**
     * Append the explicitly-requested `ids[]` (edit-mode hydration) that are
     * not already on the page, deduplicated. They bypass BOTH the search and
     * the category scope — a product already selected must keep its label
     * even once it falls outside the current filter. Total is unaffected.
     *
     * @param  Collection<int, Product>  $page
     * @return Collection<int, Product>
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

        /** @var Collection<int, Product> $hydrated */
        $hydrated = Product::query()
            ->select(['id', 'name', 'category_id'])
            ->whereIn('id', $missingIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }
}
