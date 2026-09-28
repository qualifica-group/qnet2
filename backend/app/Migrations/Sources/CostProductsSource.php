<?php

namespace App\Migrations\Sources;

use App\DataObjects\Products\CreateProductData;
use App\Enums\ProductType;
use App\Enums\ProductUsage;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Sources\Concerns\MapsExternalProductRecord;
use App\Migrations\Support\CostCategoryResolver;
use App\Migrations\Support\CostProductCatalogue;
use App\Migrations\Support\ExternalApiClient;
use App\Models\Product;
use App\Services\ProductService;
use RuntimeException;

/**
 * `cost-products` migration source (spec 0174): the legacy COST tables —
 * warehouse articles, vehicles, equipment and expense reports — imported as
 * products usable only on an Offerta's Costi tab (usages = [COST]), filed
 * under the "Costi" branch provisioned by CostCategoryResolver.
 *
 * One legacy listing unions the four tables, whose ids overlap: the row's
 * `id` is "<source>:<source_id>" (what the run report shows), while the
 * product stores `old_source` = legacy table and `old_id` = `source_id`, the
 * pair idempotence is keyed on (D-4). The legacy fields with no native column
 * land in the branch's attributes (CostProductCatalogue::ATTRIBUTES, D-2).
 *
 * A legacy `code` is kept only when free and within the column length;
 * otherwise ProductService generates the PRD-0001 sequence and a warning
 * records the drop, so one clashing article never fails the row.
 */
class CostProductsSource extends AbstractMigrationSource
{
    use MapsExternalProductRecord;

    private const int CODE_MAX_LENGTH = 32;

    public function __construct(
        ExternalApiClient $client,
        private readonly ProductService $service,
        private readonly CostCategoryResolver $categories,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'cost-products';
    }

    public function label(): string
    {
        return 'Cost products';
    }

    public function endpoint(): string
    {
        return 'cost-products';
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'string'],
            ['id' => 'source', 'label' => 'Legacy table', 'type' => 'string'],
            ['id' => 'code', 'label' => 'Code', 'type' => 'string'],
            ['id' => 'name', 'label' => 'Name', 'type' => 'string'],
            ['id' => 'description', 'label' => 'Description', 'type' => 'string'],
            ['id' => 'cost', 'label' => 'Cost', 'type' => 'number'],
            ['id' => 'price', 'label' => 'Price', 'type' => 'number'],
            ['id' => 'category_name', 'label' => 'Legacy category', 'type' => 'string'],
            ['id' => 'vat_rate_id', 'label' => 'VAT rate (external id)', 'type' => 'number'],
            ['id' => 'supplier_id', 'label' => 'Supplier (external id, not remapped)', 'type' => 'number'],
            ['id' => 'brand', 'label' => 'Brand', 'type' => 'string'],
            ['id' => 'model', 'label' => 'Model', 'type' => 'string'],
            ['id' => 'serial_number', 'label' => 'Serial number', 'type' => 'string'],
            ['id' => 'license_plate', 'label' => 'License plate', 'type' => 'string'],
            ['id' => 'barcode', 'label' => 'Barcode', 'type' => 'string'],
            ['id' => 'ministerial_code', 'label' => 'Ministerial code', 'type' => 'string'],
            ['id' => 'storage_position', 'label' => 'Storage position', 'type' => 'string'],
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
        $row = [];

        foreach ($this->nativeColumns() as $column) {
            $row[$column['id']] = $record[$column['id']] ?? null;
        }

        return $row;
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        // Step 1: identify the legacy row; an already migrated one is skipped.
        $source = $this->resolveSource($record['source'] ?? null);
        $sourceId = $record['source_id'] ?? null;

        if ($sourceId === null || $sourceId === '') {
            throw new RuntimeException('source_id is required.');
        }

        if (Product::query()->where('old_source', $source)->where('old_id', $sourceId)->exists()) {
            return MigrationRowOutcome::skipped();
        }

        $name = trim((string) ($record['name'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('name is required.');
        }

        // Step 2: create the cost-only product in its branch category.
        $warnings = [];

        $product = $this->service->create(new CreateProductData(
            name: $name,
            description: $this->trimmedText($record['description'] ?? null),
            cost: (float) ($record['cost'] ?? 0),
            price: (float) ($record['price'] ?? 0),
            categoryId: $this->categories->resolve($source, $this->legacyCategory($source, $record)),
            productType: ProductType::Service,
            vatRateId: $this->resolveVatRate($record['vat_rate_id'] ?? null, $warnings),
            supplierId: $this->unresolvableReference('supplier_id', $record['supplier_id'] ?? null, $warnings),
            attributeValues: $this->attributeValues($source, $record),
            code: $this->resolveCode($record['code'] ?? null, $warnings),
            usages: [ProductUsage::Cost],
        ));

        // Step 3: anchor it for idempotent re-import.
        $product->old_id = (int) $sourceId;
        $product->old_source = $source;
        $product->save();

        return MigrationRowOutcome::created($warnings, $product);
    }

    private function resolveSource(mixed $source): string
    {
        $source = (string) $source;

        if (! array_key_exists($source, CostProductCatalogue::BRANCHES)) {
            throw new RuntimeException("Unknown cost source '{$source}'.");
        }

        return $source;
    }

    /**
     * Only warehouse articles carry a legacy category (D-1); any other table
     * lands directly on its branch.
     *
     * @param  array<string, mixed>  $record
     */
    private function legacyCategory(string $source, array $record): ?string
    {
        if ($source !== 'products') {
            return null;
        }

        return $this->trimmedText($record['category_name'] ?? null);
    }

    /**
     * The branch attributes this row has a value for, keyed by attribute code.
     * Null when there is none, so ProductService leaves `attribute_values`
     * untouched instead of writing an empty set.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, string>|null
     */
    private function attributeValues(string $source, array $record): ?array
    {
        $values = [];

        foreach (CostProductCatalogue::ATTRIBUTES[$source] as $field => $attribute) {
            $value = $this->trimmedText($record[$field] ?? null);

            if ($value !== null) {
                $values[$attribute['code']] = $value;
            }
        }

        return $values !== [] ? $values : null;
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function resolveCode(mixed $externalCode, array &$warnings): ?string
    {
        $code = $this->trimmedText($externalCode);

        if ($code === null) {
            return null;
        }

        if (mb_strlen($code) > self::CODE_MAX_LENGTH || Product::query()->where('code', $code)->exists()) {
            $warnings[] = "code '{$code}' already used or too long; a sequential code was generated.";

            return null;
        }

        return $code;
    }
}
