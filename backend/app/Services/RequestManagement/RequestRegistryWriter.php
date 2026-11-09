<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\Models\Contract;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Registry;
use Illuminate\Validation\ValidationException;

/**
 * The client select of the work panel (decisione utente 2026-11-09): moves a
 * request onto another anagrafica. The anagrafica lives on the Opportunity, so
 * the change applies to every offer of that opportunity.
 *
 * Two states refuse it: a contract on any of those offers, because a contract
 * has no client of its own and would silently change holder, and a registry
 * inherited from a lead, which UpdateOpportunityRequest keeps immutable
 * (spec 0040, BR-2).
 */
final class RequestRegistryWriter
{
    public const string LOCKED_BY_CONTRACT = 'contract';

    public const string LOCKED_BY_LEAD = 'lead';

    /**
     * Why $opportunity cannot change client, or null when it can. Exposed on
     * the panel so the select is read-only under the same rule that refuses
     * the write.
     */
    public function lockReason(Opportunity $opportunity): ?string
    {
        if (Contract::query()->whereIn('quote_id', $opportunity->quotes()->select('id'))->exists()) {
            return self::LOCKED_BY_CONTRACT;
        }

        if ($opportunity->lead_id !== null
            && Lead::query()->whereKey($opportunity->lead_id)->whereNotNull('registry_id')->exists()) {
            return self::LOCKED_BY_LEAD;
        }

        return null;
    }

    /**
     * Mutates $opportunity IN MEMORY (the caller saves, and the automatic
     * activity log records the diff): the fiscal-identity gate of a positive
     * close in the same request has to read the new client's card.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException the request's client is locked
     */
    public function apply(Opportunity $opportunity, array $data): void
    {
        if (! array_key_exists('registry_id', $data) || (int) $data['registry_id'] === (int) $opportunity->registry_id) {
            return;
        }

        // Step 1: the two states that keep the client frozen.
        $this->assertNotLocked($opportunity);

        // Step 2: a referent belongs to a registry (spec 0040, BR-4), so one
        // the new client does not share cannot stay on the record.
        $registryId = (int) $data['registry_id'];

        if ($opportunity->referent_id !== null && ! $this->registryHasReferent($registryId, $opportunity->referent_id)) {
            $opportunity->referent_id = null;
            $opportunity->unsetRelation('referent');
        }

        // Step 3: the new client, with the stale relation dropped.
        $opportunity->registry_id = $registryId;
        $opportunity->unsetRelation('registry');
    }

    /**
     * @throws ValidationException
     */
    private function assertNotLocked(Opportunity $opportunity): void
    {
        $reason = $this->lockReason($opportunity);

        if ($reason !== null) {
            throw ValidationException::withMessages([
                'registry_id' => [__("request-management.registry_locked_by_{$reason}")],
            ]);
        }
    }

    private function registryHasReferent(int $registryId, int $referentId): bool
    {
        return Registry::query()
            ->whereKey($registryId)
            ->whereHas('referents', static fn ($query) => $query->whereKey($referentId))
            ->exists();
    }
}
