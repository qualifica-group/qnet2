<?php

namespace Database\Seeders;

use App\DataObjects\Products\CreateProductData;
use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\ProductService;
use Database\Seeders\Concerns\ResolvesDemoCategories;
use Database\Seeders\DemoCatalog\DemoProductCatalogue;
use Illuminate\Database\Seeder;

/**
 * The demo products (spec 0017): the rows of DemoCatalog\DemoProductCatalogue,
 * each filed under its own leaf category and carrying values for the product
 * attributes DemoProductCategorySeeder assigned to its branch.
 *
 * They are what "prodotti di interesse" — a MANDATORY field of the opportunity
 * form (user directive 2026-07-23) — is picked from, so DemoOpportunitySeeder
 * depends on this step.
 *
 * Created through ProductService::create() (the same path POST /api/products
 * uses), so `attribute_values` goes through the real validation against the
 * category's applicable attributes instead of a raw insert.
 *
 * Idempotent: the natural key is (name, category) — an already-seeded product
 * is left untouched, so a manual edit survives a re-run.
 */
class DemoProductSeeder extends Seeder
{
    use ResolvesDemoCategories;

    public function __construct(private readonly ProductService $products) {}

    public function run(): void
    {
        foreach (DemoProductCatalogue::PRODUCTS as $categoryName => $products) {
            // Created by DemoProductCategorySeeder: a miss means the two
            // catalogues drifted apart, which must fail loudly rather than
            // silently drop a whole category's offer.
            $category = $this->demoCategoryOrFail($categoryName);

            foreach ($products as $product) {
                $this->seedProduct($category, $product);
            }
        }
    }

    /**
     * @param  array{name: string, cost: float, price: float, type: string, attribute_values: array<string, mixed>}  $product
     */
    private function seedProduct(ProductCategory $category, array $product): void
    {
        $exists = Product::query()
            ->where('name', $product['name'])
            ->where('category_id', $category->id)
            ->exists();

        if ($exists) {
            return;
        }

        $this->products->create(new CreateProductData(
            name: $product['name'],
            description: null,
            cost: $product['cost'],
            price: $product['price'],
            categoryId: $category->id,
            productType: ProductType::from($product['type']),
            attributeValues: $product['attribute_values'],
        ));
    }
}
