<?php

declare(strict_types=1);

namespace App\Services\Assignment;

use App\Models\EmploymentProductLine;
use App\Rules\ActiveBusinessFunction;
use App\Rules\ActiveProductCategory;
use App\Services\ProductCategories\CategoryHierarchy;
use App\Services\ProductLines\ProductLineSetValidator;

/**
 * THE definition of what makes a user's `employment.product_lines` collection
 * valid (spec 0129) — the competence-only sibling of ProductLineSetValidator,
 * kept separate because the two rule sets deliberately diverge (D-9): a
 * competence row has NO `is_selectable` constraint (D-6 revokes spec 0111
 * D-4 for this one collection) and its `product_category_id` may be null
 * (D-3, "every category of this row's function").
 *
 * The invariants beyond the per-row rules:
 *  - no repeated pair, INCLUDING two (function, null) rows — reuses
 *    ProductLineSetValidator::DUPLICATE_PAIR_MESSAGE, the same message a
 *    repeated (function, category) pair gets elsewhere;
 *  - a category-mismatch check widened by D-7: a row (F, K) is admitted when
 *    K's EFFECTIVE function is F, OR K has none of its own but at least one
 *    DESCENDANT effectively belongs to F (a "neutral" container spanning
 *    several functions) — reuses
 *    ProductLineSetValidator::BUSINESS_FUNCTION_MISMATCH_MESSAGE;
 *  - the shares (spec 0346 D-2): a non-empty set adds up to exactly 100.
 *
 * Spec 0346 D-4 revoked spec 0129 D-4/D-8: a row covered by another row of
 * the same function ((F, null) + (F, K), or (F, parent) + (F, child)) is
 * admitted, since each row now carries its own share. The matching reads the
 * union of the rows, so the overlap changes nothing there.
 *
 * Every check is resolved in batch off CategoryHierarchy's own memoized
 * projections (parentIdMap/effectiveBusinessFunctionSummaries) — never a
 * query per row, mirroring OperatorCompetence's own reasoning on the read
 * side this validator protects the writes for.
 */
final class CompetenceLineSetValidator
{
    /** Spec 0346 D-2: the shares of a non-empty set add up to exactly 100. */
    public const string SHARE_TOTAL_MESSAGE = 'The assignment scope percentages must add up to 100%.';

    /**
     * D-2: the wildcard flag and non-empty competence rows are mutually
     * exclusive — one state only, no dormant rows behind an active flag.
     */
    public const string ALL_CATEGORIES_WITH_LINES_MESSAGE = 'Competence rows must be empty when "all categories" is enabled.';

    /** Spec 0367: an "all categories" row keeps the new categories, the legacy ones or both, never neither. */
    public const string NO_CATEGORY_AGE_MESSAGE = 'Choose the new categories, the old ones or both.';

    /** @var array<int, array<int, int>>|null */
    private ?array $childIdMap = null;

    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    /**
     * The per-row rules, keyed for the given collection attribute: the
     * business function is required, the category is OPTIONAL (D-3) and,
     * when present, must exist and be effectively active (spec 0208) — no
     * selectability constraint (D-6). The categories and the functions already
     * persisted on the user being updated are exempt from the activity check
     * (spec 0208 D-2, spec 0280 D-2).
     *
     * @param  array<int, int>  $exemptCategoryIds
     * @param  array<int, int>  $exemptFunctionIds
     * @return array<string, array<int, mixed>>
     */
    public function rules(string $attribute, array $exemptCategoryIds = [], array $exemptFunctionIds = []): array
    {
        return [
            $attribute.'.*.business_function_id' => ['required', 'integer', new ActiveBusinessFunction($exemptFunctionIds)],
            $attribute.'.*.product_category_id' => ['nullable', 'integer', new ActiveProductCategory($exemptCategoryIds)],
            $attribute.'.*.share_percentage' => ['required', 'integer', 'min:1', 'max:'.EmploymentProductLine::SHARE_TOTAL],
            $attribute.'.*.covers_current_categories' => ['sometimes', 'boolean'],
            $attribute.'.*.covers_legacy_categories' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The cross-row invariants (duplicates, function mismatch) keyed
     * `<attribute>.<index>.<field>`, plus the share total keyed `<attribute>`,
     * for merging into the FormRequest's own validator errors. Malformed rows
     * are skipped: the per-row rules already report them.
     *
     * @param  array<int, mixed>  $lines
     * @return array<string, string>
     */
    public function crossRowErrors(array $lines, string $attribute): array
    {
        $rows = $this->wellFormedRows($lines);

        if ($rows === []) {
            return [];
        }

        return array_merge(
            $this->duplicateAndMismatchErrors($rows, $attribute, $this->effectiveFunctionByCategory()),
            $this->shareTotalErrors($lines, $attribute),
            $this->categoryAgeErrors($lines, $attribute),
        );
    }

    /**
     * Spec 0367: a null-category row with both "Nuove" and "Vecchie" off would
     * cover nothing. An absent flag reads as true (the column default).
     *
     * @param  array<int, mixed>  $lines
     * @return array<string, string>
     */
    private function categoryAgeErrors(array $lines, string $attribute): array
    {
        $errors = [];

        foreach ($lines as $index => $line) {
            if (is_array($line) && ($line['product_category_id'] ?? null) === null
                && ! filter_var($line['covers_current_categories'] ?? true, FILTER_VALIDATE_BOOL)
                && ! filter_var($line['covers_legacy_categories'] ?? true, FILTER_VALIDATE_BOOL)) {
                $errors["{$attribute}.{$index}.covers_current_categories"] = __(self::NO_CATEGORY_AGE_MESSAGE);
            }
        }

        return $errors;
    }

    /**
     * Spec 0346 D-2: checked only when EVERY row carries a valid share, so a
     * missing or out-of-range one is reported once, on its row, by rules().
     *
     * @param  array<int, mixed>  $lines
     * @return array<string, string>
     */
    private function shareTotalErrors(array $lines, string $attribute): array
    {
        $total = 0;

        foreach ($lines as $line) {
            $share = is_array($line) ? filter_var($line['share_percentage'] ?? null, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1, 'max_range' => EmploymentProductLine::SHARE_TOTAL],
            ]) : false;

            if ($share === false) {
                return [];
            }

            $total += $share;
        }

        return $total === EmploymentProductLine::SHARE_TOTAL ? [] : [$attribute => __(self::SHARE_TOTAL_MESSAGE)];
    }

    /**
     * @param  array<int, array{business_function_id: int, product_category_id: int|null}>  $rows
     * @param  array<int, int|null>  $effectiveFunctionByCategory
     * @return array<string, string>
     */
    private function duplicateAndMismatchErrors(array $rows, string $attribute, array $effectiveFunctionByCategory): array
    {
        $errors = [];
        $seenPairs = [];

        foreach ($rows as $index => $row) {
            $pairKey = $row['business_function_id'].':'.($row['product_category_id'] ?? 'null');

            if (isset($seenPairs[$pairKey])) {
                $errors["{$attribute}.{$index}.product_category_id"] = __(ProductLineSetValidator::DUPLICATE_PAIR_MESSAGE);

                continue;
            }

            $seenPairs[$pairKey] = true;

            if ($row['product_category_id'] !== null
                && ! $this->categoryAdmittedUnderFunction($row['product_category_id'], $row['business_function_id'], $effectiveFunctionByCategory)) {
                $errors["{$attribute}.{$index}.business_function_id"] = __(ProductLineSetValidator::BUSINESS_FUNCTION_MISMATCH_MESSAGE);
            }
        }

        return $errors;
    }

    /**
     * D-7: $categoryId is admitted on a row declaring $functionId when its
     * OWN effective function is $functionId, or — when it has none — at
     * least one of its descendants effectively belongs to $functionId (a
     * "neutral" container spanning several functions). A category absent
     * from the map does not exist: the per-row `exists` rule already reports
     * that, so this defers rather than adding a second message.
     *
     * @param  array<int, int|null>  $effectiveFunctionByCategory
     */
    private function categoryAdmittedUnderFunction(int $categoryId, int $functionId, array $effectiveFunctionByCategory): bool
    {
        if (! array_key_exists($categoryId, $effectiveFunctionByCategory)) {
            return true;
        }

        $effective = $effectiveFunctionByCategory[$categoryId];

        if ($effective !== null) {
            return $effective === $functionId;
        }

        foreach ($this->descendantIds($categoryId) as $descendantId) {
            if (($effectiveFunctionByCategory[$descendantId] ?? null) === $functionId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, int|null>
     */
    private function effectiveFunctionByCategory(): array
    {
        return array_map(
            static fn (?array $summary): ?int => $summary === null ? null : (int) $summary['id'],
            $this->hierarchy->effectiveBusinessFunctionSummaries(),
        );
    }

    /**
     * Every DESCENDANT id of $categoryId, breadth-first off the in-memory
     * child index (childIdMap()) — never a query per row.
     *
     * @return array<int, int>
     */
    private function descendantIds(int $categoryId): array
    {
        $childIdMap = $this->childIdMap();
        $visited = [$categoryId => true];
        $descendants = [];
        $queue = $childIdMap[$categoryId] ?? [];

        while ($queue !== []) {
            $currentId = array_shift($queue);

            if (isset($visited[$currentId])) {
                continue;
            }

            $visited[$currentId] = true;
            $descendants[] = $currentId;

            foreach ($childIdMap[$currentId] ?? [] as $childId) {
                $queue[] = $childId;
            }
        }

        return $descendants;
    }

    /**
     * parent id => child ids, inverted once from CategoryHierarchy's memoized
     * `parent_id` projection (same construction as OperatorCompetence's own
     * childIdMap(), independent copy since the two live on different
     * services with their own lifetime).
     *
     * @return array<int, array<int, int>>
     */
    private function childIdMap(): array
    {
        if ($this->childIdMap !== null) {
            return $this->childIdMap;
        }

        $this->childIdMap = [];

        foreach ($this->hierarchy->parentIdMap() as $categoryId => $parentId) {
            if ($parentId !== null) {
                $this->childIdMap[$parentId][] = $categoryId;
            }
        }

        return $this->childIdMap;
    }

    /**
     * Normalizes and keeps only well-formed rows, PRESERVING their original
     * index so error keys still point at the submitted array position.
     *
     * @param  array<int, mixed>  $lines
     * @return array<int, array{business_function_id: int, product_category_id: int|null}>
     */
    private function wellFormedRows(array $lines): array
    {
        $rows = [];

        foreach ($lines as $index => $line) {
            if (! $this->isWellFormedLine($line)) {
                continue;
            }

            /** @var array{business_function_id: mixed, product_category_id: mixed} $line */
            $rows[$index] = [
                'business_function_id' => (int) $line['business_function_id'],
                'product_category_id' => $line['product_category_id'] === null ? null : (int) $line['product_category_id'],
            ];
        }

        return $rows;
    }

    private function isWellFormedLine(mixed $line): bool
    {
        return is_array($line)
            && array_key_exists('business_function_id', $line)
            && is_numeric($line['business_function_id'])
            && array_key_exists('product_category_id', $line)
            && ($line['product_category_id'] === null || is_numeric($line['product_category_id']));
    }
}
