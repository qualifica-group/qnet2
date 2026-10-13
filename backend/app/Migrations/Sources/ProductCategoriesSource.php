<?php

namespace App\Migrations\Sources;

use App\DataObjects\ProductCategories\CreateProductCategoryData;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationDependency;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Support\CategoryBusinessFunctionLinker;
use App\Migrations\Support\ExternalApiClient;
use App\Migrations\Support\LegacyAplBranch;
use App\Models\ProductCategory;
use App\Services\ProductCategoryService;
use RuntimeException;

/**
 * `product-categories` migration source (spec 0013 / 0017): a SELF-referential
 * tree (id, name, parent_id, inherits_attributes, inherits_quote_attributes,
 * inherits_work_order_attributes, requires_quote, is_selectable, is_active, description, business_function_id) created through
 * ProductCategoryService. `business_function_id` is an EXTERNAL business
 * function id remapped via `old_id` (CategoryBusinessFunctionLinker), kept as
 * sent: no "FORMAZIONE OLD" / "APL OLD" replacement (user directive
 * 2026-10-13). `is_selectable` (spec 0074) is a plain per-node flag; `requires_quote` is
 * owned by the branch root and only authored on a rootless row (see
 * mapRequiresQuote()). `parent_id` is an EXTERNAL id remapped to the qnet
 * parent via `old_id`. The legacy lists parents before their children
 * (global topological order), so a parent that does not resolve is a genuine
 * anomaly: the child is created detached with a non-fatal warning.
 * The category/attribute pivot (attribute_category) is NOT carried here
 * (mirrors SectorsSource: the import creates only the entity itself). Re-import
 * is idempotent (skip by old_id). Every legacy category is CREATED, never
 * adopted into a qnet one: the legacy sends its name behind the "[OLD] "
 * prefix, so it stays apart from the qnet catalogue, and as NOT active: the
 * migrated catalogue is history only, hidden with its products from every
 * input (spec 0208), while the records linked to it stay valid (user
 * directive 2026-10-13). The legacy APL branch arrives as "[OLD] APL" and is imported
 * as containers (LegacyAplBranch).
 */
class ProductCategoriesSource extends AbstractMigrationSource
{
    /**
     * The qnet ids THIS run created. afterImport() closes the ones that end up
     * in the "[OLD] APL" branch; a node an earlier run created is left alone,
     * so a selectability set by hand survives a re-import.
     *
     * @var list<int>
     */
    private array $createdIds = [];

    public function __construct(
        ExternalApiClient $client,
        private readonly ProductCategoryService $service,
        private readonly CategoryBusinessFunctionLinker $businessFunctions,
        private readonly LegacyAplBranch $aplBranch,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'product-categories';
    }

    public function label(): string
    {
        return 'Product categories';
    }

    /**
     * {@inheritDoc}
     */
    public function dependencies(): array
    {
        return [
            MigrationDependency::optional('business-functions'),
        ];
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'name', 'label' => 'Name', 'type' => 'string'],
            ['id' => 'parent_id', 'label' => 'Parent (external id)', 'type' => 'number'],
            ['id' => 'inherits_attributes', 'label' => 'Inherits attributes', 'type' => 'boolean'],
            ['id' => 'inherits_quote_attributes', 'label' => 'Inherits quote attributes (optional)', 'type' => 'boolean'],
            ['id' => 'inherits_work_order_attributes', 'label' => 'Inherits work order attributes (optional)', 'type' => 'boolean'],
            ['id' => 'requires_quote', 'label' => 'Requires quote (root only)', 'type' => 'boolean'],
            ['id' => 'is_selectable', 'label' => 'Selectable', 'type' => 'boolean'],
            ['id' => 'is_active', 'label' => 'Active', 'type' => 'boolean'],
            ['id' => 'description', 'label' => 'Description', 'type' => 'string'],
            ['id' => 'business_function_id', 'label' => 'Business function (external id)', 'type' => 'number'],
        ];
    }

    public function endpoint(): string
    {
        return 'product-categories';
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
            'parent_id' => $record['parent_id'] ?? null,
            'inherits_attributes' => $record['inherits_attributes'] ?? null,
            'inherits_quote_attributes' => $record['inherits_quote_attributes'] ?? null,
            'inherits_work_order_attributes' => $record['inherits_work_order_attributes'] ?? null,
            'requires_quote' => $record['requires_quote'] ?? null,
            'is_selectable' => $record['is_selectable'] ?? null,
            'is_active' => $record['is_active'] ?? null,
            'description' => $record['description'] ?? null,
            'business_function_id' => $record['business_function_id'] ?? null,
        ];
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        if ($this->existsByOldId(ProductCategory::class, $externalId)) {
            return MigrationRowOutcome::skipped();
        }

        $name = trim((string) ($record['name'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('name is required.');
        }

        $warnings = [];
        $parentId = $this->resolveParent($record['parent_id'] ?? null, $warnings);

        $inherits = $this->inheritanceFlags($record) ?? [
            'product' => true,
            'quote' => true,
            'work_order' => true,
        ];

        $category = $this->service->create(new CreateProductCategoryData(
            name: $name,
            parentId: $parentId,
            inheritsProductAttributes: $inherits['product'],
            inheritsQuoteAttributes: $inherits['quote'],
            inheritsWorkOrderAttributes: $inherits['work_order'],
            description: $this->mapDescription($record['description'] ?? null),
            businessFunctionId: $this->businessFunctions->ownFunctionFor($record['business_function_id'] ?? null, $parentId, $warnings),
            requiresQuote: $this->mapRequiresQuote($record, $parentId),
            isSelectable: array_key_exists('is_selectable', $record)
                ? (bool) $record['is_selectable']
                : true,
            isActive: array_key_exists('is_active', $record)
                ? (bool) $record['is_active']
                : true,
        ));

        $category->old_id = $externalId;
        $category->save();
        $this->createdIds[] = $category->id;

        return MigrationRowOutcome::created($warnings, $category);
    }

    /**
     * The three inheritance barriers (Product / Quote / Commessa, spec 0084 +
     * 0098) from the external record. The external system carries
     * `inherits_attributes` (the Product barrier and the fallback for the other
     * two) and, since spec 0182 E-10, optional `inherits_quote_attributes` /
     * `inherits_work_order_attributes` (false for a category with cards of its
     * own). Null when the record carries none of the three (nothing to say).
     *
     * @param  array<string, mixed>  $record
     * @return array{product: bool, quote: bool, work_order: bool}|null
     */
    private function inheritanceFlags(array $record): ?array
    {
        $carried = array_intersect_key($record, array_flip([
            'inherits_attributes', 'inherits_quote_attributes', 'inherits_work_order_attributes',
        ]));

        if ($carried === []) {
            return null;
        }

        $single = array_key_exists('inherits_attributes', $record) ? (bool) $record['inherits_attributes'] : true;

        return [
            'product' => $single,
            'quote' => array_key_exists('inherits_quote_attributes', $record) ? (bool) $record['inherits_quote_attributes'] : $single,
            'work_order' => array_key_exists('inherits_work_order_attributes', $record) ? (bool) $record['inherits_work_order_attributes'] : $single,
        ];
    }

    /**
     * Closes the legacy APL tree: it is never a classification target (user
     * directive 2026-10-05).
     */
    protected function afterImport(MigrationImportContext $context): void
    {
        $this->aplBranch->closeLegacyBranch($this->createdIds);
    }

    /**
     * The `requires_quote` flag is OWNED BY THE BRANCH ROOT
     * (RequiresQuoteInheritance): a category that resolved a parent takes the
     * root's value verbatim, and submitting a different one is refused by the
     * Service's no-override guard — so the external flag is only authored when
     * the category is created rootless. Absent externally = null: the Service then
     * resolves it (root's value, or false at root).
     *
     * @param  array<string, mixed>  $record
     */
    private function mapRequiresQuote(array $record, ?int $parentId): ?bool
    {
        if ($parentId !== null || ! array_key_exists('requires_quote', $record)) {
            return null;
        }

        return (bool) $record['requires_quote'];
    }

    /**
     * Remap the external parent reference to the qnet parent id via `old_id`.
     * Absent/blank means a root category (null, no warning); a reference that
     * resolves to no migrated parent gets a non-fatal warning and the category
     * is created detached.
     *
     * @param  array<int, string>  $warnings
     */
    private function resolveParent(mixed $externalRef, array &$warnings): ?int
    {
        if ($externalRef === null || $externalRef === '') {
            return null;
        }

        $id = $this->resolveOldId(ProductCategory::class, $externalRef);

        if ($id === null) {
            $warnings[] = "Unresolved parent_id (external id {$externalRef}); category created detached.";
        }

        return $id;
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
}
