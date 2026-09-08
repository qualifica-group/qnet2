<?php

namespace App\Imports\Leads;

use App\Imports\Recognition\CampaignRecognizer;

/**
 * The ONE reading of "which campaign does this staged row belong to"
 * (spec 0108, D-7): the row's own resolved `campaign_id` when the run takes
 * its campaign from a file column, the run's global `campaign_id` otherwise.
 *
 * Shared by LeadRowPersister (which Lead is written), LeadDuplicateMatcher
 * (which campaign a duplicate is looked for on) and the product-coherence
 * request rules, so the three can never drift apart on the answer.
 */
final class LeadRowCampaign
{
    /**
     * @param  array<string, mixed>  $mapped  the row's values after recognizers ran
     * @param  array<string, mixed>  $globalConfig  the run's configuration-step values
     */
    public static function resolve(array $mapped, array $globalConfig): ?int
    {
        return self::id($mapped, CampaignRecognizer::CAMPAIGN_ID_FIELD)
            ?? self::id($globalConfig, CampaignRecognizer::CAMPAIGN_ID_FIELD);
    }

    /**
     * Whether the run resolves its campaign PER ROW — true as soon as a file
     * column is mapped to `campaign_code`, so the key exists on every staged
     * row even when that row's cell is empty (StagedRowBuilder::applyMapping).
     *
     * @param  array<string, string>  $columnMapping
     */
    public static function isPerRow(array $columnMapping): bool
    {
        return in_array(CampaignRecognizer::CAMPAIGN_CODE_FIELD, array_values($columnMapping), true);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private static function id(array $values, string $key): ?int
    {
        $value = $values[$key] ?? null;

        return $value === null || $value === '' ? null : (int) $value;
    }
}
