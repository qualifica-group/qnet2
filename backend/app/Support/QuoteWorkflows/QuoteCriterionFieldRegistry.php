<?php

declare(strict_types=1);

namespace App\Support\QuoteWorkflows;

use App\CustomFields\CustomFieldEntityRegistry;
use App\CustomFields\CustomFieldProvider;
use App\CustomFields\CustomFieldRelationLabelResolver;
use App\Models\CustomFieldDefinition;
use App\Models\Quote;
use InvalidArgumentException;

/**
 * Centralized allow-list of the "criterion fields" a workflow may be matched
 * on (spec 0047, moved onto the Offerta by spec 0083 D-7). The single source
 * of truth consumed by both the resolver
 * (App\Services\Quotes\QuoteWorkflowResolver) and the FormRequest that
 * validates QuoteWorkflowCriterion payloads — never an ad-hoc list
 * duplicated at either call site (anti-SQLi: `field`/`existsTable()` values
 * only ever come from THIS registry, never from raw request input).
 *
 * D-7: `product_category_id`/`business_function_id` are resolved from the
 * Quote's own REVENUE offer lines (`Quote::offerLines`, never `cost_lines`),
 * while `source_id` and every custom `relation` field have NO counterpart on
 * the Quote at all: they are resolved by INHERITANCE from the parent
 * Opportunity (`quote.opportunity`). `inherited` in the catalogue shape
 * (AC-016) marks exactly this split, so the client can tell the two apart.
 *
 * The former `state_id` (Regione) criterion is GONE with the Opportunity's
 * own Regione column (user directive 2026-09-01).
 *
 * Custom field DEFINITIONS still come from the `opportunities` entity_type
 * (D-7: "il set dei criteri configurabili resta identico a oggi") — only
 * their VALUE resolution changed, reading through the parent Opportunity's
 * `custom_fields` instead of the record's own.
 */
final class QuoteCriterionFieldRegistry
{
    private const string ENTITY_TYPE = 'opportunities';

    private const string CUSTOM_FIELD_TYPE = 'relation';

    /**
     * @var array<string, array{for_select_resource: string, table: string, multi_valued: bool, inherited: bool}>
     */
    private const array NATIVE_FIELDS = [
        'source_id' => [
            'for_select_resource' => 'sources',
            'table' => 'sources',
            'multi_valued' => false,
            'inherited' => true,
        ],
        'business_function_id' => [
            'for_select_resource' => 'business-functions',
            'table' => 'business_functions',
            'multi_valued' => true,
            'inherited' => false,
        ],
        'product_category_id' => [
            'for_select_resource' => 'product-categories',
            'table' => 'product_categories',
            'multi_valued' => true,
            'inherited' => false,
        ],
    ];

    /**
     * Merged native+custom field metadata, built at most once per instance
     * (CustomFieldProvider's own per-request memoization already makes a
     * second build cheap, but there is no reason to redo the merge/filter
     * work more than once here either).
     *
     * @var array<string, array{source: 'native'|'custom', table: string, label: string, label_column: string, for_select_resource: string, multi_valued: bool, inherited: bool, custom_key: ?string}>|null
     */
    private ?array $fields = null;

    public function __construct(
        private readonly CustomFieldProvider $customFieldProvider,
        private readonly CustomFieldEntityRegistry $entityRegistry,
        private readonly CustomFieldRelationLabelResolver $relationLabelResolver,
    ) {}

    /**
     * The allow-list, shaped for GET /api/quote-workflows/criterion-fields
     * (AC-016): field, label, source, for-select resource, multi_valued,
     * inherited. Native fields first (registry order, unchanged); custom
     * fields after, ordered by sort_order then id.
     *
     * @return array<int, array{field: string, label: string, source: 'native'|'custom', for_select_resource: string, multi_valued: bool, inherited: bool}>
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
                'inherited' => $definition['inherited'],
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
     * modelClassFor()->getTable(), resolved once in fields().
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
     * never duplicated). Consumed by QuoteCriterionValueLabelResolver.
     */
    public function labelColumnFor(string $field): string
    {
        return ($this->fields()[$field] ?? throw $this->unknownField($field))['label_column'];
    }

    /**
     * The distinct value(s) $quote actually carries for $field (AC-010/011/
     * 012): the resolving Opportunity's own value for an INHERITED field
     * (empty when null or the opportunity is missing), the distinct,
     * non-null values across every `offerLines()` row's product for
     * `business_function_id`/`product_category_id`, or — for a custom
     * relation field (also inherited, D-7) — the Opportunity's normalized
     * (int[]) `custom_fields` value. Assumes `offerLines.product.category`/
     * `opportunity.customFieldValueRow` are already eager-loaded by the
     * caller (no N+1 query here).
     *
     * @return array<int, int>
     */
    public function quoteValues(Quote $quote, string $field): array
    {
        $definition = $this->fields()[$field] ?? throw $this->unknownField($field);

        if ($definition['source'] === 'custom') {
            return $this->normalizeCustomValues($quote->opportunity?->custom_fields[$definition['custom_key']] ?? null);
        }

        if ($definition['inherited']) {
            $value = $quote->opportunity?->getAttribute($field);

            return $value === null ? [] : [(int) $value];
        }

        return $this->offerLineValues($quote, $field);
    }

    /**
     * @return array<int, int>
     */
    private function offerLineValues(Quote $quote, string $field): array
    {
        return $quote->offerLines
            ->map(static fn ($line): mixed => $field === 'product_category_id'
                ? $line->product?->category_id
                : $line->product?->category?->business_function_id)
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
     * @return array<string, array{source: 'native'|'custom', table: string, label: string, label_column: string, for_select_resource: string, multi_valued: bool, inherited: bool, custom_key: ?string}>
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
                'label' => "quoteWorkflows.criterionFields.{$field}",
                'label_column' => 'name',
                'for_select_resource' => $definition['for_select_resource'],
                'multi_valued' => $definition['multi_valued'],
                'inherited' => $definition['inherited'],
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
     * Active `relation` definitions of the `opportunities` entity_type
     * (D-7: the custom-field catalogue stays anchored there, only its
     * VALUE resolution is inherited by the Quote), ordered by sort_order
     * then id — CustomFieldProvider already filters is_active/entity_type
     * and orders by sort_order; the id tie-break is added here since ties
     * are otherwise insertion-order, which is not a documented guarantee.
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
     * @return array{source: 'custom', table: string, label: string, label_column: string, for_select_resource: string, multi_valued: bool, inherited: bool, custom_key: string}|null
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
            // D-7: a custom field has no counterpart on the Quote at all —
            // its value is always read through the parent Opportunity.
            'inherited' => true,
            'custom_key' => $definition->key,
        ];
    }

    private function unknownField(string $field): InvalidArgumentException
    {
        return new InvalidArgumentException("Unknown criterion field [{$field}].");
    }
}
