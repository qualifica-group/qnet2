<?php

namespace Database\Seeders\QualificaCatalog;

use App\CustomFields\CustomFieldWriter;
use App\DataObjects\Products\CreateProductData;
use App\DataObjects\Products\ProductSupplierData;
use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductTypology;
use App\Models\Registry;
use App\Models\VatRate;
use App\Services\ProductService;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The products of a catalogue branch that comes with its own list — "SOA"
 * (SoaCatalogue::PRODUCTS), "Sicurezza" (SafetyProductCatalogue::PRODUCTS),
 * "Gare", "GDPR", "Finanza Agevolata", "Istruzione", "Accreditamenti" and "ISO" — seeded by CatalogProducts. Each one gets, besides its
 * price (0 when the list gives none), its description (when the list gives
 * one) and the 22% VAT rate, or the one its row names (`vat_rate`, matched
 * on its name and created when missing, ISO list 2026-10-21):
 *
 *   - the list's CODE when its "Codice" column is filled, the generated
 *     "PRD-" one otherwise (user directive 2026-10-20), written on creation
 *     only; a code is unique, so one another product holds fails the seed;
 *   - the TYPOLOGY of its category (the branch's TYPOLOGIES, by code);
 *   - its SUPPLIER, when a supplier registry of that name exists — usually
 *     brought by the legacy import, AFTER the first seed;
 *   - its "Mesi scadenza", the products module's custom field
 *     (EXPIRATION_MONTHS_FIELD, QualificaTemplateSeeder), unless the list
 *     leaves it blank.
 *
 * Idempotent and non-destructive: the natural key is (name, category), and an
 * already-seeded product keeps every value set by hand. What a re-run fills is
 * only what is still blank: the supplier, the description, the expiry, and a typology still on
 * the default "Ente" (an earlier revision seeded every product there).
 */
final class BranchProducts
{
    public const string EXPIRATION_MONTHS_FIELD = 'expiration_months';

    private const string DEFAULT_TYPOLOGY_CODE = 'institution';

    /** The list gives no supplier cost: filled in later through the products module (spec 0320). */
    private const string SUPPLIER_COST = '0.00';

    public function __construct(
        private readonly ProductService $products,
        private readonly CustomFieldWriter $customFields,
    ) {}

    /**
     * @param  array<string, array<string, array{code?: string, description?: string, price?: float, supplier: string|null, expiration_months: int|null, vat_rate?: array{name: string, rate: float}}>>  $products  category name => product name => its spec
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
                $rowVatRateId = isset($spec['vat_rate']) ? $this->vatRateId($spec['vat_rate']) : $vatRateId;

                // Step 2: the product, created or with its blanks filled.
                $product = $this->upsert($category, [$name, $spec['description'] ?? null, $spec['code'] ?? null], [$rowVatRateId, $spec['price'] ?? 0.0], [$typologyId, $defaultTypologyId], $supplierId);

                // Step 3: its "Mesi scadenza", when the list gives one.
                if ($spec['expiration_months'] !== null) {
                    $this->fillExpirationMonths($product, $spec['expiration_months']);
                }
            }
        }
    }

    /**
     * @param  array{0: string, 1: string|null, 2: string|null}  $identity  the product name, its description, then the list's code
     * @param  array{0: int, 1: float}  $pricing  the VAT rate id, then the list price
     * @param  array{0: int, 1: int}  $typologyIds  the category's typology, then the default one
     */
    private function upsert(ProductCategory $category, array $identity, array $pricing, array $typologyIds, ?int $supplierId): Product
    {
        [$name, $description, $code] = $identity;
        [$vatRateId, $price] = $pricing;
        [$typologyId, $defaultTypologyId] = $typologyIds;
        $existing = Product::query()->where('name', $name)->where('category_id', $category->id)->first();

        if ($existing === null) {
            $this->assertCodeIsFree($code, $name);

            return $this->products->create(new CreateProductData(
                name: $name,
                // Null falls back to the generated "PRD-" code.
                code: $code,
                description: $description,
                // Cost is filled in later through the CRUD modules.
                cost: 0.0,
                price: $price,
                categoryId: $category->id,
                productType: ProductType::Service,
                vatRateId: $vatRateId,
                productTypologyId: $typologyId,
                suppliers: $supplierId === null ? null : [new ProductSupplierData($supplierId, bcadd((string) $price, '0', 2), self::SUPPLIER_COST, true)],
            ));
        }

        $existing->fill(array_filter([
            'description' => blank($existing->description) ? $description : $existing->description,
            'product_typology_id' => $existing->product_typology_id === $defaultTypologyId
                ? $typologyId
                : $existing->product_typology_id,
        ], static fn (int|string|null $value): bool => $value !== null))->save();

        // Only a product still without suppliers gets the list's one; never touched afterwards.
        if ($supplierId !== null && ! $existing->productSuppliers()->exists()) {
            $existing->productSuppliers()->create(['supplier_id' => $supplierId, 'price' => bcadd((string) $price, '0', 2), 'cost' => self::SUPPLIER_COST, 'is_default' => true]);
        }

        return $existing;
    }

    /**
     * The unique index would reject a taken code anyway; failing here names the
     * list row, so the operator knows which code to fix.
     */
    private function assertCodeIsFree(?string $code, string $name): void
    {
        if ($code !== null && Product::query()->where('code', $code)->exists()) {
            throw new RuntimeException(sprintf('Product code "%s" of "%s" is already used by another product.', $code, $name));
        }
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
     * Matched on the name, not the rate: several rates share 0% ("Nessuna",
     * "IVA al 0%", the EU ones), and the list names which one it means. The
     * name is not unique either: the oldest row wins.
     *
     * @param  array{name: string, rate: float}  $vatRate
     */
    private function vatRateId(array $vatRate): int
    {
        return VatRate::query()->orderBy('id')->firstOrCreate(['name' => $vatRate['name']], ['rate' => $vatRate['rate']])->id;
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
