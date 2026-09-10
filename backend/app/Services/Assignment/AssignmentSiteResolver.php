<?php

namespace App\Services\Assignment;

use App\Imports\Leads\LeadRowCampaign;
use App\Models\ImportRunRow;
use App\Models\Lead;
use App\Models\Quote;
use App\Services\Campaigns\CampaignOperationalSites;
use Illuminate\Support\Collection;

/**
 * The Sede that SCOPES the assignment of a record (spec 0113): the Sede half
 * of the "Campagna -> Categoria prodotto -> Sede" combination, sitting next
 * to the competence half resolved by ImportRowCompetence/LeadCompetence/
 * QuoteCompetence. Same shape as those three — batched, one map per domain —
 * so the three assignment surfaces can never disagree on which Sede owns a
 * record.
 *
 * Where the Sede comes from differs per domain, and the difference is a
 * frozen decision, not an implementation detail:
 *   - a staged import row takes it from its CAMPAIGN (D-1): the row's own
 *     `operational_site_id` override is a Review-only edit and no longer
 *     participates in choosing candidates;
 *   - a real lead takes it from the campaign of `leads.campaign_id` (D-3),
 *     not from `leads.operational_site_id`, so a lead answers the same Sede
 *     before and after the import commit;
 *   - an offer takes it from `quotes.operational_site_id` (D-4): the
 *     Opportunity carries no `campaign_id`, so the campaign chain does not
 *     exist on that domain.
 */
final class AssignmentSiteResolver
{
    public function __construct(private readonly CampaignOperationalSites $campaignSites) {}

    /**
     * row id => Sede id, null when the row's campaign is unresolvable or
     * carries no Sede (AC-003). The row's campaign is read through
     * LeadRowCampaign (per-row when a file column is mapped to
     * `campaign_code`, the run's global campaign otherwise, spec 0108 D-7).
     *
     * @param  Collection<int, ImportRunRow>  $rows
     * @param  array<string, mixed>  $globalConfig
     * @return array<int, int|null>
     */
    public function forImportRows(Collection $rows, array $globalConfig): array
    {
        $campaignByRow = [];

        foreach ($rows as $row) {
            $campaignByRow[(int) $row->id] = LeadRowCampaign::resolve($row->mapped_values ?? [], $globalConfig);
        }

        return $this->siteByCampaignOwner($campaignByRow);
    }

    /**
     * lead id => the Sede of the lead's own campaign (D-3), in two queries
     * whatever the size of the batch (AC-004).
     *
     * @param  array<int, int>  $leadIds
     * @return array<int, int|null>
     */
    public function forLeads(array $leadIds): array
    {
        if ($leadIds === []) {
            return [];
        }

        $campaignByLead = Lead::query()
            ->whereIn('id', $leadIds)
            ->get(['id', 'campaign_id'])
            ->mapWithKeys(static fn (Lead $lead): array => [
                (int) $lead->id => $lead->campaign_id === null ? null : (int) $lead->campaign_id,
            ])
            ->all();

        return $this->siteByCampaignOwner($campaignByLead);
    }

    /**
     * quote id => `quotes.operational_site_id` (D-4), null included, in one
     * query.
     *
     * @param  array<int, int>  $quoteIds
     * @return array<int, int|null>
     */
    public function forQuotes(array $quoteIds): array
    {
        if ($quoteIds === []) {
            return [];
        }

        return Quote::query()
            ->whereIn('id', $quoteIds)
            ->get(['id', 'operational_site_id'])
            ->mapWithKeys(static fn (Quote $quote): array => [
                (int) $quote->id => $quote->operational_site_id === null ? null : (int) $quote->operational_site_id,
            ])
            ->all();
    }

    /**
     * Fold "record id => campaign id" onto "record id => Sede id" with ONE
     * campaign query for the whole batch — the shared tail of the two
     * campaign-driven domains.
     *
     * @param  array<int, int|null>  $campaignByRecord
     * @return array<int, int|null>
     */
    private function siteByCampaignOwner(array $campaignByRecord): array
    {
        $siteByCampaign = $this->campaignSites->forCampaigns(array_values(array_filter($campaignByRecord)));

        $siteByRecord = [];

        foreach ($campaignByRecord as $recordId => $campaignId) {
            $siteByRecord[$recordId] = $campaignId === null ? null : ($siteByCampaign[$campaignId] ?? null);
        }

        return $siteByRecord;
    }
}
