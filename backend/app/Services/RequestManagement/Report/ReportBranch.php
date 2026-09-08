<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report;

/**
 * One resolved report branch (spec 0106): the root category's own display
 * name (the "Categoria" cell), the full subtree of category ids a request
 * is matched against (root + CategoryHierarchy::descendantIds), and the
 * applicability allow-list of column keys for this branch — used only to
 * decide WHICH indicators are actually computed (avoids querying columns
 * the branch has nothing to say about); it no longer affects the CSV cell
 * itself, which is always numeric (D-15, rev-2, overrides the former D-9
 * empty-cell distinction).
 */
final class ReportBranch
{
    /**
     * @param  array<int, int>  $categoryIds
     * @param  array<int, string>  $columns
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly array $categoryIds,
        public readonly array $columns,
    ) {}
}
