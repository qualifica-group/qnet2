<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;

/**
 * The ONE batch reading of "which Sede operativa does a campaign belong to"
 * (spec 0113, D-1/D-3): `campaigns.operational_site_id`, the campaign's OWN
 * column. Unlike the product lines resolved by CampaignProductCategories,
 * this is NOT a project-first read-through — a campaign linked to a project
 * does not inherit the project's Sede, so there is deliberately no fallback
 * here.
 *
 * Extracted so every assignment surface derives the Sede identically, and
 * never one query per campaign.
 */
final class CampaignOperationalSites
{
    /**
     * campaign id => its Sede id, null included (the column is nullable). A
     * campaign id that does not resolve is ABSENT from the map — the caller
     * decides what a missing campaign means, exactly as it decides what a
     * null Sede means (spec 0113 AC-003: both end up as "no Sede").
     *
     * @param  array<int, int>  $campaignIds
     * @return array<int, int|null>
     */
    public function forCampaigns(array $campaignIds): array
    {
        $campaignIds = array_values(array_unique(array_filter($campaignIds)));

        if ($campaignIds === []) {
            return [];
        }

        $campaigns = Campaign::query()
            ->whereIn('id', $campaignIds)
            ->get(['id', 'operational_site_id']);

        $result = [];

        foreach ($campaigns as $campaign) {
            $result[(int) $campaign->id] = $campaign->operational_site_id === null
                ? null
                : (int) $campaign->operational_site_id;
        }

        return $result;
    }

    /**
     * Single-campaign convenience: the Sede of $campaignId, or null when it
     * is null, unresolvable, or simply carries no Sede.
     */
    public function forCampaign(?int $campaignId): ?int
    {
        if ($campaignId === null) {
            return null;
        }

        return $this->forCampaigns([$campaignId])[$campaignId] ?? null;
    }
}
