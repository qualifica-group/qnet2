<?php

declare(strict_types=1);

namespace App\Services\Assignment;

use App\Services\ProductCategories\CategoryHierarchy;
use App\Services\ProductLines\ProductLineSetValidator;
use Illuminate\Validation\Rule;

/**
 * THE definition of what makes a user's `employment.product_lines` collection
 * valid (spec 0129) — the competence-only sibling of ProductLineSetValidator,
 * kept separate because the two rule sets deliberately diverge (D-9): a
 * competence row has NO `is_selectable` constraint (D-6 revokes spec 0111
 * D-4 for this one collection) and its `product_category_id` may be null
 * (D-3, "every category of this row's function").
 *
 * Two invariants beyond the per-row rules:
 *  - no repeated pair, INCLUDING two (function, null) rows — reuses
 *    ProductLineSetValidator::DUPLICATE_PAIR_MESSAGE, the same message a
 *    repeated (function, category) pair gets elsewhere;
 *  - a category-mismatch check widened by D-7: a row (F, K) is admitted when
 *    K's EFFECTIVE function is F, OR K has none of its own but at least one
 *    DESCENDANT effectively belongs to F (a "neutral" container spanning
 *    several functions) — reuses
 *    ProductLineSetValidator::BUSINESS_FUNCTION_MISMATCH_MESSAGE;
 *  - redundancy (D-4/D-8): a row (F, K) is rejected when another row of the
 *    SAME function already covers K — either (F, null) or (F, ancestor of
 *    K). Order-independent: the error always lands on the more specific
 *    (covered) row, never on the general one, regardless of array position.
 *
 * Every check is resolved in batch off CategoryHierarchy's own memoized
 * projections (parentIdMap/effectiveBusinessFunctionSummaries) — never a
 * query per row, mirroring OperatorCompetence's own reasoning on the read
 * side this validator protects the writes for.
 */
final class CompetenceLineSetValidator
{
    /**
     * A row's category already covered by another row of the same function
     * (D-4/D-8): (function, null) or an ancestor of it.
     */
    public const string REDUNDANT_LINE_MESSAGE = 'This product category is already covered by another row on the same business function.';

    /**
     * D-2: the wildcard flag and non-empty competence rows are mutually
     * exclusive — one state only, no dormant rows behind an active flag.
     */
    public const string ALL_CATEGORIES_WITH_LINES_MESSAGE = 'Competence rows must be empty when "all categories" is enabled.';

    /** @var array<int, array<int, int>>|null */
    private ?array $childIdMap = null;

    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    /**
     * The per-row rules, keyed for the given collection attribute: the
     * business function is required, the category is OPTIONAL (D-3) and,
     * when present, need only exist — no selectability constraint (D-6).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(string $attribute): array
    {
        return [
            $attribute.'.*.business_function_id' => ['required', 'integer', Rule::exists('business_functions', 'id')],
            $attribute.'.*.product_category_id' => ['nullable', 'integer', Rule::exists('product_categories', 'id')],
        ];
    }

    /**
     * The cross-row invariants (duplicates, function mismatch, redundancy),
     * keyed `<attribute>.<index>.<field>` for merging into the FormRequest's
     * own validator errors. Malformed rows are skipped: the per-row rules
     * already report them.
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

        $effectiveFunctionByCategory = $this->effectiveFunctionByCategory();

        $errors = $this->duplicateAndMismatchErrors($rows, $attribute, $effectiveFunctionByCategory);

        return array_merge($errors, $this->redundancyErrors($rows, $attribute, $errors));
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
     * D-4/D-8: a row is redundant when another row of the SAME function
     * already covers its category — a (function, null) row, or a row on one
     * of its ancestors. A row already flagged by the duplicate/mismatch pass
     * above is skipped (one message per row is enough).
     *
     * @param  array<int, array{business_function_id: int, product_category_id: int|null}>  $rows
     * @param  array<string, string>  $existingErrors
     * @return array<string, string>
     */
    private function redundancyErrors(array $rows, string $attribute, array $existingErrors): array
    {
        $errors = [];

        foreach ($rows as $index => $row) {
            if ($row['product_category_id'] === null) {
                continue;
            }

            if (isset($existingErrors["{$attribute}.{$index}.product_category_id"])) {
                continue;
            }

            if ($this->coveredByAnotherRow($index, $row, $rows)) {
                $errors["{$attribute}.{$index}.product_category_id"] = __(self::REDUNDANT_LINE_MESSAGE);
            }
        }

        return $errors;
    }

    /**
     * @param  array{business_function_id: int, product_category_id: int|null}  $row
     * @param  array<int, array{business_function_id: int, product_category_id: int|null}>  $rows
     */
    private function coveredByAnotherRow(int $ownIndex, array $row, array $rows): bool
    {
        $ancestors = $this->ancestorIdSet((int) $row['product_category_id']);

        foreach ($rows as $otherIndex => $other) {
            if ($otherIndex === $ownIndex || $other['business_function_id'] !== $row['business_function_id']) {
                continue;
            }

            if ($other['product_category_id'] === null || isset($ancestors[$other['product_category_id']])) {
                return true;
            }
        }

        return false;
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
     * $categoryId's ancestor ids (excludes itself), walked off the same
     * memoized `parentIdMap()` projection every lookup below shares.
     *
     * @return array<int, true>
     */
    private function ancestorIdSet(int $categoryId): array
    {
        $parentIdMap = $this->hierarchy->parentIdMap();
        $ancestors = [];
        $currentId = $parentIdMap[$categoryId] ?? null;
        $depth = 0;

        while ($currentId !== null && $depth < 100) {
            $ancestors[$currentId] = true;
            $currentId = $parentIdMap[$currentId] ?? null;
            $depth++;
        }

        return $ancestors;
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
