<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

use App\CustomFields\FieldTypeRegistry;
use App\CustomFields\Types\FieldTypeHandler;

/**
 * Builds the `attr.<code>` column shapes for one EFFECTIVE attribute row
 * (`App\Services\ProductCategories\CategoryHierarchy::effectiveAttributes()`
 * shape) for the `request-management` domain (spec 0064; restored by the user
 * directive 2026-08-31 on the OFFER's own `quotes.attribute_values`, the
 * storage spec 0084 D-1 moved "Informazioni aggiuntive" to — the column
 * SHAPES below are unchanged, only the record they read and write is).
 *
 * Two DISTINCT shapes, mirroring `App\Tables\CustomFields\CustomFieldColumnBuilder`:
 *  - `raw()` — the minimal declarative entry consumed internally by
 *    `TableCellUpdateService`/`CellValueValidator` (PATCH structural
 *    allow-list + a generic pre-check). Deliberately carries a SENTINEL
 *    `type` (never `enum`/`date`/etc.) and no `editor`/`relation`/`options`
 *    keys: those keys make `CellValueValidator` divert into an id-shaped
 *    branch (`editor: 'select'|'multiselect'` or a `relation` key means "the
 *    value is a related row's id") which is WRONG for e.g. an enum
 *    attribute's string code — the REAL, authoritative validation/
 *    normalization for every type already lives in
 *    `App\RequestManagement\AttributeValueValidator`/`AttributeValueNormalizer`
 *    (reused, never duplicated, via `RequestManagementService::updateWork()`
 *    -> `QuoteAttributeValueWriter`),
 *    so this shape only needs to pass the STRUCTURAL "is this column
 *    declared editable" gate and stay permissive for Step 5's generic
 *    pre-check.
 *  - `resolved()` — the FULL shape `GET /columns` emits, per the frozen
 *    spec 0064 mapping table (type/filterType/editor/options/badges/relation).
 *
 * The mapping table is this class' OWN, independent of
 * `FieldTypeHandler::columnType()/filterType()` (constraint: those ARE reused
 * for `applyFilter`/`applySort`/`distinctValues`, spec 0064 §M2) — the two
 * disagree for `date`/`datetime` (handler: text/text; contract: text|
 * datetime / date) and for `relation` cardinality (handler never
 * differentiates one/many), so the wire contract (AC-007) is the source of
 * truth for what the FRONTEND sees, while the handler stays the source of
 * truth for how a filter/sort/distinct actually reads the JSON column.
 */
final class AttributeColumnBuilder
{
    private const string ID_PREFIX = 'attr.';

    /**
     * The RequestManagementAuthorization field key EVERY `attr.<code>`
     * column writes through (spec 0064 §M4): a single field-permission gate
     * for the whole `attribute_values` column, never a per-code one. PUBLIC:
     * `TableCellUpdateService` remaps the submitted `column` to THIS value
     * (via the raw declaration's `editableField`) before ever calling
     * `updateCell()`, so `WritesAttributeCells::updateCell()` needs the same
     * constant to recognize its own writes and recover the real code from
     * the ambient request (see that trait's docblock).
     */
    public const string EDITABLE_FIELD = 'attribute_values';

    /**
     * Sentinel raw `type`, matched by none of `CellValueValidator::typeRules()`'s
     * arms — Step 5 falls through to its permissive `default => []`, deferring
     * every real check to `updateWork()`.
     */
    private const string RAW_TYPE_SENTINEL = 'attribute';

    public function __construct(private readonly FieldTypeRegistry $typeRegistry) {}

    /**
     * @param  array<string, mixed>  $attributeRow
     */
    public function id(array $attributeRow): string
    {
        return self::ID_PREFIX.$attributeRow['code'];
    }

    /**
     * The attribute `code` a column id addresses, or null when `$columnId`
     * is not an `attr.<code>` column.
     */
    public function codeFor(string $columnId): ?string
    {
        return str_starts_with($columnId, self::ID_PREFIX)
            ? substr($columnId, strlen(self::ID_PREFIX))
            : null;
    }

    /**
     * @param  array<string, mixed>  $attributeRow
     */
    public function handlerFor(array $attributeRow): FieldTypeHandler
    {
        return $this->typeRegistry->resolve((string) $attributeRow['type']);
    }

    /**
     * @param  array<string, mixed>  $attributeRow
     * @return array<string, mixed>
     */
    public function raw(array $attributeRow): array
    {
        return [
            'id' => $this->id($attributeRow),
            'label' => (string) $attributeRow['name'],
            'type' => self::RAW_TYPE_SENTINEL,
            'visible' => false,
            'sortable' => true,
            'filterable' => true,
            'editable' => true,
            'editableField' => self::EDITABLE_FIELD,
            'nullable' => true,
            'source' => 'attribute',
        ];
    }

    /**
     * @param  array<string, mixed>  $attributeRow
     */
    public function resolved(array $attributeRow, int $order, bool $editable): array
    {
        $mapping = $this->mapping($attributeRow);

        $column = [
            'id' => $this->id($attributeRow),
            'label' => (string) $attributeRow['name'],
            'type' => $mapping['type'],
            'visible' => true,
            'width' => null,
            'order' => $order,
            'sortable' => true,
            'filterable' => true,
            'filterType' => $mapping['filterType'],
            'hasFilterValues' => true,
            'editable' => $editable,
            'options' => $this->optionsFor($attributeRow),
            'source' => 'attribute',
        ];

        if ($mapping['editor'] !== null) {
            $column['editor'] = $mapping['editor'];
        }

        $badges = $this->optionsFor($attributeRow);

        if ($badges !== null) {
            $column['badges'] = $badges;
        }

        $relation = $this->relationFragment($attributeRow);

        if ($relation !== null) {
            $column['relation'] = $relation;
        }

        return $column;
    }

    /**
     * The (type, filterType, editor) triad per the frozen spec 0064 mapping
     * table.
     *
     * @param  array<string, mixed>  $attributeRow
     * @return array{type: string, filterType: string, editor: string|null}
     */
    private function mapping(array $attributeRow): array
    {
        return match ((string) $attributeRow['type']) {
            'text', 'textarea', 'email', 'url', 'color', 'time' => ['type' => 'text', 'filterType' => 'text', 'editor' => null],
            'integer', 'decimal' => ['type' => 'number', 'filterType' => 'number', 'editor' => null],
            'boolean' => ['type' => 'boolean', 'filterType' => 'set', 'editor' => null],
            'enum' => $this->isMultiEnum($attributeRow)
                ? ['type' => 'tags', 'filterType' => 'set', 'editor' => 'tags']
                : ['type' => 'enum', 'filterType' => 'set', 'editor' => 'select'],
            'relation' => $this->isManyRelation($attributeRow)
                ? ['type' => 'tags', 'filterType' => 'set', 'editor' => 'multiselect']
                : ['type' => 'text', 'filterType' => 'set', 'editor' => 'relation'],
            'date' => ['type' => 'text', 'filterType' => 'date', 'editor' => 'date'],
            'datetime' => ['type' => 'datetime', 'filterType' => 'date', 'editor' => 'datetime'],
            default => ['type' => 'text', 'filterType' => 'text', 'editor' => null],
        };
    }

    /**
     * @param  array<string, mixed>  $attributeRow
     * @return array<int, array{value: string, label: string, color: string|null}>|null
     */
    private function optionsFor(array $attributeRow): ?array
    {
        if ($attributeRow['type'] !== 'enum') {
            return null;
        }

        /** @var array<int, array<string, mixed>> $options */
        $options = $attributeRow['options'] ?? [];

        return array_map(static fn (array $option): array => [
            'value' => $option['value'],
            'label' => $option['label'],
            'color' => $option['color'] ?? null,
        ], $options);
    }

    /**
     * @param  array<string, mixed>  $attributeRow
     * @return array{resource: string}|null
     */
    private function relationFragment(array $attributeRow): ?array
    {
        if ($attributeRow['type'] !== 'relation') {
            return null;
        }

        $resource = $attributeRow['relation_target']['for_select_resource'] ?? null;

        return is_string($resource) && $resource !== '' ? ['resource' => $resource] : null;
    }

    /**
     * @param  array<string, mixed>  $attributeRow
     */
    private function isMultiEnum(array $attributeRow): bool
    {
        return ($attributeRow['config']['display'] ?? null) === 'multiselect';
    }

    /**
     * @param  array<string, mixed>  $attributeRow
     */
    private function isManyRelation(array $attributeRow): bool
    {
        return ($attributeRow['relation_target']['cardinality'] ?? 'one') === 'many';
    }
}
