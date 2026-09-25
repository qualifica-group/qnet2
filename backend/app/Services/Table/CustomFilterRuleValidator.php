<?php

declare(strict_types=1);

namespace App\Services\Table;

use App\Tables\TableDefinition;

/**
 * Validation for a custom filter Rules payload (spec 0158): `{and: Rule[],
 * or: Rule[]}`, `Rule = {field, operator, value?}`. Shared by every
 * FormRequest that accepts such a payload (rows / exports / filter-views), so
 * the exact same allow-list, operator matrix and value shapes apply
 * everywhere a client can submit one — mirrors AdvancedFilterApplier::validate().
 *
 * `field` must be one of the definition's FILTERABLE columns (the exact same
 * allow-list TableRowsRequest/TableFilterViewRequest already enforce for
 * `filterModel`/`filters`) AND resolve to a usable rule type via
 * resolveType() (out of scope: a column with no filterType, spec 0158
 * scope). Every out-of-allow-list field, unsupported operator or malformed
 * value yields a 422 and the rule set never reaches the query.
 *
 * Error keys mirror the Rule's own address inside the payload:
 * `and.<index>.field`/`operator`/`value`, `and` itself (group-shaped error,
 * e.g. too many rules), or `''` (the empty string) for a payload-level error
 * (e.g. zero rules in total) — the caller prefixes `''` with its own field
 * name (`rules`/`customFilterRules`).
 */
class CustomFilterRuleValidator
{
    /** Max rules accepted in a single `and`/`or` group. */
    public const int MAX_RULES_PER_GROUP = 20;

    /** Max values accepted in a `set` rule's `in`/`not_in` list. */
    public const int MAX_SET_VALUES = 500;

    /** Max length of a `text` rule's value. */
    public const int TEXT_VALUE_MAX_LENGTH = 255;

    public const int LAST_N_DAYS_MIN = 1;

    public const int LAST_N_DAYS_MAX = 366;

    /** @var array<int, string> */
    private const array GROUP_KEYS = ['and', 'or'];

    /** @var array<int, string> */
    private const array TEXT_OPERATORS = ['contains', 'equals', 'not_equals', 'blank', 'not_blank'];

    /** @var array<int, string> */
    private const array NUMBER_OPERATORS = ['equals', 'lt', 'lte', 'gt', 'gte', 'between', 'blank', 'not_blank'];

    /** @var array<int, string> */
    private const array DATE_OPERATORS = [
        'equals', 'lt', 'lte', 'gt', 'gte', 'between', 'blank', 'not_blank',
        'today', 'this_week', 'this_month', 'last_n_days',
    ];

    /** @var array<int, string> */
    private const array SET_OPERATORS = ['in', 'not_in'];

    /** @var array<int, string> */
    private const array BOOLEAN_OPERATORS = ['is'];

    /** Operators that carry no `value` at all. */
    private const array NO_VALUE_OPERATORS = ['blank', 'not_blank', 'today', 'this_week', 'this_month'];

    private const array BLANK_OPERATORS = ['blank', 'not_blank'];

    /**
     * Resolve a filterable column's declared config to a custom-filter-rule
     * type (spec 0158, frozen contract), or null when the column cannot be
     * used in a rule (out of scope: "regole su campi non filtrabili").
     *
     * @param  array<string, mixed>  $columnConfig
     */
    public static function resolveType(array $columnConfig): ?string
    {
        if (($columnConfig['type'] ?? null) === 'boolean' || ($columnConfig['filterType'] ?? null) === 'boolean') {
            return 'boolean';
        }

        $filterType = $columnConfig['filterType'] ?? null;

        return match ($filterType) {
            'set' => 'set',
            'date' => 'date',
            'number' => 'number',
            'text' => 'text',
            'multi' => match ($columnConfig['type'] ?? null) {
                'number' => 'number',
                'date' => 'date',
                default => 'text',
            },
            default => null,
        };
    }

    /**
     * @param  mixed  $rules  the raw `rules`/`customFilterRules` input
     * @return array<string, string>
     */
    public function validate(TableDefinition $definition, mixed $rules): array
    {
        if (! is_array($rules)) {
            return ['' => 'The rules must be an object.'];
        }

        $errors = [];
        $totalRules = 0;

        foreach (self::GROUP_KEYS as $group) {
            if (! array_key_exists($group, $rules)) {
                continue;
            }

            $groupRules = $rules[$group];

            if (! is_array($groupRules)) {
                $errors[$group] = "The [{$group}] group must be a list of rules.";

                continue;
            }

            if (count($groupRules) > self::MAX_RULES_PER_GROUP) {
                $errors[$group] = 'The ['.$group.'] group accepts at most '.self::MAX_RULES_PER_GROUP.' rules.';
            }

            foreach ($groupRules as $index => $rule) {
                $totalRules++;

                foreach ($this->validateRule($definition, $rule) as $key => $message) {
                    $errors[$key === '' ? "{$group}.{$index}" : "{$group}.{$index}.{$key}"] = $message;
                }
            }
        }

        if ($totalRules === 0) {
            $errors[''] = 'At least one rule is required.';
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    private function validateRule(TableDefinition $definition, mixed $rule): array
    {
        if (! is_array($rule)) {
            return ['' => 'Each rule must be an object.'];
        }

        $field = $rule['field'] ?? null;

        if (! is_string($field) || ! in_array($field, $definition->filterableColumnIds(), true)) {
            $label = is_string($field) ? $field : 'null';

            return ['field' => "Filtering is not allowed on column [{$label}]."];
        }

        $columnConfig = $definition->filterableColumnMap()[$field];
        $type = self::resolveType($columnConfig);

        if ($type === null) {
            return ['field' => "Column [{$field}] cannot be used in a custom filter rule."];
        }

        $operator = $rule['operator'] ?? null;

        if (! is_string($operator) || ! in_array($operator, $this->operatorsFor($type), true)) {
            $label = is_string($operator) ? $operator : 'null';

            return ['operator' => "Operator [{$label}] is not allowed for column [{$field}]."];
        }

        if (in_array($operator, self::BLANK_OPERATORS, true) && ($columnConfig['hasFilterValues'] ?? true) === false) {
            return ['operator' => "Operator [{$operator}] is not allowed for column [{$field}]."];
        }

        if (in_array($operator, self::NO_VALUE_OPERATORS, true)) {
            return [];
        }

        return $this->validateValue($type, $operator, $rule['value'] ?? null);
    }

    /**
     * @return array<int, string>
     */
    private function operatorsFor(string $type): array
    {
        return match ($type) {
            'text' => self::TEXT_OPERATORS,
            'number' => self::NUMBER_OPERATORS,
            'date' => self::DATE_OPERATORS,
            'set' => self::SET_OPERATORS,
            'boolean' => self::BOOLEAN_OPERATORS,
        };
    }

    /**
     * @return array<string, string>
     */
    private function validateValue(string $type, string $operator, mixed $value): array
    {
        return match ($type) {
            'text' => $this->validateTextValue($value),
            'number' => $this->validateNumberValue($operator, $value),
            'date' => $this->validateDateValue($operator, $value),
            'set' => $this->validateSetValue($value),
            'boolean' => is_bool($value) ? [] : ['value' => 'The value must be a boolean.'],
        };
    }

    /**
     * @return array<string, string>
     */
    private function validateTextValue(mixed $value): array
    {
        if (! is_string($value) || $value === '' || mb_strlen($value) > self::TEXT_VALUE_MAX_LENGTH) {
            return ['value' => 'The value must be a non-empty string of at most '.self::TEXT_VALUE_MAX_LENGTH.' characters.'];
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    private function validateNumberValue(string $operator, mixed $value): array
    {
        if ($operator === 'between') {
            if (! $this->isNumericPair($value)) {
                return ['value' => 'The value must be a [min, max] pair of numbers.'];
            }

            if ((float) $value[0] > (float) $value[1]) {
                return ['value' => 'The minimum must be less than or equal to the maximum.'];
            }

            return [];
        }

        return is_numeric($value) ? [] : ['value' => 'The value must be a number.'];
    }

    private function isNumericPair(mixed $value): bool
    {
        return is_array($value) && count($value) === 2
            && array_is_list($value)
            && is_numeric($value[0] ?? null) && is_numeric($value[1] ?? null);
    }

    /**
     * @return array<string, string>
     */
    private function validateDateValue(string $operator, mixed $value): array
    {
        if ($operator === 'between') {
            if (
                ! is_array($value) || count($value) !== 2 || ! array_is_list($value)
                || ! $this->isValidDateString($value[0] ?? null) || ! $this->isValidDateString($value[1] ?? null)
            ) {
                return ['value' => 'The value must be a [from, to] pair of dates (Y-m-d).'];
            }

            return $value[0] > $value[1]
                ? ['value' => 'The start date must be on or before the end date.']
                : [];
        }

        if ($operator === 'last_n_days') {
            return $this->validateLastNDays($value);
        }

        return $this->isValidDateString($value) ? [] : ['value' => 'The value must be a date (Y-m-d).'];
    }

    /**
     * @return array<string, string>
     */
    private function validateLastNDays(mixed $value): array
    {
        $isIntegerish = is_int($value) || (is_string($value) && ctype_digit($value));

        if (! $isIntegerish) {
            return ['value' => 'The value must be an integer between '.self::LAST_N_DAYS_MIN.' and '.self::LAST_N_DAYS_MAX.'.'];
        }

        $n = (int) $value;

        return $n >= self::LAST_N_DAYS_MIN && $n <= self::LAST_N_DAYS_MAX
            ? []
            : ['value' => 'The value must be an integer between '.self::LAST_N_DAYS_MIN.' and '.self::LAST_N_DAYS_MAX.'.'];
    }

    private function isValidDateString(mixed $value): bool
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return false;
        }

        return checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4));
    }

    /**
     * @return array<string, string>
     */
    private function validateSetValue(mixed $value): array
    {
        if (! is_array($value) || $value === [] || ! array_is_list($value)) {
            return ['value' => 'The value must be a non-empty list of strings.'];
        }

        if (count($value) > self::MAX_SET_VALUES) {
            return ['value' => 'At most '.self::MAX_SET_VALUES.' values are allowed.'];
        }

        foreach ($value as $item) {
            if (! is_string($item) || $item === '') {
                return ['value' => 'Every value must be a non-empty string.'];
            }
        }

        return [];
    }
}
