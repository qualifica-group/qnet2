<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report;

use App\Models\ProductCategory;
use App\Services\ProductCategories\CategoryHierarchy;
use RuntimeException;

/**
 * Resolves the six report branches (spec 0106) from
 * config/request-management-report.php: each root category is looked up by
 * NAME (bound by identity to QualificaCatalogSeeder::CATALOG, never an id),
 * then expanded to its full subtree via CategoryHierarchy::descendantIds().
 *
 * `descendantIds()` is neither memoized nor scoped in the container
 * (constraints, backend.md): CategoryHierarchy is injected ONCE here, and
 * resolve() is meant to be called ONCE per request by the caller
 * (RequestManagementReportGenerator) — never re-resolved per row.
 */
final class ReportBranchResolver
{
    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    /**
     * @return array<int, ReportBranch>
     */
    public function resolve(): array
    {
        $branches = [];

        /** @var array<string, array{category: array{parent: string|null, name: string}, columns: array<int, string>}> $configured */
        $configured = (array) config('request-management-report.branches');

        foreach ($configured as $key => $definition) {
            $branches[] = $this->resolveBranch((string) $key, $definition);
        }

        return $branches;
    }

    /**
     * @param  array{category: array{parent: string|null, name: string}, columns: array<int, string>}  $definition
     */
    private function resolveBranch(string $key, array $definition): ReportBranch
    {
        $root = $this->findRoot($definition['category']['parent'], $definition['category']['name']);
        $categoryIds = array_merge([$root->id], $this->hierarchy->descendantIds($root->id));

        return new ReportBranch($key, $root->name, $categoryIds, $definition['columns']);
    }

    private function findRoot(?string $parentName, string $name): ProductCategory
    {
        $query = ProductCategory::query()->where('name', $name);

        $category = $parentName === null
            ? $query->whereNull('parent_id')->first()
            : $query->whereHas('parent', fn ($ancestor) => $ancestor->where('name', $parentName))->first();

        if ($category === null) {
            throw new RuntimeException("Request management report: category not found for branch [{$parentName}/{$name}].");
        }

        return $category;
    }
}
