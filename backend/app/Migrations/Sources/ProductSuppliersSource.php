<?php

namespace App\Migrations\Sources;

use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationDependency;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\Registry;
use RuntimeException;

/**
 * `product-suppliers` migration source (spec 0203, D-7; spec 0320, D-12): gives
 * each migrated product its default `product_suppliers` row (legacy supplier
 * remapped via `old_id`, `price` and `cost` of the legacy record, 0 when absent). Reads the
 * same `products` endpoint as ProductsSource, but is a source of its own because
 * that one skips products already migrated, and runs after `registries` (the
 * supplier is a registry resolved via `old_id`, which the products phase cannot do).
 *
 * The row is only ever CREATED: a product that already has suppliers (a choice
 * made in qnet) is skipped untouched, so a re-run changes nothing. A legacy
 * product without supplier is skipped; a supplier not migrated yet leaves the
 * product as is with a warning; a product not migrated fails its row. Written
 * without activity log (as spec 0189).
 */
class ProductSuppliersSource extends AbstractMigrationSource
{
    public function key(): string
    {
        return 'product-suppliers';
    }

    public function label(): string
    {
        return 'Product suppliers';
    }

    /**
     * {@inheritDoc}
     */
    public function dependencies(): array
    {
        return [
            MigrationDependency::required('products'),
            MigrationDependency::required('registries'),
        ];
    }

    public function endpoint(): string
    {
        return 'products';
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'Product (external id)', 'type' => 'number'],
            ['id' => 'name', 'label' => 'Name', 'type' => 'string'],
            ['id' => 'supplier_id', 'label' => 'Supplier (external id)', 'type' => 'number'],
            ['id' => 'price', 'label' => 'Price', 'type' => 'number'],
            ['id' => 'cost', 'label' => 'Cost', 'type' => 'number'],
        ];
    }

    protected function externalId(array $record): int|string|null
    {
        return $record['id'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, string|int|bool|null>
     */
    protected function mapNativeRow(array $record): array
    {
        return [
            'id' => $record['id'] ?? null,
            'name' => $record['name'] ?? null,
            'supplier_id' => $record['supplier_id'] ?? null,
            'price' => $record['price'] ?? null,
            'cost' => $record['cost'] ?? null,
        ];
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        $legacySupplierId = $record['supplier_id'] ?? null;

        if ($legacySupplierId === null || $legacySupplierId === '') {
            return MigrationRowOutcome::skipped();
        }

        // Step 1: the product must already be migrated
        $product = Product::query()
            ->where('old_source', ProductsSource::OLD_SOURCE)
            ->where('old_id', $externalId)
            ->first();

        if ($product === null) {
            throw new RuntimeException("Product not migrated (legacy id {$externalId}); migrate products first.");
        }

        // Step 2: never touch a product that already has suppliers
        if ($product->productSuppliers()->exists()) {
            return MigrationRowOutcome::skipped();
        }

        // Step 3: the supplier registry, remapped via old_id
        $supplierId = $this->resolveOldId(Registry::class, $legacySupplierId);

        if ($supplierId === null) {
            return MigrationRowOutcome::skipped(["Supplier not migrated (legacy id {$legacySupplierId}); the product was left without suppliers. Migrate registries first."]);
        }

        // Step 4: the default row; the legacy price and cost are those of that supplier
        $price = bcadd((string) ($record['price'] ?? 0), '0', 2);
        $cost = bcadd((string) ($record['cost'] ?? 0), '0', 2);

        activity()->withoutLogs(function () use ($product, $supplierId, $price, $cost): void {
            ProductSupplier::query()->create([
                'product_id' => $product->id,
                'supplier_id' => $supplierId,
                'price' => $price,
                'cost' => $cost,
                'is_default' => true,
            ]);
        });

        return MigrationRowOutcome::created();
    }
}
