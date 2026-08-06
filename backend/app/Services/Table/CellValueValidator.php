<?php

declare(strict_types=1);

namespace App\Services\Table;

use App\Support\InputFormat;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Derives the validation rules for one inline cell edit from its column's
 * declaration (spec 0053, D-6): `type` maps to a base rule, `nullable`
 * decides whether `value: null` is accepted, and the column's own optional
 * `rules` extend the set — no duplicated per-column schema, no rule invented
 * beyond what the catalogue declares.
 *
 * A RELATION column (spec 0054, D-1: `'relation' => ['resource' => ...]`)
 * takes a SEPARATE path (validateRelationValue): the value is the related
 * row's id, not a value of the display `type` (a relation column keeps
 * `type: 'text'` for display/sort/filter, so the plain type-derived `string`
 * rule would wrongly reject the submitted integer id).
 *
 * A `editor: 'select'` column (spec 0055, D-3 — e.g. a domain-specific status
 * field whose real column is not `/for-select`-backed) takes the SAME "value
 * is an id" path minus the for-select scope check: its own domain membership
 * rule lives in the definition's `updateCell()` override, not here.
 *
 * That branch keys on `editor`, NOT on the mere presence of `editableField`
 * (spec 0055, D-3): `editableField` only remaps the permission/write key
 * (0054, D-1), and a TEXT column may legitimately use it — e.g.
 * `first_name` -> `client_first_name`, whose value is a string and would be
 * wrongly rejected by an `integer` rule.
 */
final class CellValueValidator
{
    /** The `editor` kind whose submitted value is a related row's id, not a value of the display `type`. */
    private const string SELECT_EDITOR = 'select';

    /** The `editor` kind whose submitted value is a LIST of related row ids (user directive 2026-07-23). */
    private const string MULTISELECT_EDITOR = 'multiselect';

    /**
     * The `editor` kind whose submitted value is a LIST of {business function,
     * product category} PAIRS (spec 0075): the `product_lines` collection, as
     * edited in-cell on the request-management grid. Structural check only —
     * the set's own rules (existence, selectability, the category/function
     * match, no repeated pair) are enforced by the ONE writer both write
     * channels reach, exactly like the `select` editor's membership rule.
     */
    private const string PRODUCT_LINES_EDITOR = 'product_lines';

    /** The `format` names a column may declare, each mapping to an InputFormat canonicalizer. */
    private const string FORMAT_PERSON_NAME = 'person_name';

    private const string FORMAT_PHONE = 'phone';

    private const string FORMAT_TAX_CODE = 'tax_code';

    private const string FORMAT_VAT_NUMBER = 'vat_number';

    public function __construct(private readonly RelationValueScopeChecker $relationScope) {}

    /**
     * @param  array<string, mixed>  $column  the raw column declaration (id/type/nullable/rules/options/relation/editableField)
     *
     * @throws ValidationException
     */
    public function validate(array $column, mixed $value): mixed
    {
        // Checked BEFORE the relation branch: a multiselect column declares
        // `relation` too (it is what names the resource whose ids it holds),
        // but its value is a LIST, which the single-id branch would reject.
        if (($column['editor'] ?? null) === self::MULTISELECT_EDITOR) {
            return $this->validateIdListValue($column, $value);
        }

        if (($column['editor'] ?? null) === self::PRODUCT_LINES_EDITOR) {
            return $this->validatePairListValue($value);
        }

        if (isset($column['relation'])) {
            return $this->validateRelationValue($column, $value);
        }

        if (($column['editor'] ?? null) === self::SELECT_EDITOR) {
            return $this->validateIdValue($column, $value);
        }

        $nullable = ($column['nullable'] ?? false) === true;

        // Before the rules, never after: a `tax_code` typed lowercase must be
        // uppercased so the TaxCode rule checks the SAME string that is stored.
        $value = $this->format($column, $value);

        $rules = [
            $nullable ? 'nullable' : 'required',
            ...$this->typeRules($column),
            ...($column['rules'] ?? []),
        ];

        Validator::make(['value' => $value], ['value' => $rules])->validate();

        return $value;
    }

    /**
     * Applies the column's declared canonical format (user directive
     * 2026-07-23), so an inline edit stores a value in the exact same shape
     * the card form does — `App\Support\InputFormat` is the single source of
     * truth for both.
     *
     * Declaration-driven like `rules`: a column without `format` is untouched,
     * and an unknown format name is ignored rather than guessed.
     *
     * @param  array<string, mixed>  $column
     */
    private function format(array $column, mixed $value): mixed
    {
        $format = $column['format'] ?? null;

        if (! is_string($value) || ! is_string($format)) {
            return $value;
        }

        return match ($format) {
            self::FORMAT_PERSON_NAME => InputFormat::personName($value),
            self::FORMAT_PHONE => InputFormat::phone($value),
            self::FORMAT_TAX_CODE => InputFormat::taxCode($value),
            self::FORMAT_VAT_NUMBER => InputFormat::vatNumber($value),
            default => $value,
        };
    }

    /**
     * D-2: the value must be an integer (or null, when the column declares
     * `nullable`) that the picker could actually offer from the declared
     * relation resource's own `/for-select` query — an id that merely
     * `exists` elsewhere is not enough (see RelationValueScopeChecker).
     *
     * @param  array<string, mixed>  $column
     *
     * @throws ValidationException
     */
    private function validateRelationValue(array $column, mixed $value): mixed
    {
        $nullable = ($column['nullable'] ?? false) === true;

        Validator::make(
            ['value' => $value],
            ['value' => [$nullable ? 'nullable' : 'required', 'integer']],
        )->validate();

        if ($value === null) {
            return null;
        }

        $resource = $column['relation']['resource'] ?? null;

        if (! is_string($resource) || ! $this->relationScope->inScope($resource, (int) $value)) {
            throw ValidationException::withMessages([
                'value' => [__('The selected value does not exist or is not available.')],
            ]);
        }

        return (int) $value;
    }

    /**
     * A non-relation `select` column's value is still an id, not a value of
     * the display `type` — structural check only (integer, or null when
     * `nullable`); the domain-specific membership rule is the definition's
     * own responsibility (updateCell()). Deliberately NOT an `in:` rule over
     * the column's resolved options: those come from `optionsFor()` (absent
     * from the raw declaration this validator receives), and the AUTHORITATIVE
     * check is per-ROW anyway — a domain-wide catalogue check would be
     * strictly weaker and a second, divergable copy of it.
     *
     * @param  array<string, mixed>  $column
     *
     * @throws ValidationException
     */
    private function validateIdValue(array $column, mixed $value): mixed
    {
        $nullable = ($column['nullable'] ?? false) === true;

        Validator::make(
            ['value' => $value],
            ['value' => [$nullable ? 'nullable' : 'required', 'integer']],
        )->validate();

        return $value === null ? null : (int) $value;
    }

    /**
     * A MULTISELECT column's value is the whole related-id collection (a
     * full-replace sync, user directive 2026-07-23): every id must be one the
     * picker could offer from the declared resource's own `/for-select` query,
     * the same guard validateRelationValue() applies to a single id. An empty
     * list is structurally valid here — whether it is ACCEPTED is the
     * mandatory-field rule (TableCellUpdateService step 4.5), never this
     * column's own declaration.
     *
     * @param  array<string, mixed>  $column
     * @return array<int, int>
     *
     * @throws ValidationException
     */
    private function validateIdListValue(array $column, mixed $value): array
    {
        Validator::make(
            ['value' => $value],
            ['value' => ['present', 'array'], 'value.*' => ['integer']],
        )->validate();

        /** @var array<int, mixed> $value */
        $ids = array_values(array_unique(array_map(static fn (mixed $id): int => (int) $id, $value)));
        $resource = $column['relation']['resource'] ?? null;

        if (! is_string($resource)) {
            throw ValidationException::withMessages([
                'value' => [__('The selected value does not exist or is not available.')],
            ]);
        }

        foreach ($ids as $id) {
            if (! $this->relationScope->inScope($resource, $id)) {
                throw ValidationException::withMessages([
                    'value' => [__('The selected value does not exist or is not available.')],
                ]);
            }
        }

        return $ids;
    }

    /**
     * A `product_lines` column's value is the whole collection of pairs (spec
     * 0075): each row must carry both integer ids and nothing else travels on.
     * Whether an EMPTY collection is accepted is the mandatory-field rule
     * (TableCellUpdateService step 4.5), not this column's declaration —
     * `product_lines` is mandatory, so the empty case is refused there.
     *
     * @return array<int, array{business_function_id: int, product_category_id: int}>
     *
     * @throws ValidationException
     */
    private function validatePairListValue(mixed $value): array
    {
        Validator::make(
            ['value' => $value],
            [
                'value' => ['present', 'array'],
                'value.*.business_function_id' => ['required', 'integer'],
                'value.*.product_category_id' => ['required', 'integer'],
            ],
        )->validate();

        /** @var array<int, array<string, mixed>> $value */
        return array_map(static fn (array $pair): array => [
            'business_function_id' => (int) $pair['business_function_id'],
            'product_category_id' => (int) $pair['product_category_id'],
        ], array_values($value));
    }

    /**
     * @param  array<string, mixed>  $column
     * @return array<int, mixed>
     */
    private function typeRules(array $column): array
    {
        return match ($column['type'] ?? null) {
            'text' => ['string'],
            'number' => ['numeric'],
            'boolean' => ['boolean'],
            'date', 'datetime' => ['date'],
            'enum', 'badge' => [Rule::in($column['options'] ?? [])],
            default => [],
        };
    }
}
