<?php

namespace Database\Seeders\QualificaCatalog;

use App\CustomFields\CustomFieldWriter;
use App\DataObjects\Products\CreateProductData;
use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductTypology;
use App\Models\Registry;
use App\Services\ProductService;
use Illuminate\Support\Str;

/**
 * The products of a catalogue branch that comes with its own list — "SOA"
 * (SoaCatalogue::PRODUCTS) and "Sicurezza" (SafetyProductCatalogue::PRODUCTS)
 * — seeded by CatalogProducts. Each one gets, besides its price (0 when the
 * list gives none) and the 22% VAT rate:
 *
 *   - the TYPOLOGY of its category (the branch's TYPOLOGIES, by code);
 *   - its SUPPLIER, when a supplier registry of that name exists — usually
 *     brought by the legacy import, AFTER the first seed;
 *   - its "Mesi scadenza", the products module's custom field
 *     (EXPIRATION_MONTHS_FIELD, QualificaTemplateSeeder), unless the list
 *     leaves it blank.
 *
 * Idempotent and non-destructive: the natural key is (name, category), and an
 * already-seeded product keeps every value set by hand. What a re-run fills is
 * only what is still blank: the supplier, the expiry, and a typology still on
 * the default "Ente" (an earlier revision seeded every product there).
 */
final class BranchProducts
{
    public const string EXPIRATION_MONTHS_FIELD = 'expiration_months';

    private const string DEFAULT_TYPOLOGY_CODE = 'institution';

    public function __construct(
        private readonly ProductService $products,
        private readonly CustomFieldWriter $customFields,
    ) {}

    /**
     * @param  array<string, array<string, array{price?: float, supplier: string|null, expiration_months: int|null}>>  $products  category name => product name => its spec
     * @param  array<string, string>  $typologies  category name => typology code
     */
    public function seed(array $products, array $typologies, int $vatRateId): void
    {
        $defaultTypologyId = $this->typologyId(self::DEFAULT_TYPOLOGY_CODE);

        foreach ($products as $categoryName => $categoryProducts) {
            // Step 1: the category and the typology its products are filed under.
            $category = ProductCategory::query()->where('name', $categoryName)->firstOrFail();
            $typologyId = $this->typologyId($typologies[$categoryName]);

            foreach ($categoryProducts as $name => $spec) {
                $supplierId = $spec['supplier'] === null ? null : $this->supplierId($spec['supplier']);

                // Step 2: the product, created or with its blanks filled.
                $product = $this->upsert($category, $name, [$vatRateId, $spec['price'] ?? 0.0], [$typologyId, $defaultTypologyId], $supplierId);

                // Step 3: its "Mesi scadenza", when the list gives one.
                if ($spec['expiration_months'] !== null) {
                    $this->fillExpirationMonths($product, $spec['expiration_months']);
                }
            }
        }
    }

    /**
     * @param  array{0: int, 1: float}  $pricing  the VAT rate id, then the list price
     * @param  array{0: int, 1: int}  $typologyIds  the category's typology, then the default one
     */
    private function upsert(ProductCategory $category, string $name, array $pricing, array $typologyIds, ?int $supplierId): Product
    {
        [$vatRateId, $price] = $pricing;
        [$typologyId, $defaultTypologyId] = $typologyIds;
        $existing = Product::query()->where('name', $name)->where('category_id', $category->id)->first();

        if ($existing === null) {
            return $this->products->create(new CreateProductData(
                name: $name,
                description: null,
                // Cost is filled in later through the CRUD modules.
                cost: 0.0,
                price: $price,
                categoryId: $category->id,
                productType: ProductType::Service,
                vatRateId: $vatRateId,
                supplierId: $supplierId,
                productTypologyId: $typologyId,
            ));
        }

        $existing->fill(array_filter([
            'supplier_id' => $existing->supplier_id ?? $supplierId,
            'product_typology_id' => $existing->product_typology_id === $defaultTypologyId
                ? $typologyId
                : $existing->product_typology_id,
        ], static fn (?int $value): bool => $value !== null))->save();

        return $existing;
    }

    /**
     * Written only when the product has no value yet: one typed by hand wins.
     */
    private function fillExpirationMonths(Product $product, int $months): void
    {
        if (($product->custom_fields[self::EXPIRATION_MONTHS_FIELD] ?? null) !== null) {
            return;
        }

        $this->customFields->write($product, (string) $product->customFieldEntityType(), [
            self::EXPIRATION_MONTHS_FIELD => $months,
        ]);
    }

    /**
     * Seeded by ProductTypologySeeder, which QualificaCatalogSeeder runs before
     * the products: a miss must fail loudly rather than file them under the default.
     */
    private function typologyId(string $code): int
    {
        return ProductTypology::query()->where('code', $code)->firstOrFail()->id;
    }

    /**
     * The supplier registry of that name, compared without case; null when the
     * legacy import has not brought it yet. `name` is not unique on registries,
     * so the lowest id wins rather than an arbitrary row.
     */
    private function supplierId(string $name): ?int
    {
        return Registry::query()
            ->where('is_supplier', true)
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->orderBy('id')
            ->value('id');
    }
}
