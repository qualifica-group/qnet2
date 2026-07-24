<?php

namespace App\Migrations\Sources;

use App\CustomFields\CustomFieldEntityRegistry;
use App\DataObjects\Attributes\CreateAttributeData;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Support\ExternalApiClient;
use App\Models\Attribute;
use App\Services\AttributeService;
use RuntimeException;

/**
 * `attributes` migration source (spec 0013 / 0017, aligned to the custom
 * fields' presentation shape — spec 0021): the global attribute catalogue
 * (id, code, name, type) created through AttributeService. A phase-1 anchor:
 * the attribute owns its full presentation payload — the ENUM option list
 * (attribute_options), the per-type `config` (numeric min/max/step, text
 * length/regex, enum display) and the RELATION `relation_target` — imported in
 * the same row. An ENUM attribute without options, a RELATION without a valid
 * target, or an unrecognized `type` is rejected and isolated as a failed row.
 * Because the import bypasses StoreAttributeRequest, this source re-asserts the
 * two request-level invariants the AttributeService does NOT: enum option
 * `value` uniqueness and the relation_target shape (custom-fieldable
 * entity_type, one|many cardinality, non-empty for_select_resource). The
 * category/attribute pivot (attribute_category) is NOT carried here (mirrors
 * TagsSource: the import creates only the entity itself). Re-import is
 * idempotent (skip by old_id); `code` is unique.
 */
class AttributesSource extends AbstractMigrationSource
{
    private const RELATION_CARDINALITIES = ['one', 'many'];

    public function __construct(
        ExternalApiClient $client,
        private readonly AttributeService $service,
        private readonly CustomFieldEntityRegistry $entityRegistry,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'attributes';
    }

    public function label(): string
    {
        return 'Attributes';
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
            ['id' => 'type', 'label' => 'Type', 'type' => 'string'],
        ];
    }

    public function endpoint(): string
    {
        return 'attributes';
    }

    /**
     * Full example of the response envelope: one item per supported attribute
     * type, each showing the exact extra payload it accepts — ENUM `options`
     * (with color/icon/is_default), RELATION `relation_target`, and the
     * per-type `config`. Overrides the generic single-record template
     * (AbstractMigrationSource) because attributes carry type-dependent extras
     * a flat column list cannot express, and this sample is what the
     * super-admin reads on the Migrations page.
     *
     * @return array{items: array<int, array<string, mixed>>, pagination: array{total: int, offset: int, limit: int, total_pages: int}}
     */
    public function sampleResponse(): array
    {
        return [
            'items' => $this->exampleRecords(),
            'pagination' => [
                'total' => 13,
                'offset' => 0,
                'limit' => (int) config('migrations.default_per_page', 50),
                'total_pages' => 1,
            ],
        ];
    }

    /**
     * One representative record per registered attribute type (13 in total),
     * matching exactly the shape processRow()/AttributeService accept.
     *
     * @return array<int, array<string, mixed>>
     */
    private function exampleRecords(): array
    {
        return [
            ['id' => 1, 'code' => 'material', 'name' => 'Material', 'type' => 'text',
                'config' => ['minLength' => 2, 'maxLength' => 120, 'regex' => '/^[A-Za-z ]+$/', 'transform' => 'uppercase']],
            ['id' => 2, 'code' => 'notes', 'name' => 'Notes', 'type' => 'textarea',
                'config' => ['maxLength' => 2000]],
            ['id' => 3, 'code' => 'quantity', 'name' => 'Quantity', 'type' => 'integer',
                'config' => ['min' => 0, 'max' => 9999, 'step' => 1]],
            ['id' => 4, 'code' => 'weight_kg', 'name' => 'Weight (kg)', 'type' => 'decimal',
                'config' => ['min' => 0, 'max' => 500, 'decimals' => 2, 'step' => 0.5]],
            ['id' => 5, 'code' => 'is_active', 'name' => 'Active', 'type' => 'boolean'],
            ['id' => 6, 'code' => 'size', 'name' => 'Size', 'type' => 'enum',
                'config' => ['display' => 'select'],
                'options' => [
                    ['value' => 's', 'label' => 'Small', 'sort_order' => 0, 'color' => '#94a3b8', 'icon' => 'circle', 'is_default' => true],
                    ['value' => 'm', 'label' => 'Medium', 'sort_order' => 1],
                    ['value' => 'l', 'label' => 'Large', 'sort_order' => 2, 'color' => '#22c55e'],
                ]],
            ['id' => 7, 'code' => 'supplier', 'name' => 'Supplier', 'type' => 'relation',
                'relation_target' => ['entity_type' => 'referents', 'cardinality' => 'one', 'for_select_resource' => 'referents']],
            ['id' => 8, 'code' => 'delivery_date', 'name' => 'Delivery date', 'type' => 'date',
                'config' => ['min' => '2024-01-01', 'max' => '2030-12-31']],
            ['id' => 9, 'code' => 'imported_at', 'name' => 'Imported at', 'type' => 'datetime'],
            ['id' => 10, 'code' => 'opening_time', 'name' => 'Opening time', 'type' => 'time'],
            ['id' => 11, 'code' => 'contact_email', 'name' => 'Contact email', 'type' => 'email'],
            ['id' => 12, 'code' => 'website', 'name' => 'Website', 'type' => 'url'],
            ['id' => 13, 'code' => 'brand_color', 'name' => 'Brand color', 'type' => 'color'],
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
            'type' => $record['type'] ?? null,
        ];
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        if ($this->existsByOldId(Attribute::class, $externalId)) {
            return MigrationRowOutcome::skipped();
        }

        $code = trim((string) ($record['code'] ?? ''));

        if ($code === '') {
            throw new RuntimeException('code is required.');
        }

        $name = trim((string) ($record['name'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('name is required.');
        }

        $type = trim((string) ($record['type'] ?? ''));

        if ($type === '') {
            throw new RuntimeException('type is required.');
        }

        $attribute = $this->service->create(new CreateAttributeData(
            code: $code,
            name: $name,
            type: $type,
            config: $this->mapConfig($record),
            relationTarget: $this->mapRelationTarget($record, $type),
            options: $this->mapOptions($record),
        ));

        $attribute->old_id = $externalId;
        $attribute->save();

        return MigrationRowOutcome::created(model: $attribute);
    }

    /**
     * Map the external ENUM option list into the shape CreateAttributeData
     * expects. Returns null when no options were provided (a non-ENUM
     * attribute); an ENUM attribute that arrives without options is rejected by
     * AttributeService (422) and isolated as a failed row. `color`/`icon`/
     * `is_default` are carried through when present. Duplicate `value`s are
     * rejected here (the Service only guards option COUNT, so this replicates
     * StoreAttributeRequest's uniqueness rule the import would otherwise skip).
     *
     * @param  array<string, mixed>  $record
     * @return array<int, array{value: string, label: string, color: string|null, icon: string|null, sort_order: int, is_default: bool}>|null
     */
    private function mapOptions(array $record): ?array
    {
        $raw = $record['options'] ?? null;

        if (! is_array($raw) || $raw === []) {
            return null;
        }

        $options = [];

        foreach ($raw as $index => $option) {
            if (! is_array($option)) {
                continue;
            }

            $value = trim((string) ($option['value'] ?? ''));

            if ($value === '') {
                continue;
            }

            $label = trim((string) ($option['label'] ?? ''));
            $color = trim((string) ($option['color'] ?? ''));
            $icon = trim((string) ($option['icon'] ?? ''));

            $options[] = [
                'value' => $value,
                'label' => $label !== '' ? $label : $value,
                'color' => $color !== '' ? $color : null,
                'icon' => $icon !== '' ? $icon : null,
                'sort_order' => (int) ($option['sort_order'] ?? $index),
                'is_default' => (bool) ($option['is_default'] ?? false),
            ];
        }

        if ($options === []) {
            return null;
        }

        $values = array_column($options, 'value');

        if (count($values) !== count(array_unique($values))) {
            throw new RuntimeException('Option values must be unique.');
        }

        return $options;
    }

    /**
     * Forward the per-type `config` blob (numeric min/max/step/decimals, text
     * minLength/maxLength/regex/transform, enum display) verbatim. Mirrors
     * StoreAttributeRequest, which validates `config` only as a nullable array
     * without per-key rules — a non-array or empty blob becomes null.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>|null
     */
    private function mapConfig(array $record): ?array
    {
        $raw = $record['config'] ?? null;

        return is_array($raw) && $raw !== [] ? $raw : null;
    }

    /**
     * Map and validate the RELATION `relation_target`. Only meaningful for a
     * `relation` attribute: for any other type it returns null (a stray target
     * is dropped). A relation attribute with a missing target returns null so
     * AttributeService rejects it (422); a malformed target throws here so the
     * row fails with a precise reason — replicating the shape rules
     * ValidatesFieldTypeDefinition enforces on the (bypassed) FormRequest.
     *
     * @param  array<string, mixed>  $record
     * @return array{entity_type: string, cardinality: string, for_select_resource: string}|null
     */
    private function mapRelationTarget(array $record, string $type): ?array
    {
        if ($type !== 'relation') {
            return null;
        }

        $raw = $record['relation_target'] ?? null;

        if (! is_array($raw) || $raw === []) {
            return null;
        }

        $entityType = trim((string) ($raw['entity_type'] ?? ''));
        $cardinality = trim((string) ($raw['cardinality'] ?? ''));
        $forSelectResource = trim((string) ($raw['for_select_resource'] ?? ''));

        if (! $this->entityRegistry->isCustomFieldable($entityType)) {
            throw new RuntimeException("relation_target.entity_type [{$entityType}] is not a custom-fieldable entity.");
        }

        if (! in_array($cardinality, self::RELATION_CARDINALITIES, true)) {
            throw new RuntimeException('relation_target.cardinality must be one or many.');
        }

        if ($forSelectResource === '') {
            throw new RuntimeException('relation_target.for_select_resource is required.');
        }

        return [
            'entity_type' => $entityType,
            'cardinality' => $cardinality,
            'for_select_resource' => $forSelectResource,
        ];
    }
}
