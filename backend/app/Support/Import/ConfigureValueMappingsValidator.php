<?php

namespace App\Support\Import;

use App\Imports\ImportDefinition;
use App\Models\ImportRun;

/**
 * Configure-step checks for the two wizard extensions of spec 0348, run by
 * ConfigureImportRequest after its own structural checks: the `value_mappings`
 * payload (every distinct file value of a mapped value-mapping column needs a
 * decision) and the `attribute.<code>` mapping targets. Generic shape checks
 * live here; what an id or an attribute code MEANS is the definition's call.
 */
final class ConfigureValueMappingsValidator
{
    public const string ATTRIBUTE_TARGET_PREFIX = 'attribute.';

    public function __construct(private readonly ImportValueMappings $valueMappings) {}

    /**
     * @param  array<string, string>  $columnMapping
     * @param  array<string, mixed>  $globalConfig
     * @return array<string, array<int, string>> error key => messages
     */
    public function errors(ImportDefinition $definition, ImportRun $run, array $columnMapping, array $globalConfig, mixed $submitted): array
    {
        return array_merge_recursive(
            $this->attributeTargetErrors($definition, $columnMapping, $globalConfig),
            $this->valueMappingErrors($definition, $run, $columnMapping, $globalConfig, $submitted),
        );
    }

    /**
     * @param  array<string, string>  $columnMapping
     * @param  array<string, mixed>  $globalConfig
     * @return array<string, array<int, string>>
     */
    private function attributeTargetErrors(ImportDefinition $definition, array $columnMapping, array $globalConfig): array
    {
        $usesAttributes = array_filter(
            $columnMapping,
            static fn (mixed $target): bool => is_string($target) && str_starts_with($target, self::ATTRIBUTE_TARGET_PREFIX),
        ) !== [];

        return $definition->attributeTargets() !== null && $usesAttributes
            ? $definition->validateAttributeTargets($columnMapping, $globalConfig)
            : [];
    }

    /**
     * @param  array<string, string>  $columnMapping
     * @param  array<string, mixed>  $globalConfig
     * @return array<string, array<int, string>>
     */
    private function valueMappingErrors(ImportDefinition $definition, ImportRun $run, array $columnMapping, array $globalConfig, mixed $submitted): array
    {
        // A domain with no value-mapping step ignores the key entirely.
        if ($definition->valueMappingFields() === []) {
            return [];
        }

        $shapeErrors = $this->shapeErrors($definition, $submitted);

        if ($shapeErrors !== []) {
            return $shapeErrors;
        }

        $mappedColumns = $this->valueMappings->mappedColumns($definition, $run, $columnMapping);

        return $mappedColumns === []
            ? []
            : $definition->validateValueMappings($mappedColumns, is_array($submitted) ? $submitted : [], $globalConfig);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function shapeErrors(ImportDefinition $definition, mixed $submitted): array
    {
        if ($submitted === null) {
            return [];
        }

        if (! is_array($submitted)) {
            return ['value_mappings' => ['The value_mappings field must be an object.']];
        }

        $errors = [];

        foreach ($submitted as $field => $decisions) {
            if (! in_array($field, $definition->valueMappingFields(), true) || ! is_array($decisions)) {
                $errors["value_mappings.{$field}"][] = "The value mapping [{$field}] is not allowed.";

                continue;
            }

            foreach ($decisions as $decision) {
                if (! is_array($decision)) {
                    $errors["value_mappings.{$field}"][] = "Every decision of [{$field}] must be an object.";

                    break;
                }
            }
        }

        return $errors;
    }
}
