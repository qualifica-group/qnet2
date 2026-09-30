<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\CustomFields\CustomFieldEntityRegistry;
use App\CustomFields\Table\TableFieldConfigValidator;
use Illuminate\Contracts\Validation\Validator;

/**
 * Shared cross-field validation for a field-type-driven definition — the
 * custom field definitions (spec 0021) AND the product attributes (spec
 * 0017), aligned onto the same `type`/`options`/`relation_target` request
 * shape (App\CustomFields\FieldTypeRegistry is the single source of truth
 * for the allowed `type` values in both families): an ENUM type requires at
 * least one option with unique values; a RELATION type requires a valid
 * relation_target (a custom-fieldable entity_type, a one|many cardinality, a
 * non-empty for_select_resource).
 *
 * `fieldTypeDefinitionType()`/`shouldValidateOptions()`/
 * `shouldValidateRelationTarget()` are overridden by a PARTIAL (PATCH)
 * request to fall back to the persisted value and to skip a check the
 * request did not submit at all (the Service enforces the persisted-value
 * fallback instead); a CREATE request uses the defaults below unchanged.
 */
trait ValidatesFieldTypeDefinition
{
    /**
     * ENUM requires at least one option, each with a unique `value`.
     */
    protected function validateEnumOptions(Validator $validator): void
    {
        if ($this->fieldTypeDefinitionType() !== 'enum' || ! $this->shouldValidateOptions()) {
            return;
        }

        $options = $this->input('options', []);

        if (! is_array($options) || $options === []) {
            $validator->errors()->add('options', 'At least one option is required for an ENUM field.');

            return;
        }

        $values = array_column($options, 'value');

        if (count($values) !== count(array_unique($values))) {
            $validator->errors()->add('options', 'Option values must be unique.');
        }
    }

    /**
     * RELATION requires a valid relation_target: a custom-fieldable
     * entity_type, a one|many cardinality and a for_select_resource.
     */
    protected function validateRelationTarget(Validator $validator): void
    {
        if ($this->fieldTypeDefinitionType() !== 'relation' || ! $this->shouldValidateRelationTarget()) {
            return;
        }

        $target = $this->input('relation_target');

        if (! is_array($target)) {
            $validator->errors()->add('relation_target', 'A relation field requires a relation_target.');

            return;
        }

        $entityType = $target['entity_type'] ?? null;
        $cardinality = $target['cardinality'] ?? null;
        $forSelectResource = $target['for_select_resource'] ?? null;

        if (! is_string($entityType) || ! app(CustomFieldEntityRegistry::class)->isCustomFieldable($entityType)) {
            $validator->errors()->add('relation_target.entity_type', 'The relation target must be a custom-fieldable entity.');
        }

        if (! in_array($cardinality, ['one', 'many'], true)) {
            $validator->errors()->add('relation_target.cardinality', 'The relation cardinality must be one or many.');
        }

        if (! is_string($forSelectResource) || $forSelectResource === '') {
            $validator->errors()->add('relation_target.for_select_resource', 'The relation target requires a for_select_resource.');
        }
    }

    /**
     * TABLE requires a config that satisfies the TableFieldConfig contract
     * (spec 0180); errors are keyed by the contract's `config.*` paths.
     */
    protected function validateTableConfig(Validator $validator): void
    {
        if ($this->fieldTypeDefinitionType() !== 'table' || ! $this->shouldValidateTableConfig()) {
            return;
        }

        foreach ((new TableFieldConfigValidator)->validate($this->tableConfigInput()) as $key => $message) {
            $validator->errors()->add($key, $message);
        }
    }

    /**
     * A TABLE field cannot be indexed: its value is a JSON document, not a
     * scalar column. Custom field requests only (attributes have no index).
     */
    protected function rejectIndexedTable(Validator $validator): void
    {
        if ($this->fieldTypeDefinitionType() === 'table' && $this->indexedInput()) {
            $validator->errors()->add('is_indexed', 'A table field cannot be indexed.');
        }
    }

    /**
     * The `type` this request's submission would produce. A CREATE request
     * reads the raw input; a PARTIAL update overrides this to fall back to
     * the model's currently persisted `type` when not submitted.
     */
    protected function fieldTypeDefinitionType(): ?string
    {
        return $this->input('type');
    }

    /**
     * Whether `options` should be checked at all — always true for a CREATE
     * request; a PARTIAL update overrides this to `$this->has('options')`.
     */
    protected function shouldValidateOptions(): bool
    {
        return true;
    }

    /**
     * Whether `relation_target` should be checked at all — always true for a
     * CREATE request; a PARTIAL update overrides this to
     * `$this->has('relation_target')`.
     */
    protected function shouldValidateRelationTarget(): bool
    {
        return true;
    }

    /**
     * Whether the table `config` should be checked — always true for a
     * CREATE request; a PARTIAL update overrides this to fire when `config`
     * or `type` is submitted (the latter covers a type change to table).
     */
    protected function shouldValidateTableConfig(): bool
    {
        return true;
    }

    /**
     * The `config` the definition would end up with; a PARTIAL update
     * overrides this to fall back to the persisted config.
     */
    protected function tableConfigInput(): mixed
    {
        return $this->input('config');
    }

    /**
     * The `is_indexed` flag the definition would end up with; a PARTIAL
     * update overrides this to fall back to the persisted flag.
     */
    protected function indexedInput(): bool
    {
        return $this->boolean('is_indexed');
    }
}
