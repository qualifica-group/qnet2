<?php

declare(strict_types=1);

namespace App\Tables\CustomFields;

use App\CustomFields\CustomFieldProvider;
use App\CustomFields\FieldTypeRegistry;
use App\CustomFields\Types\FieldTypeHandler;
use App\Models\CustomFieldDefinition;

/**
 * Builds the `ColumnDefinition`-shaped arrays for one custom field, both the
 * raw declarative shape (`TableDefinition::columns()`) and the fully resolved
 * shape (`TableDefinition::resolveConfig()['columns']`) — split out of
 * `CustomFieldAwareTableDefinition` to keep it within the file-size budget
 * (engineering.md §6).
 */
final class CustomFieldColumnBuilder
{
    public function __construct(
        private readonly CustomFieldProvider $provider,
        private readonly FieldTypeRegistry $typeRegistry,
    ) {}

    /**
     * The namespaced column id (`custom.<key>`) for a definition.
     */
    public function id(CustomFieldDefinition $definition): string
    {
        return $this->provider->namespacedKey($definition->key);
    }

    public function handlerFor(CustomFieldDefinition $definition): FieldTypeHandler
    {
        return $this->typeRegistry->resolve($definition->type);
    }

    /**
     * Raw declarative column (`TableDefinition::columns()` shape).
     *
     * @return array<string, mixed>
     */
    public function raw(CustomFieldDefinition $definition): array
    {
        $handler = $this->handlerFor($definition);
        $filterType = $handler->filterType();

        return [
            'id' => $this->id($definition),
            'label' => $definition->label,
            'type' => $handler->columnType(),
            'visible' => false,
            'sortable' => true,
            'filterable' => true,
            'filterType' => $filterType,
            'searchable' => $filterType === 'text',
            'source' => 'custom',
            ...$this->enumFragment($handler, $definition),
        ];
    }

    /**
     * Fully resolved column (`TableDefinition::resolveConfig()['columns']`
     * shape), `$order` placed after every native column.
     *
     * @return array<string, mixed>
     */
    public function resolved(CustomFieldDefinition $definition, int $order): array
    {
        $handler = $this->handlerFor($definition);
        $filterType = $handler->filterType();
        $enum = $this->enumFragment($handler, $definition);

        $column = [
            'id' => $this->id($definition),
            'label' => $definition->label,
            'type' => $handler->columnType(),
            'visible' => false,
            'width' => null,
            'order' => $order,
            'sortable' => true,
            'filterable' => true,
            'filterType' => $filterType,
            'hasFilterValues' => true,
            // Inline cell-editing (spec 0053): custom fields are not
            // eligible for inline edit in this round — always false, same
            // contract shape as every native column.
            'editable' => false,
            'options' => $enum['options'] ?? null,
            'source' => 'custom',
        ];

        if (isset($enum['badges'])) {
            $column['badges'] = $enum['badges'];
        }

        if ($handler->columnType() === 'table') {
            $column['table'] = $handler->toMeta($definition)['config'] ?? [];
        }

        return $column;
    }

    /**
     * `options` (scalar values) + `badges` (label/color/icon) for an
     * enum-columnType field, sourced from the handler's own `toMeta()` —
     * omitted entirely for every other column type.
     *
     * @return array{options?: array<int, scalar>, badges?: array<int, array<string, mixed>>}
     */
    private function enumFragment(FieldTypeHandler $handler, CustomFieldDefinition $definition): array
    {
        if ($handler->columnType() !== 'enum') {
            return [];
        }

        /** @var array<int, array<string, mixed>> $options */
        $options = $handler->toMeta($definition)['options'] ?? [];

        return [
            'options' => array_column($options, 'value'),
            'badges' => $options,
        ];
    }
}
