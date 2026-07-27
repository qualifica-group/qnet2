<?php

namespace App\Migrations\Sources;

use App\Enums\AttributeContext;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Models\Attribute;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * `product-category-attributes` migration source (spec 0013, extended): the
 * association pass that links catalogue attributes to an already-migrated
 * product category, mirroring BusinessFunctionMembersSource (which links the
 * people to an already-migrated business function).
 *
 * It re-reads the SAME external `product-categories` endpoint
 * ProductCategoriesSource consumes, taking its `attributes` array, and runs in
 * a later phase once BOTH anchors exist: neither AttributesSource nor
 * ProductCategoriesSource carries the `attribute_category` pivot, so this
 * source back-fills it once every attribute and category has an `old_id`
 * (MigrationOrder phase 5).
 *
 * Each link identifies its attribute by EXTERNAL id (`attribute_id`, remapped
 * via `old_id`) or by qnet `attribute_code`, and MUST declare its `context`
 * (product|opportunity, spec 0061): the same attribute can be assigned to a
 * category's Product section, its Opportunity section, or both (two pivot
 * rows), so the destination is never guessed. `is_required`/`sort_order` are
 * optional per-assignment extras.
 *
 * Writes go straight to the pivot and are ADDITIVE — never
 * ProductCategoryService::syncAttributes(), whose full-replace semantics would
 * wipe assignments the migration did not send. An unresolved attribute, a
 * missing/unknown context or a malformed link is a non-fatal warning that
 * ignores that single link. Re-import is idempotent: an already-linked pair is
 * left untouched (extras included), and a row that changes nothing is skipped.
 */
class ProductCategoryAttributesSource extends AbstractMigrationSource
{
    public function key(): string
    {
        return 'product-category-attributes';
    }

    public function label(): string
    {
        // Named for what the pass actually does (link attributes onto an
        // already-migrated category), so it is not mistaken for the
        // "Product categories" source in the selector.
        return 'Product categories — link attributes';
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'name', 'label' => 'Name', 'type' => 'string'],
        ];
    }

    /**
     * `attributes` is an array of link objects, not a scalar preview column, so
     * it is injected here to keep the copyable "expected response" faithful to
     * the real external contract — mirrors BusinessFunctionMembersSource::
     * sampleResponse(). Both accepted identifications (external `attribute_id`
     * and qnet `attribute_code`) are shown, each with its mandatory `context`.
     *
     * @return array{items: array<int, array<string, mixed>>, pagination: array{total: int, offset: int, limit: int, total_pages: int}}
     */
    public function sampleResponse(): array
    {
        $sample = parent::sampleResponse();
        $sample['items'][0]['attributes'] = [
            ['attribute_id' => 7, 'context' => 'product', 'is_required' => true, 'sort_order' => 0],
            ['attribute_code' => 'size', 'context' => 'opportunity'],
        ];

        return $sample;
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
        ];
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        $categoryId = $this->resolveOldId(ProductCategory::class, $externalId);

        if ($categoryId === null) {
            return MigrationRowOutcome::skipped(["Unresolved product category reference (external id {$externalId})."]);
        }

        /** @var ProductCategory $category */
        $category = ProductCategory::query()->findOrFail($categoryId);

        $warnings = [];
        $linked = $this->attachAttributes($category, (array) ($record['attributes'] ?? []), $warnings);

        return $linked
            ? MigrationRowOutcome::created($warnings, $category)
            : MigrationRowOutcome::skipped($warnings);
    }

    /**
     * Resolve every link (attribute + context) and insert the pivot rows that
     * do not exist yet; an already-linked pair is left untouched, so a re-run
     * never duplicates a row nor overwrites extras edited in qnet. Duplicate
     * links inside the SAME record collapse to one row (the pivot's
     * attribute_id/category_id/context unique would otherwise fail the whole
     * row). Returns whether at least one pivot row was created.
     *
     * @param  array<int, mixed>  $links
     * @param  array<int, string>  $warnings
     */
    private function attachAttributes(ProductCategory $category, array $links, array &$warnings): bool
    {
        $now = now();
        $rows = [];

        foreach (array_values($links) as $index => $link) {
            if (! is_array($link)) {
                $warnings[] = 'Malformed attribute link (expected an object); link ignored.';

                continue;
            }

            $attributeId = $this->resolveAttribute($link, $warnings);
            $context = $this->resolveContext($link, $warnings);

            if ($attributeId === null || $context === null) {
                continue;
            }

            $key = $attributeId.':'.$context->value;

            if (isset($rows[$key]) || $this->linkExists($category->id, $attributeId, $context)) {
                continue;
            }

            $rows[$key] = [
                'attribute_id' => $attributeId,
                'category_id' => $category->id,
                'context' => $context->value,
                'is_required' => (bool) ($link['is_required'] ?? false),
                'sort_order' => (int) ($link['sort_order'] ?? $index),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            return false;
        }

        DB::table('attribute_category')->insert(array_values($rows));

        return true;
    }

    /**
     * Resolve the link's attribute: by EXTERNAL id via `old_id` when
     * `attribute_id` is present, otherwise by the qnet `attribute_code` (the
     * `attributes.code` column is unique). A link carrying neither, or one
     * whose reference resolves to no attribute, is a non-fatal warning.
     *
     * @param  array<string, mixed>  $link
     * @param  array<int, string>  $warnings
     */
    private function resolveAttribute(array $link, array &$warnings): ?int
    {
        $externalRef = $link['attribute_id'] ?? null;

        if ($externalRef !== null && $externalRef !== '') {
            $id = $this->resolveOldId(Attribute::class, $externalRef);

            if ($id === null) {
                $warnings[] = "Unresolved attribute reference (external id {$externalRef}); link ignored.";
            }

            return $id;
        }

        $code = trim((string) ($link['attribute_code'] ?? ''));

        if ($code === '') {
            $warnings[] = 'Attribute link without attribute_id or attribute_code; link ignored.';

            return null;
        }

        /** @var int|null $id */
        $id = Attribute::query()->where('code', $code)->value('id');

        if ($id === null) {
            $warnings[] = "Unresolved attribute reference (code {$code}); link ignored.";
        }

        return $id;
    }

    /**
     * The destination section of the assignment (spec 0061) is MANDATORY on
     * every link: the same attribute can legitimately belong to the Product
     * section, the Opportunity section, or both, so an absent or unknown
     * context is never defaulted — it is a non-fatal warning that ignores the
     * link.
     *
     * @param  array<string, mixed>  $link
     * @param  array<int, string>  $warnings
     */
    private function resolveContext(array $link, array &$warnings): ?AttributeContext
    {
        $raw = trim((string) ($link['context'] ?? ''));

        if ($raw === '') {
            $warnings[] = 'Attribute link without context (expected product or opportunity); link ignored.';

            return null;
        }

        $context = AttributeContext::tryFrom($raw);

        if ($context === null) {
            $warnings[] = "Unknown attribute link context [{$raw}] (expected product or opportunity); link ignored.";
        }

        return $context;
    }

    private function linkExists(int $categoryId, int $attributeId, AttributeContext $context): bool
    {
        return DB::table('attribute_category')
            ->where('category_id', $categoryId)
            ->where('attribute_id', $attributeId)
            ->where('context', $context->value)
            ->exists();
    }
}
