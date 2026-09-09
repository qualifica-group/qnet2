<?php

namespace App\Imports\Leads;

use App\Models\ImportRunRow;
use App\Services\Campaigns\CampaignProductCategories;
use App\Services\Opportunities\ProductCategoryCoherence;
use Illuminate\Support\Collection;

/**
 * Validates a submitted "Prodotti di interesse" id set against a Campaign's
 * EFFECTIVE product categories (spec 0094, D-4/D-5), reused by BOTH the
 * import wizard's global configuration step (ConfigureImportRequest,
 * AC-051) and the per-row override (UpdateImportRowRequest, AC-054) — the
 * SAME coherence rule App\Services\Leads\LeadProductInterestWriter applies
 * at persist time (App\Services\Opportunities\ProductCategoryCoherence,
 * LEAD_MESSAGE), never re-implemented here: this class only resolves WHICH
 * categories a campaign classifies itself with (delegated to
 * CampaignProductCategories, the shared project-first resolver of BR-2)
 * before handing off to that one shared rule for the actual "is it covered"
 * check.
 */
final class LeadImportProductCoherence
{
    public function __construct(
        private readonly ProductCategoryCoherence $coherence,
        private readonly CampaignProductCategories $campaignCategories,
    ) {}

    /**
     * The products of $productIds sitting outside $campaignId's effective
     * categories, as ready-to-display labels — empty when coherent. An
     * unresolved campaign covers ZERO categories, so every submitted product
     * is reported (never silently accepted just because the campaign id
     * itself turned out invalid — that gets its own error from the caller's
     * `exists:` rule).
     *
     * @param  array<int, int>  $productIds
     * @return array<int, string>
     */
    public function offendingProducts(?int $campaignId, array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        return $this->coherence->offendingProducts($productIds, $this->effectiveCategoryIds($campaignId));
    }

    /**
     * The `row_number`s among $rows whose OWN campaign (spec 0108, D-6 —
     * per-row when the run reads campaigns from a file column, the run's
     * global one otherwise) does not cover $productIds. Backs the bulk
     * assignment's all-or-nothing rule: a run can now span several campaigns,
     * so one shared verdict no longer exists and the request must name the
     * rows that would break.
     *
     * Grouped by campaign, so the cost is one coherence resolution per
     * DISTINCT campaign, never one per row.
     *
     * @param  Collection<int, ImportRunRow>  $rows
     * @param  array<string, mixed>  $globalConfig
     * @param  array<int, int>  $productIds
     * @return array<int, int>
     */
    public function offendingRowNumbers(Collection $rows, array $globalConfig, array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $offendingRows = [];

        foreach ($rows->groupBy(static fn (ImportRunRow $row): string => (string) LeadRowCampaign::resolve($row->mapped_values ?? [], $globalConfig)) as $campaignKey => $group) {
            $campaignId = $campaignKey === '' ? null : (int) $campaignKey;

            if ($this->offendingProducts($campaignId, $productIds) === []) {
                continue;
            }

            foreach ($group as $row) {
                $offendingRows[] = $row->row_number;
            }
        }

        sort($offendingRows);

        return $offendingRows;
    }

    /**
     * @param  array<int, string>  $offendingProducts
     */
    public function message(array $offendingProducts): string
    {
        return $this->coherence->message($offendingProducts, ProductCategoryCoherence::LEAD_MESSAGE);
    }

    /**
     * @return array<int, int>
     */
    private function effectiveCategoryIds(?int $campaignId): array
    {
        return $this->campaignCategories->forCampaign($campaignId);
    }
}
