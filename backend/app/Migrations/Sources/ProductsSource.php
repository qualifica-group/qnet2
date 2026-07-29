<?php

namespace App\Migrations\Sources;

use App\DataObjects\Products\CreateProductData;
use App\Enums\ProductType;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Support\ExternalApiClient;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\VatRate;
use App\Services\ProductService;
use RuntimeException;

/**
 * `products` migration source (spec 0013 / 0017): creates a product through
 * ProductService, tagged with `old_id` for idempotent re-import. `category_id`
 * is an EXTERNAL id remapped to the qnet category via `old_id` (product-categories
 * must be migrated first) — it is a REQUIRED FK, so a row whose category is not
 * migrated fails per-row (never created detached, unlike the self-referential
 * ProductCategoriesSource). `product_type` maps to the ProductType enum, falling
 * back to the default case on an absent/unknown value with a non-fatal warning.
 *
 * `vat_rate_id` IS remapped via `old_id` (vat-rates must be migrated first),
 * but unlike the category it is OPTIONAL: an unmigrated reference leaves the
 * column null with a non-fatal warning instead of failing the row.
 * `supplier_id` is still NOT remapped: `registries` carries no `old_id` and has
 * no migration source, so its external reference cannot be resolved. It is left
 * null; a non-fatal warning is surfaced when the external record carries one,
 * so the operator knows the link was dropped.
 *
 * `code` (spec 0065, D-1) carries the external `code` as-is when present; an
 * absent/blank value leaves it null so ProductService::create() falls back to
 * the sequential PRD-0001 generator (mirrors every other optional passthrough
 * field here), never duplicating the sequence across re-imports.
 */
class ProductsSource extends AbstractMigrationSource
{
    public function __construct(
        ExternalApiClient $client,
        private readonly ProductService $service,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'products';
    }

    public function label(): string
    {
        return 'Products';
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
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'code', 'label' => 'Code', 'type' => 'string'],
            ['id' => 'name', 'label' => 'Name', 'type' => 'string'],
            ['id' => 'description', 'label' => 'Description', 'type' => 'string'],
            ['id' => 'cost', 'label' => 'Cost', 'type' => 'number'],
            ['id' => 'price', 'label' => 'Price', 'type' => 'number'],
            ['id' => 'category_id', 'label' => 'Category (external id)', 'type' => 'number'],
            ['id' => 'product_type', 'label' => 'Product type', 'type' => 'string'],
            ['id' => 'vat_rate_id', 'label' => 'VAT rate (external id)', 'type' => 'number'],
            ['id' => 'supplier_id', 'label' => 'Supplier (external id, not remapped)', 'type' => 'number'],
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
            'code' => $record['code'] ?? null,
            'name' => $record['name'] ?? null,
            'description' => $record['description'] ?? null,
            'cost' => $record['cost'] ?? null,
            'price' => $record['price'] ?? null,
            'category_id' => $record['category_id'] ?? null,
            'product_type' => $record['product_type'] ?? null,
            'vat_rate_id' => $record['vat_rate_id'] ?? null,
            'supplier_id' => $record['supplier_id'] ?? null,
        ];
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        if ($this->existsByOldId(Product::class, $externalId)) {
            return MigrationRowOutcome::skipped();
        }

        $name = trim((string) ($record['name'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('name is required.');
        }

        $warnings = [];

        $product = $this->service->create(new CreateProductData(
            name: $name,
            description: $this->mapDescription($record['description'] ?? null),
            cost: (float) ($record['cost'] ?? 0),
            price: (float) ($record['price'] ?? 0),
            categoryId: $this->resolveCategory($record['category_id'] ?? null),
            productType: $this->mapProductType($record['product_type'] ?? null, $warnings),
            vatRateId: $this->resolveVatRate($record['vat_rate_id'] ?? null, $warnings),
            supplierId: $this->unresolvableReference('supplier_id', $record['supplier_id'] ?? null, $warnings),
            code: $this->mapCode($record['code'] ?? null),
        ));

        $product->old_id = $externalId;
        $product->save();

        return MigrationRowOutcome::created($warnings, $product);
    }

    /**
     * Remap the required external category reference to the qnet category id via
     * `old_id`. A product cannot exist without a category, so an absent or
     * unmigrated reference is a fatal per-row error (isolated by importRow()).
     */
    private function resolveCategory(mixed $externalRef): int
    {
        if ($externalRef === null || $externalRef === '') {
            throw new RuntimeException('category_id is required.');
        }

        $id = $this->resolveOldId(ProductCategory::class, $externalRef);

        if ($id === null) {
            throw new RuntimeException("Unresolved category_id (external id {$externalRef}); migrate product-categories first.");
        }

        return $id;
    }

    /**
     * Map the external product type string to the ProductType enum. An absent
     * value takes the default case silently; an unknown value takes the default
     * with a non-fatal warning rather than failing the row.
     *
     * @param  array<int, string>  $warnings
     */
    private function mapProductType(mixed $externalType, array &$warnings): ProductType
    {
        $default = ProductType::default() ?? ProductType::Service;

        if ($externalType === null || $externalType === '') {
            return $default;
        }

        $type = ProductType::tryFrom((string) $externalType);

        if ($type === null) {
            $warnings[] = "Unknown product_type '{$externalType}'; defaulted to '{$default->value}'.";

            return $default;
        }

        return $type;
    }

    /**
     * Remap the OPTIONAL external VAT rate reference to the qnet vat_rate id via
     * `old_id`. Absent/blank → null (no warning: the legacy row simply carries
     * none); a reference that resolves to no migrated rate → null plus a
     * non-fatal warning, never a failed row — a product is perfectly valid
     * without a VAT rate, and failing here would block the whole catalogue on a
     * missing lookup.
     *
     * @param  array<int, string>  $warnings
     */
    private function resolveVatRate(mixed $externalRef, array &$warnings): ?int
    {
        if ($externalRef === null || $externalRef === '') {
            return null;
        }

        $id = $this->resolveOldId(VatRate::class, $externalRef);

        if ($id === null) {
            $warnings[] = "Unresolved vat_rate_id (external id {$externalRef}); left empty, migrate vat-rates first.";
        }

        return $id;
    }

    /**
     * `supplier_id` cannot be remapped (`registries` carries no `old_id` and has
     * no migration source). The external reference is dropped to null; a
     * non-fatal warning records that the link was not carried.
     *
     * @param  array<int, string>  $warnings
     */
    private function unresolvableReference(string $field, mixed $externalRef, array &$warnings): ?int
    {
        if ($externalRef === null || $externalRef === '') {
            return null;
        }

        $warnings[] = "{$field} (external id {$externalRef}) not remapped; the reference has no migration source and was left empty.";

        return null;
    }

    /**
     * A blank external description becomes null (the column is nullable);
     * otherwise the trimmed string is kept.
     */
    private function mapDescription(mixed $externalDescription): ?string
    {
        if ($externalDescription === null) {
            return null;
        }

        $description = trim((string) $externalDescription);

        return $description !== '' ? $description : null;
    }

    /**
     * A blank/absent external `code` becomes null, so ProductService::create()
     * falls back to the sequential PRD-0001 generator (spec 0065, D-1b);
     * otherwise the trimmed external value is kept as-is.
     */
    private function mapCode(mixed $externalCode): ?string
    {
        if ($externalCode === null) {
            return null;
        }

        $code = trim((string) $externalCode);

        return $code !== '' ? $code : null;
    }
}
