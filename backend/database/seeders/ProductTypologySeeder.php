<?php

namespace Database\Seeders;

use App\Enums\SupplierCommissionDirection;
use App\Models\ProductTypology;
use Illuminate\Database\Seeder;

/**
 * Seed the initial product typology catalogue (spec 0099, D-4). Like
 * UnitOfMeasureSeeder — and unlike the Demo* lookup seeders — this is CLEAN
 * reference data: no `Demo` prefix, called from DatabaseSeeder::run() itself,
 * never from DemoDataSeeder, because a production installation needs these
 * rows without ever running the demo fixtures.
 *
 * These are ORDINARY records (requirement 2): no enum, no constant, no
 * hardcoded condition, no name-based logic anywhere in the application. Once
 * seeded they are renamed, edited or deleted from the module like any other
 * row — the only reference the code keeps is `institution`, resolved by CODE
 * (D-3) as the default a Product falls back to, never by name.
 *
 * `code='institution'` ("Ente") already exists by the time this runs:
 * 2026_09_03_100000_create_product_typologies_table.php inserts it directly
 * so the very next migration (products backfill) has a default row to point
 * every existing product at. `firstOrCreate(['code' => ...])` finds that row
 * without duplicating it — idempotent on re-run, never overwriting a manual
 * rename made through the `product-typologies` CRUD module.
 */
class ProductTypologySeeder extends Seeder
{
    /**
     * [code, name, Supplier commission direction, badge color] (specs 0202, 0204): both enabled.
     *
     * @var array<int, array{0: string, 1: string, 2: SupplierCommissionDirection, 3: string}>
     */
    private const array TYPOLOGIES = [
        ['institution', 'Ente', SupplierCommissionDirection::Received, 'violet'],
        ['consultancy', 'Consulenza', SupplierCommissionDirection::Paid, 'blue'],
    ];

    public function run(): void
    {
        foreach (self::TYPOLOGIES as [$code, $name, $direction, $color]) {
            ProductTypology::firstOrCreate(['code' => $code], [
                'name' => $name,
                'color' => $color,
                'supplier_commission_enabled' => true,
                'supplier_commission_direction' => $direction,
            ]);
        }
    }
}
