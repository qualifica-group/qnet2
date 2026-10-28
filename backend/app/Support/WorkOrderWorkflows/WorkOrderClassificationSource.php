<?php

declare(strict_types=1);

namespace App\Support\WorkOrderWorkflows;

use App\Enums\QuoteLineType;
use App\Models\QuoteLine;
use App\Models\WorkOrder;
use App\Services\ProductCategories\CategoryHierarchy;
use Illuminate\Support\Collection;

/**
 * The "funzione aziendale" + "categoria prodotto" classification a WorkOrder
 * is matched on by the workflow criteria (spec 0322, D-8): the products of
 * the REVENUE lines scheduled in the commessa (`quoteLines`, never COST
 * ones). A commessa with no REVENUE line has no classification at all, so no
 * criterion matches and the global set applies.
 *
 * The business function is the EFFECTIVE one of each category (own or
 * inherited from the nearest ancestor, CategoryHierarchy), unlike
 * QuoteClassificationSource, which reads the category's own column only
 * (reported separately, spec 0322 "out").
 *
 * Assumes the caller already eager-loaded `quoteLines.product` — the
 * contract WorkOrderWorkflowResolver::resolve() enforces with its own
 * `loadMissing()`, so nothing here lazy-loads under preventLazyLoading.
 */
final class WorkOrderClassificationSource
{
    /**
     * Effective business function per category id, read once per instance
     * (see forgetCaches()).
     *
     * @var array<int, array{id: int, name: string}|null>|null
     */
    private ?array $functionsByCategory = null;

    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    /**
     * The distinct product categories $workOrder is classified by.
     *
     * @return array<int, int>
     */
    public function categoryIds(WorkOrder $workOrder): array
    {
        return $this->distinctIds(
            $this->revenueLines($workOrder)->map(static fn (QuoteLine $line): ?int => $line->product?->category_id),
        );
    }

    /**
     * The distinct EFFECTIVE business functions of those categories.
     *
     * @return array<int, int>
     */
    public function businessFunctionIds(WorkOrder $workOrder): array
    {
        $functions = $this->functionsByCategory();

        return $this->distinctIds(
            collect($this->categoryIds($workOrder))->map(static fn (int $categoryId): ?int => $functions[$categoryId]['id'] ?? null),
        );
    }

    /**
     * Drops the memoized category tree projection so the next read sees the
     * database as it is (a reparented or re-assigned category).
     */
    public function forgetCaches(): void
    {
        $this->functionsByCategory = null;
    }

    /**
     * @return Collection<int, QuoteLine>
     */
    private function revenueLines(WorkOrder $workOrder): Collection
    {
        return $workOrder->quoteLines->filter(
            static fn (QuoteLine $line): bool => $line->line_type === QuoteLineType::Revenue,
        );
    }

    /**
     * @return array<int, array{id: int, name: string}|null>
     */
    private function functionsByCategory(): array
    {
        return $this->functionsByCategory ??= $this->hierarchy->effectiveBusinessFunctionSummaries();
    }

    /**
     * @param  Collection<int, int|null>  $ids
     * @return array<int, int>
     */
    private function distinctIds(Collection $ids): array
    {
        return $ids
            ->filter()
            ->unique()
            ->values()
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }
}
