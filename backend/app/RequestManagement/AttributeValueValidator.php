<?php

declare(strict_types=1);

namespace App\RequestManagement;

use App\CustomFields\CustomFieldEntityRegistry;
use App\CustomFields\Table\TableFieldSchema;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validates a submitted `attribute_values` map {code => value} against an
 * opportunity's applicable-attribute set (spec 0049, D-4/AC-040/041/042).
 *
 * Attributes have NO value store (EAV removed, commit e5c31dc) and the
 * CustomFields FieldTypeHandlers are coupled to CustomFieldDefinition (their
 * signatures take a CustomFieldDefinition) — NOT reusable here. This class
 * mirrors their per-type semantics directly against ApplicableAttribute's own
 * `config`/`relation_target`, sharing only the 13-type vocabulary
 * (App\CustomFields\FieldTypeRegistry's keys) and CustomFieldEntityRegistry
 * (an entity-agnostic registry, not CustomFieldDefinition-coupled) to resolve
 * a `relation` attribute's target table.
 *
 * Semantics implemented (AC-040/041/042):
 *  - a code NOT in the applicable set fails immediately, keyed
 *    `attribute_values.<code>`;
 *  - every SUBMITTED, applicable code is validated per its `type`;
 *  - a submitted code whose descriptor has pivot `is_required` fails when its
 *    value is empty/null (Laravel's native `required` semantics);
 *  - an applicable code simply ABSENT from the payload is never checked here
 *    — sparse PATCH semantics (spec 0049 data_contract): an unset key keeps
 *    whatever is already persisted, already validated when it was written.
 */
final class AttributeValueValidator
{
    public function __construct(private readonly CustomFieldEntityRegistry $entityRegistry) {}

    /**
     * @param  Collection<int, ApplicableAttribute>  $applicableAttributes
     * @param  array<string, mixed>  $values  submitted {code => value}
     * @return array<string, mixed> the same map, once every key has passed
     *
     * @throws ValidationException
     */
    public function validate(Collection $applicableAttributes, array $values): array
    {
        $indexed = $applicableAttributes->keyBy('code');

        // Step 1: fail immediately on any code outside the applicable set
        $this->assertKnownCodes($indexed, $values);

        // Step 2: per-type + required rules for the (now known) submitted codes
        ValidatorFacade::make(['attribute_values' => $values], $this->rules($indexed, $values))->validate();

        return $values;
    }

    /**
     * @param  Collection<string, ApplicableAttribute>  $indexed
     * @param  array<string, mixed>  $values
     */
    private function assertKnownCodes(Collection $indexed, array $values): void
    {
        $unknown = array_diff(array_keys($values), $indexed->keys()->all());

        if ($unknown === []) {
            return;
        }

        $messages = [];

        foreach ($unknown as $code) {
            $messages["attribute_values.{$code}"] = ["The \"{$code}\" attribute is not part of the applicable attribute set."];
        }

        throw ValidationException::withMessages($messages);
    }

    /**
     * @param  Collection<string, ApplicableAttribute>  $indexed
     * @param  array<string, mixed>  $values
     * @return array<string, array<int, mixed>>
     */
    private function rules(Collection $indexed, array $values): array
    {
        $rules = [];

        foreach (array_keys($values) as $code) {
            /** @var ApplicableAttribute $attribute */
            $attribute = $indexed->get($code);

            $rules["attribute_values.{$code}"] = [
                $attribute->isRequired ? 'required' : 'nullable',
                ...$this->typeRules($attribute),
            ];

            if ($attribute->type === 'table') {
                $rules = [...$rules, ...$this->nestedTableRules($attribute)];
            }
        }

        return $rules;
    }

    /**
     * The table cells' own rules (spec 0180), keyed under the attribute so a
     * failure points at `attribute_values.<code>.rows.N.<col>`.
     *
     * @return array<string, array<int, mixed>>
     */
    private function nestedTableRules(ApplicableAttribute $attribute): array
    {
        $nested = [];

        foreach (TableFieldSchema::fromConfig($attribute->config, $attribute->isRequired)->rules() as $key => $rules) {
            $nested["attribute_values.{$attribute->code}.{$key}"] = $rules;
        }

        return $nested;
    }

    /**
     * @return array<int, mixed>
     */
    private function typeRules(ApplicableAttribute $attribute): array
    {
        return match ($attribute->type) {
            'text', 'textarea' => $this->stringRules($attribute),
            'color' => ['string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'integer' => $this->numericRules($attribute, 'integer'),
            'decimal' => $this->numericRules($attribute, 'numeric'),
            'boolean' => ['boolean'],
            'enum' => $this->enumRules($attribute),
            'relation' => $this->relationRules($attribute),
            'date' => ['string', 'date_format:Y-m-d'],
            'datetime' => ['string', 'date_format:Y-m-d\TH:i,Y-m-d\TH:i:s'],
            'time' => ['string', 'date_format:H:i,H:i:s'],
            'email' => ['string', 'email', 'max:191'],
            'url' => ['string', 'url', 'max:2048'],
            'table' => ['array'],
            default => ['string'],
        };
    }

    /**
     * @return array<int, mixed>
     */
    private function stringRules(ApplicableAttribute $attribute): array
    {
        $config = $attribute->config;
        $rules = ['string'];

        if (isset($config['minLength'])) {
            $rules[] = "min:{$config['minLength']}";
        }

        if (isset($config['maxLength'])) {
            $rules[] = "max:{$config['maxLength']}";
        }

        if (! empty($config['regex'])) {
            $rules[] = "regex:{$config['regex']}";
        }

        return $rules;
    }

    /**
     * @return array<int, mixed>
     */
    private function numericRules(ApplicableAttribute $attribute, string $baseRule): array
    {
        $config = $attribute->config;
        $rules = [$baseRule];

        if (isset($config['min'])) {
            $rules[] = "min:{$config['min']}";
        }

        if (isset($config['max'])) {
            $rules[] = "max:{$config['max']}";
        }

        return $rules;
    }

    /**
     * @return array<int, mixed>
     */
    private function enumRules(ApplicableAttribute $attribute): array
    {
        $options = array_column($attribute->options, 'value');
        $isMulti = ($attribute->config['display'] ?? null) === 'multiselect';

        if ($isMulti) {
            $rules = ['array'];

            if ($options !== []) {
                $rules[] = $this->eachInRule($options);
            }

            return $rules;
        }

        return $options === [] ? [] : [Rule::in($options)];
    }

    /**
     * Per-element "value ∈ options" for a multiselect enum, as a single
     * Closure rather than Illuminate\Validation\Rule::forEach — mirrors
     * App\CustomFields\Types\Concerns\ValidatesEachElement: in this Laravel
     * version, a colon-parametrized sub-rule compiled by Rule::forEach
     * alongside a sibling `array` rule on the same attribute gets nested one
     * level too deep and fails with a bogus BadMethodCallException.
     *
     * @param  array<int, string>  $options
     */
    private function eachInRule(array $options): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($options): void {
            foreach ((array) $value as $item) {
                if (! in_array($item, $options, true)) {
                    $fail("The {$attribute} contains an invalid value.");

                    return;
                }
            }
        };
    }

    /**
     * @return array<int, mixed>
     */
    private function relationRules(ApplicableAttribute $attribute): array
    {
        $target = $attribute->relationTarget ?? [];
        $isMany = ($target['cardinality'] ?? 'one') === 'many';
        $table = $this->targetTable($target);

        if ($isMany) {
            $rules = ['array'];

            if ($table !== null) {
                $rules[] = $this->allExistRule($table);
            }

            return $rules;
        }

        $rules = ['integer'];

        if ($table !== null) {
            $rules[] = Rule::exists($table, 'id');
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $target
     */
    private function targetTable(array $target): ?string
    {
        $entityType = $target['entity_type'] ?? null;

        if (! is_string($entityType) || $entityType === '') {
            return null;
        }

        $modelClass = $this->entityRegistry->modelClassFor($entityType);

        return $modelClass === null ? null : (new $modelClass)->getTable();
    }

    /**
     * Bulk "every id exists" check for the `many` cardinality — one query for
     * the whole array rather than N `exists:` lookups.
     */
    private function allExistRule(string $table): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($table): void {
            $ids = array_values(array_unique(array_filter(
                (array) $value,
                static fn (mixed $id): bool => is_scalar($id),
            )));

            if ($ids === []) {
                return;
            }

            $found = DB::table($table)->whereIn('id', $ids)->count();

            if ($found !== count($ids)) {
                $fail("The {$attribute} contains an id that does not exist.");
            }
        };
    }
}
