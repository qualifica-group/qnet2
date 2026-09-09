<?php

namespace App\Services\Assignment;

use App\Models\Lead;
use App\Services\Campaigns\CampaignProductCategories;

/**
 * The import row's counterpart for REAL leads (spec 0110, INV-1): the
 * categories of the lead's own "Prodotti di interesse" (`lead_product`) and,
 * only when it has none, the effective categories of its campaign — the same
 * reading ImportRowCompetence applies to a staged row, so a lead assigned
 * before and after the import commit answers identically.
 */
final class LeadCompetence
{
    public function __construct(private readonly CampaignProductCategories $campaignCategories) {}

    /**
     * lead id => required category ids, batched: two queries whatever the
     * size of the batch.
     *
     * @param  array<int, int>  $leadIds
     * @return array<int, array<int, int>>
     */
    public function requiredByLead(array $leadIds): array
    {
        if ($leadIds === []) {
            return [];
        }

        $leads = Lead::query()
            ->with('productsOfInterest:id,category_id')
            ->whereIn('id', $leadIds)
            ->get(['id', 'campaign_id']);

        $categoriesByCampaign = $this->campaignCategories->forCampaigns(
            $leads->pluck('campaign_id')->filter()->map(intval(...))->all(),
        );

        $required = [];

        foreach ($leads as $lead) {
            $ownCategoryIds = $lead->productsOfInterest
                ->pluck('category_id')
                ->filter()
                ->map(intval(...))
                ->unique()
                ->values()
                ->all();

            $required[(int) $lead->id] = $ownCategoryIds !== []
                ? $ownCategoryIds
                : ($categoriesByCampaign[(int) $lead->campaign_id] ?? []);
        }

        return $required;
    }

    /**
     * The UNION of the leads' requirements (spec 0110 D-14).
     *
     * @param  array<int, int>  $leadIds
     * @return array<int, int>
     */
    public function requiredUnion(array $leadIds): array
    {
        $union = array_merge(...array_values($this->requiredByLead($leadIds)) ?: [[]]);

        sort($union);

        return array_values(array_unique($union));
    }
}
