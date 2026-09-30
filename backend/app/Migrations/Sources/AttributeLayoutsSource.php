<?php

namespace App\Migrations\Sources;

use App\Enums\AttributeContext;
use App\Enums\LayoutFormScope;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Support\ExternalApiClient;
use App\Models\AttributeLayout;
use App\Models\ProductCategory;
use App\Services\ProductCategories\AttributeLayoutService;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * `attribute-layouts` migration source (spec 0181): the per-category form
 * layout (sections -> rows -> items) a legacy category shows for one
 * (context, form_mode), written through AttributeLayoutService::upsert so the
 * AttributeLayoutValidator allow-list (the codes the category actually has
 * linked for the context) applies exactly as in the editor. Runs after
 * `product-category-attributes`, which creates those links.
 *
 * The category is identified by EXTERNAL id (`category_id`, remapped via
 * `old_id`); an unknown one skips the row with a warning. An already
 * configured (category, context, form_mode) layout — authored by hand or by a
 * previous import — is never overwritten, which also makes a re-import
 * idempotent. A layout the validator rejects fails the row with its message
 * and writes nothing. The layout has no `old_id` of its own: the synthetic
 * external id ("<category_id>-<context>-<form_mode>") only labels the report.
 */
class AttributeLayoutsSource extends AbstractMigrationSource
{
    public function __construct(
        ExternalApiClient $client,
        private readonly AttributeLayoutService $service,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'attribute-layouts';
    }

    public function label(): string
    {
        return 'Attribute layouts';
    }

    public function endpoint(): string
    {
        return 'attribute-layouts';
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'string'],
            ['id' => 'category_id', 'label' => 'Category (external id)', 'type' => 'number'],
            ['id' => 'context', 'label' => 'Context', 'type' => 'string'],
            ['id' => 'form_mode', 'label' => 'Form mode', 'type' => 'string'],
        ];
    }

    /**
     * The `layout` blob is nested, so it is injected into the generic
     * single-record template to keep the copyable expected response faithful.
     *
     * @return array{items: array<int, array<string, mixed>>, pagination: array{total: int, offset: int, limit: int, total_pages: int}}
     */
    public function sampleResponse(): array
    {
        $sample = parent::sampleResponse();
        $sample['items'][0] = [
            'id' => '70-work_order-all',
            'category_id' => 70,
            'context' => 'work_order',
            'form_mode' => 'all',
            'layout' => ['sections' => [[
                'id' => 'details',
                'title' => 'Details',
                'description' => null,
                'variant' => 'default',
                'collapsible' => false,
                'default_collapsed' => false,
                'columns' => 2,
                'sort_order' => 0,
                'rows' => [['id' => 'row-1', 'items' => [
                    ['attribute_code' => 'size', 'width' => 'half'],
                    ['attribute_code' => 'notes', 'width' => 'half'],
                ]]],
            ]]],
        ];

        return $sample;
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
            'category_id' => $record['category_id'] ?? null,
            'context' => $record['context'] ?? null,
            'form_mode' => $record['form_mode'] ?? null,
        ];
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        // Step 1: resolve the destination category, context and scope
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        $categoryRef = $record['category_id'] ?? null;
        $categoryId = $categoryRef === null ? null : $this->resolveOldId(ProductCategory::class, $categoryRef);

        if ($categoryId === null) {
            return MigrationRowOutcome::skipped(["Unresolved product category reference (external id {$categoryRef})."]);
        }

        $attributeContext = AttributeContext::tryFrom((string) ($record['context'] ?? ''))
            ?? throw new RuntimeException('Unknown layout context (expected product, quote or work_order).');
        $scope = LayoutFormScope::tryFrom((string) ($record['form_mode'] ?? ''))
            ?? throw new RuntimeException('Unknown layout form_mode (expected all, create, edit or view).');

        $layout = $record['layout'] ?? null;

        if (! is_array($layout) || ($layout['sections'] ?? []) === []) {
            throw new RuntimeException('layout.sections is required.');
        }

        // Step 2: never overwrite a layout that is already configured
        if ($this->layoutExists($categoryId, $attributeContext, $scope)) {
            return MigrationRowOutcome::skipped();
        }

        // Step 3: validate against the linked attributes and persist
        /** @var ProductCategory $category */
        $category = ProductCategory::query()->findOrFail($categoryId);

        try {
            $this->service->upsert($category, $attributeContext, $scope, $layout);
        } catch (ValidationException $exception) {
            throw new RuntimeException('Invalid layout: '.collect($exception->errors())->flatten()->implode(' '));
        }

        return MigrationRowOutcome::created();
    }

    private function layoutExists(int $categoryId, AttributeContext $context, LayoutFormScope $scope): bool
    {
        return AttributeLayout::query()
            ->where('product_category_id', $categoryId)
            ->where('context', $context->value)
            ->where('form_mode', $scope->value)
            ->exists();
    }
}
