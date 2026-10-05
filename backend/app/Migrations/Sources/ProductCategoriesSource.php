<?php

namespace App\Migrations\Sources;

use App\DataObjects\ProductCategories\CreateProductCategoryData;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Support\CategoryBusinessFunctionLinker;
use App\Migrations\Support\ExternalApiClient;
use App\Migrations\Support\LegacyAplBranch;
use App\Models\ProductCategory;
use App\Services\ProductCategories\RequiresQuoteInheritance;
use App\Services\ProductCategoryService;
use RuntimeException;

/**
 * `product-categories` migration source (spec 0013 / 0017): a SELF-referential
 * tree (id, name, parent_id, inherits_attributes, inherits_quote_attributes,
 * inherits_work_order_attributes, requires_quote, is_selectable, description, business_function_id) created through
 * ProductCategoryService. `business_function_id` is an EXTERNAL business
 * function id remapped via `old_id` (CategoryBusinessFunctionLinker), which
 * also redirects the functions a created category may not be filed on
 * ("Formazione" to "FORMAZIONE OLD", user directive 2026-09-16) and imports
 * the categories filed there as not selectable (user directive 2026-10-05).
 * `is_selectable` (spec 0074) is a plain per-node flag; `requires_quote` is
 * owned by the branch root and only authored on a rootless row (see
 * mapRequiresQuote()). `parent_id` is an EXTERNAL id remapped to the qnet
 * parent via `old_id`. A child whose parent has not been migrated yet (parent
 * later in the same external listing) is created detached with a non-fatal
 * warning, then relinked in a second pass (afterImport) once every node exists.
 * The category/attribute pivot (attribute_category) is NOT carried here
 * (mirrors SectorsSource: the import creates only the entity itself). Re-import
 * is idempotent (skip by old_id); `name` carries no unique index, so a category
 * qnet ALREADY holds under that name is ADOPTED and refreshed rather than
 * duplicated (user directive 2026-09-07, mirrors SourcesSource) — see adopt().
 * Never into the manual "APL" branch, though: its legacy twin is imported
 * beside it as "APL old", not selectable (user directive 2026-10-05,
 * LegacyAplBranch).
 */
class ProductCategoriesSource extends AbstractMigrationSource
{
    /**
     * The qnet ids THIS run created DETACHED, because their external parent
     * was a forward reference (see resolveParent()). Only these are relinked
     * by afterImport().
     *
     * Scoping the relink pass to them is what keeps it off the nodes it has no
     * say over — first of all the ones it ADOPTED (adopt(): their position is
     * qnet's), which are legitimately parentless whenever the static catalogue
     * seeds them as ROOTS. Without this list, `parent_id IS NULL` alone would
     * match an adopted "Formazione" or "APL" root and drag it under its legacy
     * parent on the very next import.
     *
     * Same-run scope is the hook's own contract (AbstractMigrationSource::
     * afterImport: "a self-referential parent processed after its child in the
     * same run"), and the instance lives for exactly one run.
     *
     * @var list<int>
     */
    private array $detachedIds = [];

    /**
     * The qnet ids THIS run created. afterImport() closes the ones that end up
     * in the "APL old" branch or on an "OLD" business function; a node an earlier run created is left alone, so
     * a selectability set by hand survives a re-import.
     *
     * @var list<int>
     */
    private array $createdIds = [];

    public function __construct(
        ExternalApiClient $client,
        private readonly ProductCategoryService $service,
        private readonly RequiresQuoteInheritance $requiresQuote,
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

        // Adopt the category qnet already holds under this name and that no
        // other external id has claimed — the static catalogue seeds part of
        // the same tree (QualificaCatalogSeeder), and a second row with the
        // same name would be an unusable duplicate in every select.
        $existing = ProductCategory::query()->where('name', $name)->whereNull('old_id')->first();
        $manualTwin = $this->aplBranch->manualNodeNamed($name);

        if ($existing !== null && $manualTwin === null) {
            return $this->adopt($existing, $externalId, $record);
        }

        // The legacy twin of a manual APL node is imported beside it, under a
        // name of its own, wherever its legacy parent puts it (LegacyAplBranch).
        if ($manualTwin !== null) {
            $name = $this->aplBranch->legacyName($name);
            $warnings[] = sprintf('Legacy twin of the catalogue category "%s" imported as "%s" instead of adopted.', $manualTwin->name, $name);
        }

        $parentId = $this->resolveParent($record['parent_id'] ?? null, $warnings, $this->isSelfParented($record));

        if ($this->isSelfParented($record)) {
            $warnings[] = 'parent_id equals the category own id (spec 0183 F-9); imported as a root.';
        }

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
        ));

        $category->old_id = $externalId;
        $category->save();
        $this->createdIds[] = $category->id;

        // Created without the parent its external record names: afterImport()
        // retries it once every node of this run exists.
        if ($parentId === null && $this->namesAParent($record)) {
            $this->detachedIds[] = $category->id;
        }

        return MigrationRowOutcome::created($warnings, $category);
    }

    /**
     * Claims an existing qnet category for $externalId and refreshes it from
     * the external record, instead of creating a second node under the same
     * name.
     *
     * Only the DESCRIPTIVE fields are refreshed. What the node's own tree
     * says about itself is qnet's and stays put:
     *   - `parent_id` — adopting must never MOVE a branch. The external tree
     *     is nested under one root by QualificaLegacyImportSeeder; a category
     *     the static catalogue already placed keeps the place it gave it;
     *   - `requires_quote` — owned by the branch root
     *     (RequiresQuoteInheritance), never authored on a node with a parent;
     *   - `is_selectable` — the static catalogue REALIGNS it on every seed run
     *     (spec 0074), so writing the external value here would only survive
     *     until the next seed, and would reopen a container in the meantime.
     *
     * `business_function_id` is only filled into a FREE slot, so an assignment
     * made in qnet (QualificaBusinessFunctionLinkSeeder, or by hand) survives.
     *
     * `old_id` is set directly: it is not fillable, being the migration
     * engine's own bookkeeping rather than a domain field.
     *
     * @param  array<string, mixed>  $record
     */
    private function adopt(ProductCategory $category, int|string $externalId, array $record): MigrationRowOutcome
    {
        $warnings = [sprintf('Existing category "%s" adopted and refreshed instead of duplicated.', $category->name)];

        $category->old_id = $externalId;
        $category->fill($this->adoptableAttributes($record));
        $this->businessFunctions->fillFreeSlot($category, $record['business_function_id'] ?? null, $warnings);
        $category->save();
        $this->cutManualChildrenFromLegacyFields($category);

        return MigrationRowOutcome::created($warnings, $category);
    }

    /**
     * Spec 0183 F-6: the adopted category now receives the legacy Offerta /
     * Commessa fields, which its manual (no `old_id`) children would inherit
     * on top of the fields they already had. Closing their quote and
     * work_order barriers keeps exactly the pre-import set. The DIRECT children
     * are enough: CategoryHierarchy::inheritedAncestors() stops climbing at the
     * first opted-out ancestor, so a deeper manual descendant already reaches
     * nothing above its own opted-out parent. The Product barrier is left as
     * is. Idempotent: it rewrites the same two flags to the same value.
     */
    private function cutManualChildrenFromLegacyFields(ProductCategory $adopted): void
    {
        ProductCategory::query()
            ->where('parent_id', $adopted->id)
            ->whereNull('old_id')
            ->update(['inherits_quote_attributes' => false, 'inherits_work_order_attributes' => false]);
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
     * The fields an adoption refreshes, each only when the external record
     * carries it — an absent key means "the external system says nothing",
     * which must not blank the value qnet holds.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function adoptableAttributes(array $record): array
    {
        $attributes = [];

        if (array_key_exists('description', $record)) {
            $attributes['description'] = $this->mapDescription($record['description']);
        }

        $inherits = $this->inheritanceFlags($record);

        if ($inherits !== null) {
            $attributes['inherits_product_attributes'] = $inherits['product'];
            $attributes['inherits_quote_attributes'] = $inherits['quote'];
            $attributes['inherits_work_order_attributes'] = $inherits['work_order'];
        }

        return $attributes;
    }

    /**
     * Second pass, once every node of this run exists.
     */
    protected function afterImport(MigrationImportContext $context): void
    {
        // Step 1: the forward references, so each node sits in its branch.
        $this->relinkDetached();

        // Step 2: the legacy APL tree and every category filed on an "OLD"
        // function are never a classification target (user directive
        // 2026-10-05) — after step 1, which places the late children.
        $this->aplBranch->closeLegacyBranch($this->createdIds);
        $this->businessFunctions->closeRedirected($this->createdIds);
    }

    /**
     * Relinks the categories THIS run created detached because their parent
     * had not been migrated yet ($detachedIds): resolve the external parent via
     * `old_id` and set it where it is still null. Leaves an already-linked or
     * genuinely-rootless category untouched (idempotent), and never touches a
     * node it did not create — an ADOPTED root is parentless by design, not by
     * accident.
     */
    private function relinkDetached(): void
    {
        if ($this->detachedIds === []) {
            return;
        }

        $this->eachRecord(function (array $record): void {
            if (! $this->namesAParent($record)) {
                return;
            }

            $category = ProductCategory::query()
                ->where('old_id', $this->externalId($record))
                ->whereIntegerInRaw('id', $this->detachedIds)
                ->whereNull('parent_id')
                ->first();

            $parentId = $category === null
                ? null
                : $this->resolveOldId(ProductCategory::class, $record['parent_id']);

            if ($category !== null && $parentId !== null) {
                $category->update(['parent_id' => $parentId]);

                // The relink changed the branch root: a category authored its
                // own `requires_quote` while detached, so realign it and its
                // subtree on the root's value, and let its business function give
                // way to the branch's (the invariants this bypassed by writing
                // parent_id outside ProductCategoryService::update).
                $this->requiresQuote->syncSubtree($category);
                $this->businessFunctions->realignAfterRelink($category);
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
     * Whether the external record points at a parent at all — absent or blank
     * means a legacy ROOT, which is not detached and must never be relinked.
     *
     * @param  array<string, mixed>  $record
     */
    private function namesAParent(array $record): bool
    {
        $externalParent = $record['parent_id'] ?? null;

        return $externalParent !== null && $externalParent !== '' && ! $this->isSelfParented($record);
    }

    /**
     * A legacy row whose `parent_id` is its own id (spec 0183 F-9) is a root:
     * treating it as a parent reference would leave it detached forever.
     *
     * @param  array<string, mixed>  $record
     */
    private function isSelfParented(array $record): bool
    {
        $externalParent = $record['parent_id'] ?? null;

        return $externalParent !== null && $externalParent !== '' && (string) $externalParent === (string) ($record['id'] ?? '');
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
    private function resolveParent(mixed $externalRef, array &$warnings, bool $selfParented = false): ?int
    {
        if ($externalRef === null || $externalRef === '' || $selfParented) {
            return null;
        }

        $id = $this->resolveOldId(ProductCategory::class, $externalRef);

        if ($id === null) {
            $warnings[] = "Unresolved parent_id (external id {$externalRef}); category created detached, relinked at the end of this run if the parent is listed later in it.";
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
