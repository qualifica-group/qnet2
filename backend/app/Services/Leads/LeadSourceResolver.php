<?php

namespace App\Services\Leads;

use App\Models\Campaign;

/**
 * The ONE reading of "which Fonte does this lead get" (spec 0176, D-2/D-5):
 * its own source when given, otherwise the one its campaign names. Shared by
 * the lead FormRequests (whether `source_id` is still required), LeadService
 * (what is persisted) and the import row validator, so the three never drift.
 */
final class LeadSourceResolver
{
    public function resolve(?int $sourceId, ?int $campaignId): ?int
    {
        if ($sourceId !== null || $campaignId === null) {
            return $sourceId;
        }

        $campaignSourceId = Campaign::query()->whereKey($campaignId)->value('source_id');

        return $campaignSourceId === null ? null : (int) $campaignSourceId;
    }
}
