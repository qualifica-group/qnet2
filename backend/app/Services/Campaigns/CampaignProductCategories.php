<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;

/**
 * The ONE batch reading of "which product categories does a campaign
 * classify itself with" (spec 0094, BR-2: project-first — a campaign under a
 * project inherits the project's product lines, otherwise it carries its
 * own). Extracted so the import's product-coherence rule and the assignment
 * competence (spec 0110) resolve it identically, and never one query per
 * campaign.
 */
final class CampaignProductCategories
{
    /**
     * campaign id => its effective product category ids. A campaign id that
     * does not resolve maps to an EMPTY array — the caller decides what that
     * means (coherence treats it as "covers nothing", competence as "demands
     * nothing", INV-4a).
     *
     * @param  array<int, int>  $campaignIds
     * @return array<int, array<int, int>>
     */
    public function forCampaigns(array $campaignIds): array
    {
        $campaignIds = array_values(array_unique(array_filter($campaignIds)));

        if ($campaignIds === []) {
            return [];
        }

        $campaigns = Campaign::query()
            ->with(['productLines', 'project.productLines'])
            ->whereIn('id', $campaignIds)
            ->get();

        $result = [];

        foreach ($campaigns as $campaign) {
            $lines = $campaign->project !== null ? $campaign->project->productLines : $campaign->productLines;

            $result[(int) $campaign->id] = $lines->pluck('product_category_id')->map(intval(...))->values()->all();
        }

        return $result;
    }

    /**
     * Single-campaign convenience: the effective categories of $campaignId,
     * or an empty array when it is null or unresolvable.
     *
     * @return array<int, int>
     */
    public function forCampaign(?int $campaignId): array
    {
        if ($campaignId === null) {
            return [];
        }

        return $this->forCampaigns([$campaignId])[$campaignId] ?? [];
    }
}
