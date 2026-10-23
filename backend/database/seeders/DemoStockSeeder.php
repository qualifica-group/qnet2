<?php

namespace Database\Seeders;

use App\DataObjects\Products\CreateProductData;
use App\DataObjects\Stock\StockMovementData;
use App\Enums\ProductType;
use App\Enums\ProductUsage;
use App\Enums\StockMovementType as Type;
use App\Models\OperationalSite;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\ProductService;
use App\Services\Stock\StockMovementService;
use Illuminate\Database\Seeder;

/**
 * Demo equipment stock (spec 0307, D-20): three physical goods for internal
 * use, each with a short history of movements (every type appears at least
 * once) written through the real StockMovementService. Idempotent: a product
 * is found by name and its movements are written only while it has none.
 * Needs the demo operational sites and users (DemoOperationalSiteSeeder,
 * DemoUsersSeeder).
 */
class DemoStockSeeder extends Seeder
{
    private const string CATEGORY = 'Dotazioni interne';

    /** @var array<int, string> */
    private const array PRODUCTS = ['Notebook aziendale', 'Smartphone aziendale', 'Sedia ergonomica'];

    public function __construct(
        private readonly ProductService $products,
        private readonly StockMovementService $stock,
    ) {}

    public function run(): void
    {
        // Step 1: the people and sites the movements refer to.
        $sites = OperationalSite::query()->where('is_active', true)->orderBy('id')->limit(2)->get();
        $users = User::query()->where('is_active', true)->orderBy('id')->limit(3)->get();

        if ($sites->count() < 2 || $users->count() < 3) {
            $this->command?->warn('Not enough demo sites or users: stock demo skipped.');

            return;
        }

        // Step 2: the goods, then their histories.
        [$notebook, $phone, $chair] = array_map($this->seedProduct(...), self::PRODUCTS);
        [$siteA, $siteB] = $sites->all();
        [$keeper, $first, $second] = $users->all();

        $movements = [
            [$notebook, Type::Load, $siteA->id, null, ['quantity' => '10']],
            [$notebook, Type::Assign, $siteA->id, $first->id, ['quantity' => '2']],
            [$notebook, Type::Assign, $siteA->id, $second->id, ['quantity' => '1']],
            [$notebook, Type::Return, $siteA->id, $second->id, ['quantity' => '1']],
            [$notebook, Type::Transfer, $siteA->id, null, ['quantity' => '3', 'toSiteId' => $siteB->id]],
            [$phone, Type::Load, $siteA->id, null, ['quantity' => '8']],
            [$phone, Type::Assign, $siteA->id, $first->id, ['quantity' => '3']],
            [$phone, Type::Unload, $siteA->id, null, ['quantity' => '1', 'note' => 'Dispositivo smarrito']],
            [$phone, Type::Adjustment, $siteA->id, null, ['countedQuantity' => '5', 'note' => 'Conteggio di fine mese']],
            [$chair, Type::Load, $siteB->id, null, ['quantity' => '20']],
            [$chair, Type::Transfer, $siteB->id, null, ['quantity' => '5', 'toSiteId' => $siteA->id]],
        ];

        // Step 3: products that already have a history keep it (re-run safe).
        $alreadySeeded = StockMovement::query()->distinct()->pluck('product_id')->all();

        foreach ($movements as $index => [$product, $type, $siteId, $userId, $extra]) {
            if (! in_array($product->id, $alreadySeeded, true)) {
                $this->record($keeper, $product, $type, $siteId, $userId, $extra, count($movements) - $index);
            }
        }
    }

    private function seedProduct(string $name): Product
    {
        $category = ProductCategory::firstOrCreate(['name' => self::CATEGORY, 'parent_id' => null]);

        return Product::query()->where('name', $name)->where('category_id', $category->id)->first()
            ?? $this->products->create(new CreateProductData(
                name: $name,
                description: null,
                cost: null,
                price: null,
                categoryId: $category->id,
                productType: ProductType::Physical,
                usages: [ProductUsage::Internal],
            ));
    }

    /**
     * @param  array<string, string|int>  $extra
     */
    private function record(User $actor, Product $product, Type $type, int $siteId, ?int $userId, array $extra, int $daysAgo): void
    {
        $this->stock->record($actor, new StockMovementData(
            type: $type,
            productId: $product->id,
            siteId: $siteId,
            userId: $userId,
            toSiteId: $extra['toSiteId'] ?? null,
            quantity: $extra['quantity'] ?? null,
            countedQuantity: $extra['countedQuantity'] ?? null,
            movedOn: now()->subDays($daysAgo)->toDateString(),
            note: $extra['note'] ?? null,
        ));
    }
}
