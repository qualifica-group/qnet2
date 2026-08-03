<?php

declare(strict_types=1);

namespace App\Services\ProductLines;

use App\Models\ProductCategory;
use App\Rules\SelectableProductCategory;
use App\Services\ProductCategories\CategoryHierarchy;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * THE definition of what makes a `product_lines` collection valid (spec 0075,
 * D-1): per-row (the business function exists, the category exists and is
 * SELECTABLE — spec 0074, with the already-persisted ones exempt) and
 * cross-row (no repeated {funzione aziendale, categoria} pair; each row's
 * category belongs to EXACTLY that business function once inheritance is
 * resolved).
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
 *  - rules()/crossRowErrors(): keyed `product_lines.<index>.<field>`, merged
 *    into the FormRequest's own validator alongside the rest of the payload;
 *  - assert(): everything at once, thrown as a single ValidationException on
 *    the key the caller names (an inline PATCH edits ONE cell — there is no
 *    per-row control to land a message on).
 */
final class ProductLineSetValidator
{
    /**
     * The two cross-row messages, kept as the ENGLISH source strings they are
     * translated from (`lang/it.json`): they are what `__()` is keyed by, so a
     * caller comparing against them still matches the untranslated form.
     */
    public const string DUPLICATE_PAIR_MESSAGE = 'This business function / product category pair is already present.';

    public const string BUSINESS_FUNCTION_MISMATCH_MESSAGE = 'This product category does not belong to the selected business function.';

    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    /**
     * The per-row rules, keyed for the given collection attribute.
     *
     * @param  array<int, int>  $exemptCategoryIds  categories already persisted on the record being updated (spec 0074, D-3b)
     * @return array<string, array<int, mixed>>
     */
    public function rules(string $attribute, array $exemptCategoryIds): array
    {
        return [
            $attribute.'.*.business_function_id' => ['required', 'integer', Rule::exists('business_functions', 'id')],
            // Spec 0074: only SELECTABLE categories may classify a line. The
            // categories already on the record are exempt (D-3b), so a
            // full-replace sync that resubmits the current lines unchanged
            // never fails because one of them was made unselectable meanwhile.
            $attribute.'.*.product_category_id' => ['required', 'integer', new SelectableProductCategory($exemptCategoryIds)],
        ];
    }

    /**
     * The cross-row invariants, keyed `<attribute>.<index>.<field>`. Rows that
     * are not well-formed are skipped: the per-row rules already report them.
     *
     * @param  array<int, mixed>  $lines
     * @return array<string, string>
     */
    public function crossRowErrors(array $lines, string $attribute): array
    {
        $errors = [];
        $seenPairs = [];

        foreach ($lines as $index => $line) {
            if (! $this->isWellFormedLine($line)) {
                continue;
            }

            /** @var array{business_function_id: mixed, product_category_id: mixed} $line */
            $businessFunctionId = (int) $line['business_function_id'];
            $productCategoryId = (int) $line['product_category_id'];
            $pairKey = "{$businessFunctionId}:{$productCategoryId}";

            if (isset($seenPairs[$pairKey])) {
                $errors["{$attribute}.{$index}.product_category_id"] = __(self::DUPLICATE_PAIR_MESSAGE);

                continue;
            }

            $seenPairs[$pairKey] = true;

            if (! $this->categoryMatchesBusinessFunction($productCategoryId, $businessFunctionId)) {
                $errors["{$attribute}.{$index}.business_function_id"] = __(self::BUSINESS_FUNCTION_MISMATCH_MESSAGE);
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

    /**
     * Whether the category's EFFECTIVE business function (own, or inherited
     * from its ancestors) is exactly the one the row declares.
     */
    private function categoryMatchesBusinessFunction(int $productCategoryId, int $businessFunctionId): bool
    {
        $category = ProductCategory::find($productCategoryId);

        if ($category === null) {
            // The per-row rule on product_category_id already reports this.
            return true;
        }

        $effective = $this->hierarchy->effectiveBusinessFunction($category);

        return $effective !== null && $effective['id'] === $businessFunctionId;
    }

    private function isWellFormedLine(mixed $line): bool
    {
        return is_array($line) && isset($line['business_function_id'], $line['product_category_id']);
    }
}
