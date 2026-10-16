<?php

namespace Database\Seeders;

use App\DataObjects\Products\CreateProductData;
use App\Enums\ProductType;
use App\Enums\ProductUsage;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\VatRate;
use App\Services\ProductService;
use Database\Seeders\QualificaCatalog\CostCatalogue;
use Database\Seeders\QualificaCatalog\SelfFundedCourseCatalogue;
use Illuminate\Database\Seeder;

/**
 * The client's cost-only products (QualificaCatalog\CostCatalogue): the legacy
 * cost items as new, active qnet products under the "Costi" branch, usable only
 * on an Offerta's Costi tab. A step of QualificaProductionDataSeeder.
 *
 * Created through ProductService::create() (the same path POST /api/products
 * uses). Idempotent and non-destructive: the root resolves by name at top
 * level, a leaf by name under it, a product by (name, category); an existing
 * row is left untouched, so a manual edit survives a re-run.
 */
class QualificaCostProductSeeder extends Seeder
{
    public function __construct(private readonly ProductService $products) {}

    public function run(): void
    {
        // Step 1: the branch root, a container with no business function.
        $root = ProductCategory::firstOrCreate(
            ['name' => CostCatalogue::ROOT, 'parent_id' => null],
            ['is_selectable' => false],
        );
        $vatRateId = $this->plusVatRate()->id;

        foreach (CostCatalogue::PRODUCTS as $categoryName => $items) {
            // Step 2: the leaf category, the classification target.
            $category = ProductCategory::firstOrCreate(['name' => $categoryName, 'parent_id' => $root->id]);

            // Step 3: its cost items.
            foreach ($items as $item) {
                $this->seedCostProduct($category, $item, $item['plus_vat'] ? $vatRateId : null);
            }
        }
    }

    /**
     * Adopted by percentage, as QualificaCatalog\CatalogProducts does: the
     * production seed creates no VAT catalogue of its own.
     */
    private function plusVatRate(): VatRate
    {
        return VatRate::query()->firstOrCreate(
            ['rate' => SelfFundedCourseCatalogue::VAT_RATE],
            ['name' => SelfFundedCourseCatalogue::VAT_RATE_NAME],
        );
    }

    /**
     * @param  array{name: string, cost: float, plus_vat: bool, description: string|null}  $item
     */
    private function seedCostProduct(ProductCategory $category, array $item, ?int $vatRateId): void
    {
        $exists = Product::query()
            ->where('name', $item['name'])
            ->where('category_id', $category->id)
            ->exists();

        if ($exists) {
            return;
        }

        $this->products->create(new CreateProductData(
            name: $item['name'],
            description: $item['description'],
            cost: $item['cost'],
            price: $item['cost'],
            categoryId: $category->id,
            productType: ProductType::Service,
            vatRateId: $vatRateId,
            usages: [ProductUsage::Cost],
        ));
    }
}
