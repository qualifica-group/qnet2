<?php

namespace App\Services\Assignment;

use App\Imports\Leads\LeadRowCampaign;
use App\Models\ImportRunRow;
use App\Models\Product;
use App\Services\Campaigns\CampaignProductCategories;
use Illuminate\Support\Collection;

/**
 * The product categories a STAGED import row demands of its operator (spec
 * 0110, INV-1): the categories of the row's effective "Prodotti di
 * interesse" — its own `product_ids` override, else the run's global ones —
 * and, only when the row carries none, the effective categories of its own
 * campaign (per-row when the run reads campaigns from a file column, spec
 * 0108 D-6, the run's global campaign otherwise).
 *
 * Batched by construction: one query for every product across every row, one
 * for every distinct campaign, whatever the size of the run.
 */
final class ImportRowCompetence
{
    public function __construct(private readonly CampaignProductCategories $campaignCategories) {}

    /**
     * row id => required category ids. Rows demanding nothing map to an
     * empty array, which INV-4a reads as "constrains nobody".
     *
     * @param  Collection<int, ImportRunRow>  $rows
     * @param  array<string, mixed>  $globalConfig
     * @return array<int, array<int, int>>
     */
    public function requiredByRow(Collection $rows, array $globalConfig): array
    {
        // Step 1: read each row's effective products and campaign once.
        $productIdsByRow = [];
        $campaignByRow = [];

        foreach ($rows as $row) {
            $productIds = $this->effectiveProductIds($row, $globalConfig);
            $productIdsByRow[(int) $row->id] = $productIds;

            $campaignByRow[(int) $row->id] = $productIds === []
                ? LeadRowCampaign::resolve($row->mapped_values ?? [], $globalConfig)
                : null;
        }

        // Step 2: resolve both sides in batch, never per row.
        $categoryByProduct = $this->categoryByProduct(array_merge(...array_values($productIdsByRow) ?: [[]]));
        $categoriesByCampaign = $this->campaignCategories->forCampaigns(array_values(array_filter($campaignByRow)));

        // Step 3: fold each row onto its own requirement.
        $required = [];

        foreach ($productIdsByRow as $rowId => $productIds) {
            $required[$rowId] = $productIds === []
                ? ($categoriesByCampaign[$campaignByRow[$rowId]] ?? [])
                : $this->categoriesOf($productIds, $categoryByProduct);
        }

        return $required;
    }

    /**
     * The UNION of the rows' requirements (spec 0110 D-14): what the bulk
     * picker filters on, where one operator is offered for a whole
     * selection.
     *
     * @param  Collection<int, ImportRunRow>  $rows
     * @param  array<string, mixed>  $globalConfig
     * @return array<int, int>
     */
    public function requiredUnion(Collection $rows, array $globalConfig): array
    {
        $union = array_merge(...array_values($this->requiredByRow($rows, $globalConfig)) ?: [[]]);

        sort($union);

        return array_values(array_unique($union));
    }

    /**
     * The row's own `product_ids` override when set, the run's global ones
     * otherwise — the same three-state reading LeadRowPersister applies at
     * persist time (spec 0094 D-4: null defers to the global value, `[]` is
     * an explicit "this row carries none").
     *
     * @param  array<string, mixed>  $globalConfig
     * @return array<int, int>
     */
    private function effectiveProductIds(ImportRunRow $row, array $globalConfig): array
    {
        $productIds = $row->product_ids ?? $globalConfig['product_ids'] ?? [];

        return array_map(intval(...), array_values((array) $productIds));
    }

    /**
     * @param  array<int, int>  $productIds
     * @return array<int, int> product id => category id
     */
    private function categoryByProduct(array $productIds): array
    {
        $productIds = array_values(array_unique($productIds));

        if ($productIds === []) {
            return [];
        }

        return Product::query()
            ->whereIn('id', $productIds)
            ->pluck('category_id', 'id')
            ->map(intval(...))
            ->all();
    }

    /**
     * @param  array<int, int>  $productIds
     * @param  array<int, int>  $categoryByProduct
     * @return array<int, int>
     */
    private function categoriesOf(array $productIds, array $categoryByProduct): array
    {
        $categoryIds = [];

        foreach ($productIds as $productId) {
            if (isset($categoryByProduct[$productId])) {
                $categoryIds[] = $categoryByProduct[$productId];
            }
        }

        return array_values(array_unique($categoryIds));
    }
}
