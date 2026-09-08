<?php

namespace App\Support\Import;

use App\Imports\Recognition\CampaignRecognizer;
use App\Models\Campaign;

/**
 * Turns a validated `campaign_id` PATCH block (spec 0108, D-5 — the campaign
 * an operator picked in the review grid) into the SAME mapped_values keys
 * CampaignRecognizer would have produced from the file's code. Mirrors
 * GeoPinResolver: the pin IS the resolution, so StagedRowReviser replays the
 * staging pipeline with CampaignRecognizer skipped and the operator's choice
 * is never re-derived from (nor contradicted by) the raw file value.
 *
 * UpdateImportRowRequest has already checked the id exists, so the lookup
 * below always finds its campaign.
 */
final class CampaignPinResolver
{
    /**
     * @return array<string, string|int>
     */
    public function pin(int $campaignId): array
    {
        /** @var string|null $code */
        $code = Campaign::query()->whereKey($campaignId)->value('code');

        return [
            CampaignRecognizer::CAMPAIGN_ID_FIELD => $campaignId,
            CampaignRecognizer::CAMPAIGN_CODE_FIELD => (string) $code,
        ];
    }
}
