<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report;

/**
 * One resolved report branch (spec 0106, spec 0131): the reportable
 * category's id as key, its own display name (the "Categoria" cell) and the
 * full subtree of category ids a request is matched against (the category +
 * every descendant). Every indicator of
 * `config('request-management-report.indicator_columns')` is computed for
 * every branch (spec 0131 — no per-branch applicability list any more).
 */
final class ReportBranch
{
    /**
     * @param  array<int, int>  $categoryIds
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly array $categoryIds,
    ) {}
}
