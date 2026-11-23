<?php

declare(strict_types=1);

namespace App\CustomFields\Types;

use App\CustomFields\Types\Concerns\AppliesSetFilter;
use App\CustomFields\Types\Concerns\DerivesRequiredRule;
use App\CustomFields\Types\Concerns\OrdersByJsonPath;
use App\CustomFields\Types\Concerns\ResolvesDistinctJsonValues;
use App\CustomFields\Types\Concerns\ResolvesJsonColumn;
use App\CustomFields\Types\Concerns\ValidatesEachElement;
use App\Models\CustomFieldDefinition;
use Illuminate\Validation\Rule;

/**
 * Discrete-choice field backed by the definition's `options()` (spec 0021
 * MVP). `config.display`: select|radio|badge (single value) or
 * multiselect/checklist (array, EnumDisplay). A single value is stored as a
 * plain string; a multi value as a JSON array of strings — both share the SAME set filter/sort/distinct behaviour
 * (Concerns\AppliesSetFilter / ResolvesDistinctJsonValues handle either
 * shape transparently).
 */
class EnumFieldType implements FieldTypeHandler
{
    use AppliesSetFilter;
    use DerivesRequiredRule;
    use OrdersByJsonPath;
    use ResolvesDistinctJsonValues;
    use ResolvesJsonColumn;
    use ValidatesEachElement;

    public function key(): string
    {
        return 'enum';
    }

    public function storageType(): string
    {
        return 'string';
    }

    public function columnType(): string
    {
        return 'enum';
    }

    public function filterType(): string
    {
        return 'set';
    }

    public function validationRules(CustomFieldDefinition $definition): array
    {
        $options = $definition->options->pluck('value')->all();
        $base = $this->requiredOrNullable($definition);

        if ($this->isMulti($definition)) {
            $rules = [$base, 'array'];

            if ($options !== []) {
                $rules[] = $this->eachElementRule(static fn (mixed $item): bool => in_array($item, $options, true));
            }

            return $rules;
        }

        $rules = [$base];

        if ($options !== []) {
            $rules[] = Rule::in($options);
        }

        return $rules;
    }

    public function normalizeForStore(mixed $value, CustomFieldDefinition $definition): mixed
    {
        if ($value === null) {
            return null;
        }

        return $this->isMulti($definition)
            ? array_values(array_map(static fn (mixed $item): string => (string) $item, (array) $value))
            : (string) $value;
    }

    public function resolveForRead(mixed $stored, CustomFieldDefinition $definition): mixed
    {
        return $stored;
    }

    public function toMeta(CustomFieldDefinition $definition): array
    {
        return [
            'type' => $this->key(),
            'config' => $definition->config ?? [],
            'options' => $definition->options->map(static fn ($option): array => [
                'value' => $option->value,
                'label' => $option->label,
                'color' => $option->color,
                'icon' => $option->icon,
            ])->all(),
        ];
    }

    private function isMulti(CustomFieldDefinition $definition): bool
    {
        return EnumDisplay::isMultiValue($definition->config);
    }
}
