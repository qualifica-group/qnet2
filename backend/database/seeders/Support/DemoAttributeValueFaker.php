<?php

namespace Database\Seeders\Support;

use App\CustomFields\CustomFieldEntityRegistry;
use Faker\Generator;

/**
 * A demo value per attribute type that passes AttributeValueValidator: a
 * relation points at a real row of its target entity, a multiselect enum is
 * an array, a table starts empty — a plain sentence would fail every one of
 * them and abort the whole demo chain.
 */
final class DemoAttributeValueFaker
{
    private const string MULTISELECT_DISPLAY = 'multiselect';

    private const int DEFAULT_MIN = 1;

    private const int INTEGER_SPAN = 99;

    private const int DECIMAL_SPAN = 999;

    /** @var array<string, int|null> first id per relation entity type, memoized across rows */
    private array $relationIds = [];

    public function __construct(private readonly CustomFieldEntityRegistry $entityRegistry) {}

    /**
     * @param  array<string, mixed>  $attribute  a CategoryHierarchy::effectiveAttributes() row
     */
    public function fake(Generator $faker, array $attribute): mixed
    {
        return match ($attribute['type']) {
            'integer' => $faker->numberBetween(...array_map(intval(...), $this->bounds($attribute, self::INTEGER_SPAN))),
            'decimal' => $faker->randomFloat(2, ...$this->bounds($attribute, self::DECIMAL_SPAN)),
            'boolean' => $faker->boolean(),
            'date' => $faker->date('Y-m-d'),
            'datetime' => $faker->date('Y-m-d\TH:i'),
            'time' => $faker->time('H:i'),
            'email' => $faker->safeEmail(),
            'url' => $faker->url(),
            'color' => $faker->hexColor(),
            'enum' => $this->enumValue($attribute),
            'relation' => $this->relationValue($attribute),
            'table' => [],
            default => $faker->sentence(6),
        };
    }

    /**
     * The attribute's own `config` min/max (the validator enforces them), the
     * demo range around them where unset.
     *
     * @param  array<string, mixed>  $attribute
     * @return array{0: int|float, 1: int|float}
     */
    private function bounds(array $attribute, int $span): array
    {
        $max = $attribute['config']['max'] ?? null;
        $min = $attribute['config']['min'] ?? min(self::DEFAULT_MIN, $max ?? self::DEFAULT_MIN);

        return [$min, $max ?? $min + $span];
    }

    /**
     * @param  array<string, mixed>  $attribute
     */
    private function enumValue(array $attribute): mixed
    {
        $first = $attribute['options'][0]['value'] ?? null;

        if (($attribute['config']['display'] ?? null) !== self::MULTISELECT_DISPLAY) {
            return $first;
        }

        return $first === null ? [] : [$first];
    }

    /**
     * @param  array<string, mixed>  $attribute
     */
    private function relationValue(array $attribute): mixed
    {
        $target = $attribute['relation_target'] ?? [];
        $id = $this->firstIdOf($target['entity_type'] ?? null);

        if (($target['cardinality'] ?? 'one') === 'many') {
            return $id === null ? [] : [$id];
        }

        return $id;
    }

    private function firstIdOf(?string $entityType): ?int
    {
        if ($entityType === null) {
            return null;
        }

        if (! array_key_exists($entityType, $this->relationIds)) {
            $modelClass = $this->entityRegistry->modelClassFor($entityType);
            $this->relationIds[$entityType] = $modelClass === null ? null : $modelClass::query()->orderBy('id')->value('id');
        }

        return $this->relationIds[$entityType];
    }
}
