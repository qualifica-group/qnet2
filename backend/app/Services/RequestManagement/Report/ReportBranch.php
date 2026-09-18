<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report;

/**
 * One resolved report branch (spec 0106, spec 0131): the reportable
 * category's id as key, its own display name (the "Categoria" cell) and the
 * full subtree of category ids a request is matched against (the category +
 * every descendant).
 *
 * `columnCategoryIds` (user directive 2026-09-18) maps each ACTIVE indicator
 * column to the category ids it is computed on; a column missing from the
 * map is 0 for this branch. A plain branch maps every active column to its
 * own subtree ({@see self::withColumns()}); the dashboard's overall summary
 * maps each column to the union of the branches where it is active, so a
 * column never counts requests of a category it does not belong to.
 *
 * `depth`/`parentKey` (user directive 2026-09-18): 0/null for a reportable
 * category, n and the parent branch's key for its n-th level subcategory —
 * the "Sottocategorie" picker lists the descendants of the chosen ones.
 */
final class ReportBranch
{
    /**
     * @param  array<int, int>  $categoryIds
     * @param  array<string, array<int, int>>  $columnCategoryIds
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly array $categoryIds,
        public readonly array $columnCategoryIds,
        public readonly int $depth = 0,
        public readonly ?string $parentKey = null,
    ) {}

    /**
     * @param  array<int, int>  $categoryIds
     * @param  array<int, string>  $columns
     */
    public static function withColumns(string $key, string $label, array $categoryIds, array $columns, int $depth = 0, ?string $parentKey = null): self
    {
        return new self($key, $label, $categoryIds, array_fill_keys($columns, $categoryIds), $depth, $parentKey);
    }

    /**
     * @return array<int, int>|null null when the column is not active here
     */
    public function categoryIdsFor(string $column): ?array
    {
        return $this->columnCategoryIds[$column] ?? null;
    }
}
