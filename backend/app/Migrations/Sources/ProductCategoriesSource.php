<?php

namespace App\Migrations\Sources;

use App\DataObjects\ProductCategories\CreateProductCategoryData;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Support\ExternalApiClient;
use App\Models\ProductCategory;
use App\Services\ProductCategories\RequiresQuoteInheritance;
use App\Services\ProductCategoryService;
use RuntimeException;

/**
 * `product-categories` migration source (spec 0013 / 0017): a SELF-referential
 * tree (id, name, parent_id, inherits_attributes, requires_quote,
 * is_selectable, description) created through ProductCategoryService.
 * `is_selectable` (spec 0074) is a plain per-node flag; `requires_quote` is
 * owned by the branch root and only authored on a rootless row (see
 * mapRequiresQuote()). `parent_id` is an EXTERNAL id remapped to the qnet
 * parent via `old_id`. A child whose parent has not been migrated yet (parent
 * later in the same external listing) is created detached with a non-fatal
 * warning, then relinked in a second pass (afterImport) once every node exists.
 * The category/attribute pivot (attribute_category) is NOT carried here
 * (mirrors SectorsSource: the import creates only the entity itself). Re-import
 * is idempotent (skip by old_id); the name is NOT unique.
 */
class ProductCategoriesSource extends AbstractMigrationSource
{
    public function __construct(
        ExternalApiClient $client,
        private readonly ProductCategoryService $service,
        private readonly RequiresQuoteInheritance $requiresQuote,
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
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'name', 'label' => 'Name', 'type' => 'string'],
            ['id' => 'parent_id', 'label' => 'Parent (external id)', 'type' => 'number'],
            ['id' => 'inherits_attributes', 'label' => 'Inherits attributes', 'type' => 'boolean'],
            ['id' => 'requires_quote', 'label' => 'Requires quote (root only)', 'type' => 'boolean'],
            ['id' => 'is_selectable', 'label' => 'Selectable', 'type' => 'boolean'],
            ['id' => 'description', 'label' => 'Description', 'type' => 'string'],
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
            'requires_quote' => $record['requires_quote'] ?? null,
            'is_selectable' => $record['is_selectable'] ?? null,
            'description' => $record['description'] ?? null,
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

        // The external system carries a SINGLE inheritance flag; qnet splits it
        // per usage context (Product / Opportunity), so the imported value seeds
        // both barriers identically and is decoupled from qnet on afterwards.
        $inheritsAttributes = array_key_exists('inherits_attributes', $record)
            ? (bool) $record['inherits_attributes']
            : true;

        $category = $this->service->create(new CreateProductCategoryData(
            name: $name,
            parentId: $parentId,
            inheritsProductAttributes: $inheritsAttributes,
            inheritsOpportunityAttributes: $inheritsAttributes,
            description: $this->mapDescription($record['description'] ?? null),
            requiresQuote: $this->mapRequiresQuote($record, $parentId),
            isSelectable: array_key_exists('is_selectable', $record)
                ? (bool) $record['is_selectable']
                : true,
        ));

        $category->old_id = $externalId;
        $category->save();

        return MigrationRowOutcome::created($warnings, $category);
    }

    /**
     * Second pass: relink every category that was created detached because its
     * parent had not been migrated yet. Now that all nodes exist, resolve the
     * external parent via `old_id` and set it where it is still null. Leaves an
     * already-linked or genuinely-rootless category untouched (idempotent).
     */
    protected function afterImport(MigrationImportContext $context): void
    {
        $this->eachRecord(function (array $record): void {
            $externalParent = $record['parent_id'] ?? null;

            if ($externalParent === null || $externalParent === '') {
                return;
            }

            $category = ProductCategory::query()
                ->where('old_id', $this->externalId($record))
                ->whereNull('parent_id')
                ->first();

            $parentId = $category === null ? null : $this->resolveOldId(ProductCategory::class, $externalParent);

            if ($category !== null && $parentId !== null) {
                $category->update(['parent_id' => $parentId]);

                // The relink changed the branch root: a category authored its
                // own `requires_quote` while detached, so realign it and its
                // subtree on the root's value (the invariant this bypassed by
                // writing parent_id outside ProductCategoryService::update).
                $this->requiresQuote->syncSubtree($category);
            }
        });
    }

    /**
     * The `requires_quote` flag is OWNED BY THE BRANCH ROOT
     * (RequiresQuoteInheritance): a category that resolved a parent takes the
     * root's value verbatim, and submitting a different one is refused by the
     * Service's no-override guard — so the external flag is only authored when
     * the category is created rootless. A child created DETACHED (forward
     * reference) is therefore authored here and realigned by afterImport()
     * right after the relink. Absent externally = null: the Service then
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
     * resolves to no migrated parent gets a non-fatal warning, the category is
     * created detached and relinked later by afterImport() if the parent then
     * exists.
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
            $warnings[] = "Unresolved parent_id (external id {$externalRef}); category created detached, relinked if the parent is migrated.";
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
