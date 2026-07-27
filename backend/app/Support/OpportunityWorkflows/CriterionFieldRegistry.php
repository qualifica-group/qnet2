<?php

declare(strict_types=1);

namespace App\Support\OpportunityWorkflows;

use App\CustomFields\CustomFieldEntityRegistry;
use App\CustomFields\CustomFieldProvider;
use App\CustomFields\CustomFieldRelationLabelResolver;
use App\Models\CustomFieldDefinition;
use App\Models\Opportunity;
use InvalidArgumentException;

/**
 * Centralized allow-list of the "criterion fields" a workflow may be matched
 * on (spec 0047, scope item: "Allow-list dei campi-criterio centralizzata
 * backend"). The single source of truth consumed by both the resolver
 * (App\Services\Opportunities\OpportunityWorkflowResolver) and the
 * FormRequest that validates OpportunityWorkflowCriterion payloads — never an
 * ad-hoc list duplicated at either call site (anti-SQLi: `field`/
 * `existsTable()` values only ever come from THIS registry, never from raw
 * request input).
 *
 * `state_id`/`source_id` are direct Opportunity columns; `business_function_id`/
 * `product_category_id` match against ANY row of the opportunity's
 * `productLines()` collection (spec 0040 amendment rev.3), not a column on
 * `opportunities` itself.
 *
 * AMENDMENT 2026-07-27 (D5-D11): the allow-list is extended with every active
 * CustomFieldDefinition of entity_type `opportunities` and type `relation` —
 * the only custom field type whose value is an id compatible with
 * `opportunity_workflow_criteria.value_id`. A custom field's key is D5's
 * namespaced form (`custom.<key>`, App\CustomFields\CustomFieldProvider::
 * KEY_PREFIX); its target table is ALWAYS resolved through
 * CustomFieldEntityRegistry::modelClassFor() (D11), never from request
 * input. This is why the class stopped being a static-only utility: it now
 * depends on CustomFieldProvider (the definitions, already memoized
 * per-request — see its own docblock) and CustomFieldEntityRegistry (the
 * entity_type -> model resolution).
 */
final class CriterionFieldRegistry
{
    private const string ENTITY_TYPE = 'opportunities';

    private const string CUSTOM_FIELD_TYPE = 'relation';

    /**
     * @var array<string, array{for_select_resource: string, table: string, multi_valued: bool, from_product_lines: bool}>
     */
    private const array NATIVE_FIELDS = [
        'state_id' => [
            'for_select_resource' => 'states',
            'table' => 'states',
            'multi_valued' => false,
            'from_product_lines' => false,
        ],
        'source_id' => [
            'for_select_resource' => 'sources',
            'table' => 'sources',
            'multi_valued' => false,
            'from_product_lines' => false,
        ],
        'business_function_id' => [
            'for_select_resource' => 'business-functions',
            'table' => 'business_functions',
            'multi_valued' => true,
            'from_product_lines' => true,
        ],
        'product_category_id' => [
            'for_select_resource' => 'product-categories',
            'table' => 'product_categories',
            'multi_valued' => true,
            'from_product_lines' => true,
        ],
    ];

    /**
     * Merged native+custom field metadata, built at most once per instance
     * (CustomFieldProvider's own per-request memoization already makes a
     * second build cheap, but there is no reason to redo the merge/filter
     * work more than once here either).
     *
     * @var array<string, array{source: 'native'|'custom', table: string, label: string, label_column: string, for_select_resource: string, multi_valued: bool, from_product_lines: bool, custom_key: ?string}>|null
     */
    private ?array $fields = null;

    public function __construct(
        private readonly CustomFieldProvider $customFieldProvider,
        private readonly CustomFieldEntityRegistry $entityRegistry,
        private readonly CustomFieldRelationLabelResolver $relationLabelResolver,
    ) {}

    /**
     * The allow-list, shaped for GET /api/opportunity-workflows/criterion-fields
     * (AC-022/AC-027): field, label, source, for-select resource, multi_valued.
     * Native fields first (registry order, unchanged); custom fields after,
     * ordered by sort_order then id (D-amendment).
     *
     * @return array<int, array{field: string, label: string, source: 'native'|'custom', for_select_resource: string, multi_valued: bool}>
     */
    public function allowedFields(): array
    {
        return array_map(
            static fn (string $field, array $definition): array => [
                'field' => $field,
                'label' => $definition['label'],
                'source' => $definition['source'],
                'for_select_resource' => $definition['for_select_resource'],
                'multi_valued' => $definition['multi_valued'],
            ],
            array_keys($this->fields()),
            $this->fields(),
        );
    }

    public function isAllowed(string $field): bool
    {
        return array_key_exists($field, $this->fields());
    }

    /**
     * The DB table backing `value_id` for $field, for a `Rule::exists()`
     * check — never accepts raw input, only an already-allow-listed $field.
     * For a custom field this is ALWAYS CustomFieldEntityRegistry::
     * modelClassFor()->getTable() (D11), resolved once in fields().
     */
    public function existsTable(string $field): string
    {
        return ($this->fields()[$field] ?? throw $this->unknownField($field))['table'];
    }

    /**
     * The target table's display column for $field's value (native fields
     * all use `name`, unchanged; a custom field's target may not — e.g.
     * `companies.denomination` — resolved via the same heuristic
     * CustomFieldRelationLabelResolver already uses for the Table grid,
     * never duplicated). Consumed by CriterionValueLabelResolver.
     */
    public function labelColumnFor(string $field): string
    {
        return ($this->fields()[$field] ?? throw $this->unknownField($field))['label_column'];
    }

    /**
     * The distinct value(s) $opportunity actually carries for $field
     * (AC-013/AC-031): a direct column read for `state_id`/`source_id`
     * (empty when null), the distinct, non-null values across every
     * `productLines()` row for `business_function_id`/`product_category_id`,
     * or — for a custom relation field — its (normalized to int[])
     * `custom_fields` value. Assumes `productLines`/`customFieldValueRow`
     * are already eager-loaded by the caller (no N+1 query here, AC-032).
     *
     * @return array<int, int>
     */
    public function opportunityValues(Opportunity $opportunity, string $field): array
    {
        $definition = $this->fields()[$field] ?? throw $this->unknownField($field);

        if ($definition['source'] === 'custom') {
            return $this->normalizeCustomValues($opportunity->custom_fields[$definition['custom_key']] ?? null);
        }

        if (! $definition['from_product_lines']) {
            $value = $opportunity->getAttribute($field);

            return $value === null ? [] : [(int) $value];
        }

        return $opportunity->productLines
            ->pluck($field)
            ->filter()
            ->unique()
            ->values()
            ->map(static fn (mixed $value): int => (int) $value)
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function normalizeCustomValues(mixed $stored): array
    {
        if ($stored === null || $stored === []) {
            return [];
        }

        $raw = is_array($stored) ? $stored : [$stored];

        return collect($raw)
            ->filter(static fn (mixed $value): bool => $value !== null && $value !== '')
            ->map(static fn (mixed $value): int => (int) $value)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, array{source: 'native'|'custom', table: string, label: string, label_column: string, for_select_resource: string, multi_valued: bool, from_product_lines: bool, custom_key: ?string}>
     */
    private function fields(): array
    {
        if ($this->fields !== null) {
            return $this->fields;
        }

        $fields = [];

        foreach (self::NATIVE_FIELDS as $field => $definition) {
            $fields[$field] = [
                'source' => 'native',
                'table' => $definition['table'],
                'label' => "opportunityWorkflows.criterionFields.{$field}",
                'label_column' => 'name',
                'for_select_resource' => $definition['for_select_resource'],
                'multi_valued' => $definition['multi_valued'],
                'from_product_lines' => $definition['from_product_lines'],
                'custom_key' => null,
            ];
        }

        foreach ($this->customFieldRelationDefinitions() as $definition) {
            $entry = $this->customFieldEntry($definition);

            if ($entry === null) {
                continue; // D8: excluded, as if the definition did not exist.
            }

            $fields[$this->customFieldProvider->namespacedKey($definition->key)] = $entry;
        }

        return $this->fields = $fields;
    }

    /**
     * Active `relation` definitions of the `opportunities` entity_type,
     * ordered by sort_order then id (D-amendment) — CustomFieldProvider
     * already filters is_active/entity_type and orders by sort_order; the id
     * tie-break is added here since ties are otherwise insertion-order,
     * which is not a documented guarantee.
     *
     * @return array<int, CustomFieldDefinition>
     */
    private function customFieldRelationDefinitions(): array
    {
        return $this->customFieldProvider->definitionsFor(self::ENTITY_TYPE)
            ->filter(static fn (CustomFieldDefinition $definition): bool => $definition->type === self::CUSTOM_FIELD_TYPE)
            ->sortBy(static fn (CustomFieldDefinition $definition): string => sprintf('%020d-%020d', $definition->sort_order, $definition->id))
            ->values()
            ->all();
    }

    /**
     * @return array{source: 'custom', table: string, label: string, label_column: string, for_select_resource: string, multi_valued: bool, from_product_lines: bool, custom_key: string}|null
     */
    private function customFieldEntry(CustomFieldDefinition $definition): ?array
    {
        $target = $definition->relation_target ?? [];
        $entityType = $target['entity_type'] ?? null;
        $forSelectResource = $target['for_select_resource'] ?? null;

        if (! is_string($entityType) || $entityType === '' || ! is_string($forSelectResource) || $forSelectResource === '') {
            return null; // D8
        }

        $modelClass = $this->entityRegistry->modelClassFor($entityType);

        if ($modelClass === null) {
            return null; // D8
        }

        return [
            'source' => 'custom',
            'table' => (new $modelClass)->getTable(),
            'label' => $definition->label,
            'label_column' => $this->relationLabelResolver->displayColumnFor($modelClass),
            'for_select_resource' => $forSelectResource,
            'multi_valued' => ($target['cardinality'] ?? 'one') === 'many',
            'from_product_lines' => false,
            'custom_key' => $definition->key,
        ];
    }

    private function unknownField(string $field): InvalidArgumentException
    {
        return new InvalidArgumentException("Unknown criterion field [{$field}].");
    }
}
