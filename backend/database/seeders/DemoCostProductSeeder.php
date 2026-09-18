<?php

namespace Database\Seeders;

use App\DataObjects\Products\CreateProductData;
use App\Enums\ProductType;
use App\Enums\ProductUsage;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use App\Services\ProductService;
use Database\Seeders\DemoCatalog\DemoCostProductCatalogue;
use Illuminate\Database\Seeder;

/**
 * The demo cost-only products (spec 0142): the rows of
 * DemoCatalog\DemoCostProductCatalogue — cars, train tickets, hotels... —
 * filed under their own "Spese e Trasferte" branch and usable only on an
 * Offerta's Costi tab. DemoQuoteSeeder draws its COST lines from them.
 *
 * Created through ProductService::create() (the same path POST /api/products
 * uses). Idempotent: categories resolve by their position in this branch
 * (root name + `parent_id IS NULL`, leaf name + root id, same discipline as
 * Concerns\ResolvesDemoCategories) and a product by (name, category), so a
 * re-run duplicates nothing and a manual edit survives.
 */
class DemoCostProductSeeder extends Seeder
{
    public function __construct(private readonly ProductService $products) {}

    public function run(): void
    {
        // Step 1: the branch root, with no business function (see the catalogue).
        $root = ProductCategory::firstOrCreate(['name' => DemoCostProductCatalogue::ROOT, 'parent_id' => null]);

        foreach (DemoCostProductCatalogue::PRODUCTS as $categoryName => $items) {
            // Step 2: the leaf category under that root.
            $category = ProductCategory::firstOrCreate(['name' => $categoryName, 'parent_id' => $root->id]);

            // Step 3: its cost items.
            foreach ($items as $item) {
                $this->seedCostProduct($category, $item);
            }
        }
    }

    /**
     * @param  array{name: string, cost: float, unit: string}  $item
     */
    private function seedCostProduct(ProductCategory $category, array $item): void
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
            description: null,
            cost: $item['cost'],
            price: $item['cost'],
            categoryId: $category->id,
            productType: ProductType::Service,
            // Null (unknown code) falls back to the default unit in ProductService.
            unitOfMeasureId: UnitOfMeasure::query()->where('code', $item['unit'])->value('id'),
            usages: [ProductUsage::Cost],
        ));
    }
}
