<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Enums\QuoteLineType;
use App\Http\Resources\MultiProgrammableQuoteLineResource;
use App\Models\Contract;
use App\Models\QuoteLine;
use App\Services\ProductCategories\CategoryRootResolver;
use Illuminate\Support\Collection;

/**
 * Revenue lines of the selected contracts' offers for the "Programma
 * commessa unica" dialog (spec 0267, D-3), with branch root and eligibility.
 * Three queries regardless of the number of contracts: lines (with product,
 * category, unit, work orders), plus the batched root resolution.
 */
final class MultiProgrammableLinesBuilder
{
    public function __construct(private readonly CategoryRootResolver $roots) {}

    /**
     * @param  Collection<int, Contract>  $contracts  in the order to return
     * @return array<int, MultiProgrammableQuoteLineResource>
     */
    public function build(Collection $contracts): array
    {
        $lines = QuoteLine::query()
            ->whereIn('quote_id', $contracts->pluck('quote_id'))
            ->where('line_type', QuoteLineType::Revenue)
            ->with(['product.category', 'product.unitOfMeasure', 'unitOfMeasure', 'workOrders'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('quote_id');

        $categoryIds = $lines->flatten(1)
            ->map(static fn (QuoteLine $line): ?int => $line->product?->category_id)
            ->filter()
            ->all();
        $rootsByCategory = $this->roots->rootSummariesFor($categoryIds);

        $resources = [];

        foreach ($contracts as $contract) {
            foreach ($lines->get($contract->quote_id, []) as $line) {
                $categoryId = $line->product?->category_id;
                $root = $categoryId !== null ? ($rootsByCategory[$categoryId] ?? null) : null;
                $eligible = $line->product?->category?->multi_quote_work_order === true && $line->workOrders->isEmpty();

                $resources[] = new MultiProgrammableQuoteLineResource($line, $contract->id, $root, $eligible);
            }
        }

        return $resources;
    }
}
