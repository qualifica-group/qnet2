<?php

namespace App\Migrations\Sources;

use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Models\Product;
use App\Models\Registry;
use RuntimeException;

/**
 * `product-suppliers` migration source (spec 0203, D-7): links each migrated
 * product to its legacy supplier. Reads the same `products` endpoint as
 * ProductsSource, but is a source of its own because that one skips products
 * already migrated, and runs after `registries` (the supplier is a registry
 * resolved via `old_id`, which the products phase cannot do).
 *
 * `supplier_id` is only ever FILLED: a product that already has one (a choice
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

        // Step 2: never overwrite a supplier already set
        if ($product->supplier_id !== null) {
            return MigrationRowOutcome::skipped();
        }

        // Step 3: the supplier registry, remapped via old_id
        $supplierId = $this->resolveOldId(Registry::class, $legacySupplierId);

        if ($supplierId === null) {
            return MigrationRowOutcome::skipped(["Supplier not migrated (legacy id {$legacySupplierId}); the product was left without supplier. Migrate registries first."]);
        }

        activity()->withoutLogs(function () use ($product, $supplierId): void {
            $product->supplier_id = $supplierId;
            $product->save();
        });

        return MigrationRowOutcome::created();
    }
}
