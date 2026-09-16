<?php

declare(strict_types=1);

namespace App\Services\ProductLines;

use App\Enums\CategoryManagementMode;
use App\Rules\SelectableProductCategory;
use App\Services\ProductCategories\CategoryHierarchy;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * THE definition of what makes a `product_lines` collection valid: per-row
 * (the category exists and is SELECTABLE — spec 0074, with the
 * already-persisted ones exempt — and its EFFECTIVE business function is
 * resolvable) and cross-row (no repeated category; and, spec 0077 rev.2
 * INV-3, a card carrying a row whose root management_mode is `single` may
 * carry that row only).
 *
 * Spec 0132, D-3: the business function is no longer a row input — a row is
 * identified by its category ALONE, and the function is DERIVED
 * (BusinessFunctionResolver) rather than declared and cross-checked. This
 * revokes the former {funzione aziendale, categoria} pair identity (spec
 * 0075) and the mismatch check it needed: a descendant may not override the
 * business function it inherits (ProductCategoryService::
 * assertNoInheritedBusinessFunction), so once the function follows the
 * category there is nothing left to mismatch.
 *
 * Spec 0077 rev.2 (user directive 2026-08-31) REVOKED INV-1/INV-2: the rows
 * of a `multiple` card are independent — each picks its own category root.
 *
 * It exists as a service, and not only as the FormRequest trait it used to
 * be, because the collection is written through TWO channels: the form
 * endpoints (which have a FormRequest — see
 * `Http\Requests\Concerns\ValidatesProductLines`, which delegates here) and
 * the inline cell-editing engine (which has none — see
 * `Services\RequestManagement\RequestProductLineWriter`, which calls
 * `assert()`). Two copies of these rules would diverge at the first change.
 *
 * Two surfaces, because the two channels report differently:
 *  - rules()/crossRowErrors()/pairErrors(): keyed
 *    `product_lines.<index>.<field>`, merged into the FormRequest's own
 *    validator alongside the rest of the payload;
 *  - assert(): everything at once, thrown as a single ValidationException on
 *    the key the caller names (an inline PATCH edits ONE cell — there is no
 *    per-row control to land a message on).
 */
final class ProductLineSetValidator
{
    /**
     * Spec 0129's competence-only sibling (CompetenceLineSetValidator) still
     * declares a {funzione aziendale, categoria} pair and reuses these two
     * ENGLISH source strings (`lang/it.json`) verbatim — a competence row is
     * NOT in scope of spec 0132's derivation (D-4), so the messages stay here
     * for it even though the card path below no longer raises them itself.
     */
    public const string DUPLICATE_PAIR_MESSAGE = 'This business function / product category pair is already present.';

    public const string BUSINESS_FUNCTION_MISMATCH_MESSAGE = 'This product category does not belong to the selected business function.';

    /**
     * Spec 0132: a card row is now identified by its category ALONE — the
     * duplicate check this validator itself runs (pairErrors()) reports this
     * message instead of DUPLICATE_PAIR_MESSAGE above.
     */
    public const string DUPLICATE_CATEGORY_MESSAGE = 'This product category is already present.';

    /**
     * Spec 0132, AC-003: a selectable category whose EFFECTIVE business
     * function cannot be resolved (own, or inherited from its ancestors) —
     * e.g. the known `parent_id`-cycle data issue — cannot classify a card
     * row: the function is derived from it, and there would be none to
     * derive.
     */
    public const string CATEGORY_WITHOUT_BUSINESS_FUNCTION_MESSAGE = 'This product category has no business function of reference.';

    /**
     * The card-level invariant (spec 0077 INV-3). Unlike the messages above
     * (which blame a single row), it lands on the collection attribute
     * itself — `errors: { product_lines: [...] }`, no row index — because it
     * is not about one row, it is about how the rows relate to each other.
     */
    public const string SINGLE_ROW_ONLY_MESSAGE = 'This product category allows only a single row.';

    public function __construct(
        private readonly CategoryHierarchy $hierarchy,
        private readonly BusinessFunctionResolver $businessFunctionResolver,
    ) {}

    /**
     * The per-row rules, keyed for the given collection attribute. Spec 0132:
     * `business_function_id` is no longer accepted as an input — if the
     * client still sends it, it simply carries no rule and is dropped by
     * `validated()`, never reaching the DataObjects that write the row.
     *
     * @param  array<int, int>  $exemptCategoryIds  categories already persisted on the record being updated (spec 0074, D-3b)
     * @return array<string, array<int, mixed>>
     */
    public function rules(string $attribute, array $exemptCategoryIds): array
    {
        return [
            // Spec 0074: only SELECTABLE categories may classify a line. The
            // categories already on the record are exempt (D-3b), so a
            // full-replace sync that resubmits the current lines unchanged
            // never fails because one of them was made unselectable meanwhile.
            $attribute.'.*.product_category_id' => ['required', 'integer', new SelectableProductCategory($exemptCategoryIds)],
        ];
    }

    /**
     * The cross-row invariants, keyed `<attribute>.<index>.<field>`: the pair
     * rules PLUS the card-level one.
     *
     * @param  array<int, mixed>  $lines
     * @return array<string, string>
     */
    public function crossRowErrors(array $lines, string $attribute): array
    {
        return [
            ...$this->pairErrors($lines, $attribute),
            ...$this->collectionInvariantErrors($lines, $attribute),
        ];
    }

    /**
     * The PAIR rules alone — spec 0132: no repeated category, and each row's
     * category must have a resolvable EFFECTIVE business function — without
     * the card-level `single` cap of spec 0077. Kept separate from
     * `collectionInvariantErrors()` (called together by `crossRowErrors()`)
     * for the same reason it always was: one concerns each row on its own
     * merits, the other how the rows relate to each other. Rows that are not
     * well-formed are skipped: the per-row rules already report them.
     *
     * @param  array<int, mixed>  $lines
     * @return array<string, string>
     */
    public function pairErrors(array $lines, string $attribute): array
    {
        $errors = [];
        $seenCategoryIds = [];
        $businessFunctionByCategoryId = $this->businessFunctionResolver->resolveMany(
            $this->wellFormedCategoryIds($lines),
        );

        foreach ($lines as $index => $line) {
            if (! $this->isWellFormedLine($line)) {
                continue;
            }

            /** @var array{product_category_id: mixed} $line */
            $productCategoryId = (int) $line['product_category_id'];

            if (isset($seenCategoryIds[$productCategoryId])) {
                $errors["{$attribute}.{$index}.product_category_id"] = __(self::DUPLICATE_CATEGORY_MESSAGE);

                continue;
            }

            $seenCategoryIds[$productCategoryId] = true;

            if (($businessFunctionByCategoryId[$productCategoryId] ?? null) === null) {
                $errors["{$attribute}.{$index}.product_category_id"] = __(self::CATEGORY_WITHOUT_BUSINESS_FUNCTION_MESSAGE);
            }
        }

        return $errors;
    }

    /**
     * The WHOLE rule set at once, for a channel with no FormRequest to merge
     * into: the collection must be a non-empty array of well-formed, valid,
     * non-repeating pairs. Every message lands on $errorField.
     *
     * @param  array<int, mixed>  $lines
     * @param  array<int, int>  $exemptCategoryIds
     *
     * @throws ValidationException
     */
    public function assert(array $lines, array $exemptCategoryIds, string $errorField): void
    {
        $attribute = 'product_lines';

        $validator = Validator::make(
            [$attribute => $lines],
            [
                $attribute => ['required', 'array', 'min:1'],
                ...$this->rules($attribute, $exemptCategoryIds),
            ],
        );

        $messages = array_merge(
            array_values(array_merge(...array_values($validator->errors()->messages()))),
            array_values($this->crossRowErrors($lines, $attribute)),
        );

        if ($messages === []) {
            return;
        }

        throw ValidationException::withMessages([$errorField => $messages]);
    }

    private function isWellFormedLine(mixed $line): bool
    {
        return is_array($line) && isset($line['product_category_id']);
    }

    /**
     * The well-formed rows' category ids, deduplicated — the batch input
     * `pairErrors()` resolves business functions for in one shot.
     *
     * @param  array<int, mixed>  $lines
     * @return array<int, int>
     */
    private function wellFormedCategoryIds(array $lines): array
    {
        return array_values(array_unique(array_map(
            static fn (array $line): int => (int) $line['product_category_id'],
            array_values(array_filter($lines, fn (mixed $line): bool => $this->isWellFormedLine($line))),
        )));
    }

    /**
     * Spec 0077 INV-3: skipped below two well-formed rows — it cannot be
     * broken by a single row, and a malformed one is already reported by the
     * per-row rules. Only ever invoked with the SUBMITTED collection
     * (crossRowErrors()/assert() are only ever called that way), so D-5's
     * grandfathering falls out for free: a historical record that never
     * resubmits `product_lines` never reaches here.
     *
     * Rows may now sit on different roots (rev.2 revoked INV-1), so the mode
     * is resolved over ALL of them and the STRICTEST one wins (D-10): one row
     * on a `single` root caps the whole card at that row. A category that
     * resolves to nothing is ignored — the per-row existence/selectability
     * rule already reports it.
     *
     * @param  array<int, mixed>  $lines
     * @return array<string, string>
     */
    private function collectionInvariantErrors(array $lines, string $attribute): array
    {
        $wellFormedCount = count(array_filter($lines, fn (mixed $line): bool => $this->isWellFormedLine($line)));

        if ($wellFormedCount < 2) {
            return [];
        }

        $categoryIds = $this->wellFormedCategoryIds($lines);

        // One batch call resolves every row's root+mode at once (spec 0077
        // constraint: never a walk per row).
        $carriesSingleModeRow = array_any(
            array_filter($this->hierarchy->rootManagementModesFor($categoryIds)),
            static fn (array $resolution): bool => $resolution['management_mode'] === CategoryManagementMode::Single,
        );

        return $carriesSingleModeRow ? [$attribute => __(self::SINGLE_ROW_ONLY_MESSAGE)] : [];
    }
}
