<?php

declare(strict_types=1);

namespace App\Actions\Leads;

use App\Exceptions\Leads\BulkConversionBlockedException;
use App\Models\Lead;
use App\Models\User;
use App\Services\Opportunities\LeadOpportunityDefaultsResolver;
use App\Services\Opportunities\RegistryOpenOpportunityGuard;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bulk Lead -> Opportunity conversion (spec 0071): the mass action behind
 * POST /api/leads/convert-to-opportunities.
 *
 * Owns only the BATCH concerns — convertibility pre-check, atomicity, order.
 * The conversion of a single lead stays entirely in ConvertLeadToOpportunity
 * (the same class the creation checkbox and the import auto-convert call), and
 * the "is this lead convertible" predicate stays LeadOpportunityDefaultsResolver's,
 * so this class cannot drift from either.
 *
 * All-or-nothing (D-1): every blocker is collected and thrown BEFORE the first
 * write. Reporting them by attempting the batch and rolling back would only
 * ever surface the first failure, and the dialog needs the full list.
 */
final class ConvertLeadsToOpportunities
{
    public function __construct(
        private readonly ConvertLeadToOpportunity $converter,
        private readonly LeadOpportunityDefaultsResolver $defaultsResolver,
        private readonly RegistryOpenOpportunityGuard $openOpportunityGuard,
    ) {}

    /**
     * @param  array<int, int>  $leadIds
     * @param  ?User  $actor  propagated to the per-lead conversion for the
     *                        assignment notifications (spec 0081)
     * @return array<int, int> ids of the created Opportunities, in lead id order
     */
    public function handle(array $leadIds, ?User $actor = null): array
    {
        // Step 1: load the batch once, eager-loading exactly what the
        // per-lead conversion reads, so neither the pre-check nor the
        // conversion loop degenerates into an N+1.
        $leads = $this->loadLeads($leadIds);

        // Step 2: collect EVERY offending lead, never stopping at the first.
        $blockers = $this->blockers($leads);

        // Step 3: one blocker rejects the whole batch, before any write.
        if ($blockers !== []) {
            throw new BulkConversionBlockedException(
                $blockers,
                sprintf('%d of the selected leads cannot be converted to an opportunity.', count($blockers)),
            );
        }

        // Step 4: the batch is atomic — a failure on any lead rolls back the
        // Opportunities already created for the previous ones.
        return DB::transaction(fn (): array => $leads
            ->map(fn (Lead $lead): int => $this->converter->handle($lead, $actor)->id)
            ->all());
    }

    /**
     * @param  array<int, int>  $leadIds
     * @return Collection<int, Lead>
     */
    private function loadLeads(array $leadIds): Collection
    {
        /** @var Collection<int, Lead> $leads */
        $leads = Lead::query()
            ->with(LeadOpportunityDefaultsResolver::REQUIRED_RELATIONS)
            ->whereIn('id', $leadIds)
            ->orderBy('id')
            ->get();

        return $leads;
    }

    /**
     * @param  Collection<int, Lead>  $leads
     * @return array<int, array{id: int, reason: string}>
     */
    private function blockers(Collection $leads): array
    {
        $blockers = [];

        // One query for the whole selection, never one per lead.
        $openByRegistry = $this->openOpportunityGuard->openOpportunityIdsByRegistry(
            $leads->pluck('registry_id')->filter()->map(intval(...))->unique()->values()->all(),
        );

        /** @var array<int, true> $claimedRegistries */
        $claimedRegistries = [];

        foreach ($leads as $lead) {
            $registryId = (int) $lead->registry_id;

            $reason = $this->blockerFor($lead)
                ?? $this->registryBlockerFor($registryId, $openByRegistry, $claimedRegistries);

            if ($reason !== null) {
                $blockers[] = ['id' => $lead->id, 'reason' => $reason];

                continue;
            }

            $claimedRegistries[$registryId] = true;
        }

        return $blockers;
    }

    /**
     * User directive 2026-08-31: an anagrafica may carry one open opportunity
     * at a time. Two convertible leads of the SAME anagrafica in one batch are
     * the same violation — the first would create the opportunity the second
     * then collides with — so a registry already claimed EARLIER IN THIS BATCH
     * blocks exactly like one that is already open in the database.
     *
     * @param  array<int, int>  $openByRegistry
     * @param  array<int, true>  $claimedRegistries
     */
    private function registryBlockerFor(int $registryId, array $openByRegistry, array $claimedRegistries): ?string
    {
        if (isset($openByRegistry[$registryId]) || isset($claimedRegistries[$registryId])) {
            return BulkConversionBlockedException::BLOCKER_REGISTRY_HAS_OPEN_OPPORTUNITY;
        }

        return null;
    }

    /**
     * Why this lead cannot be converted, or null when it can. A lead with no
     * campaign at all derives nothing either, so it falls in the same bucket
     * as one whose campaign lacks a business function or product category.
     */
    private function blockerFor(Lead $lead): ?string
    {
        if ($lead->opportunity !== null) {
            return BulkConversionBlockedException::BLOCKER_ALREADY_CONVERTED;
        }

        $campaign = $lead->campaign;

        if ($campaign === null || ! $this->defaultsResolver->campaignDerivesProductLine($campaign)) {
            return BulkConversionBlockedException::BLOCKER_NOT_DERIVABLE;
        }

        return null;
    }
}
