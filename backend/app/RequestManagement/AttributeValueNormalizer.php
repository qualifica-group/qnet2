<?php

declare(strict_types=1);

namespace App\RequestManagement;

use App\CustomFields\Table\TableFieldSchema;
use Illuminate\Support\Collection;

/**
 * Coerces an already-VALIDATED `attribute_values` map {code => value} into
 * its JSON-storage shape (spec 0049, D-4), mirroring the CustomFields
 * FieldTypeHandlers' `normalizeForStore()` semantics per type — against
 * ApplicableAttribute, not CustomFieldDefinition, which those handlers
 * require (not reusable here, see AttributeValueValidator's docblock).
 *
 * Only ever run AFTER AttributeValueValidator has passed: a value reaching
 * here is trusted well-formed for its type. Unknown codes (should never
 * happen post-validation) pass through untouched rather than being dropped
 * silently.
 */
final class AttributeValueNormalizer
{
    /**
     * @param  Collection<int, ApplicableAttribute>  $applicableAttributes
     * @param  array<string, mixed>  $values  validated {code => value}
     * @return array<string, mixed> normalized, ready to merge into
     *                              `opportunities.attribute_values`
     */
    public function normalize(Collection $applicableAttributes, array $values): array
    {
        $indexed = $applicableAttributes->keyBy('code');
        $normalized = [];

        foreach ($values as $code => $value) {
            /** @var ApplicableAttribute|null $attribute */
            $attribute = $indexed->get($code);
            $normalized[$code] = $attribute === null ? $value : $this->normalizeValue($value, $attribute);
        }

        return $normalized;
    }

    private function normalizeValue(mixed $value, ApplicableAttribute $attribute): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($attribute->type) {
            'integer' => (int) round((float) $value),
            'decimal' => (float) $value,
            'boolean' => (bool) $value,
            'enum' => $this->normalizeEnum($value, $attribute),
            'relation' => $this->normalizeRelation($value, $attribute),
            'table' => TableFieldSchema::fromConfig($attribute->config, $attribute->isRequired)->normalize($value),
            'text', 'textarea', 'color', 'date', 'datetime', 'time', 'email', 'url' => trim((string) $value),
            default => $value,
        };
    }

    private function normalizeEnum(mixed $value, ApplicableAttribute $attribute): mixed
    {
        return $this->isMultiEnum($attribute)
            ? array_values(array_map(static fn (mixed $item): string => (string) $item, (array) $value))
            : (string) $value;
    }

    private function isMultiEnum(ApplicableAttribute $attribute): bool
    {
        return ($attribute->config['display'] ?? null) === 'multiselect';
    }

    private function normalizeRelation(mixed $value, ApplicableAttribute $attribute): mixed
    {
        $isMany = ($attribute->relationTarget['cardinality'] ?? 'one') === 'many';

        return $isMany
            ? array_values(array_map(static fn (mixed $id): int => (int) $id, (array) $value))
            : (int) $value;
    }
}
