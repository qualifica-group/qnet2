<?php

declare(strict_types=1);

namespace App\Services\Opportunities;

use App\Enums\CategoryManagementMode;
use App\Models\Opportunity;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * User directive 2026-08-31: an anagrafica may carry only ONE opportunity
 * still running at a time — creating a second one while an open one exists is
 * refused, and the refusal names the opportunity that blocks it so the client
 * can link straight to it.
 *
 * "Open" is NOT a column: the Opportunity's status is COMPUTED from its quotes
 * (spec 0082/0083, OpportunityStatusResolver). The predicate therefore reuses
 * OpportunityStatusScope — the query side of that same computation — with
 * ACTIVE_GROUPS, so "aperta" here means exactly what the badge and the table
 * filter mean: at least one quote outside the two terminal outcomes
 * (`closed_won`/`closed_lost`), or no quote at all (which displays the `open`
 * row of the workflow its own product category resolves to).
 *
 * User directive 2026-08-31 (refinement): an open opportunity managed on a
 * SINGLE product category does NOT block. That mode is the one-shot deal
 * (spec 0077: one product line, one offer, one product row) — it describes a
 * closed scope, not the anagrafica's whole running business — so the
 * anagrafica must stay free to open another opportunity next to it. Only an
 * open opportunity in `multiple` mode (or one whose mode is indeterminate,
 * which is never PROVEN to be single) blocks.
 *
 * Enforced inside OpportunityService::create(), the single write point every
 * creation path funnels through (form, lead conversion, import auto-convert),
 * so no path can bypass it. The bulk conversion pre-checks the same predicate
 * (ConvertLeadsToOpportunities) only to report every offending lead at once.
 */
final class RegistryOpenOpportunityGuard
{
    /**
     * User directive 2026-08-31: the refusal must POINT SOMEWHERE — the open
     * opportunity is where the offer belongs, so the message says to add it
     * there rather than to close anything (the client turns the id below into
     * a one-click "add the offer on it" link).
     */
    public const string OPEN_OPPORTUNITY_MESSAGE = 'This registry already has an open opportunity: :opportunity. Add the offer to that one instead of creating a duplicate.';

    /**
     * Carried alongside the `registry_id` field error so the client can link
     * to the blocking opportunity (the URL is built client-side: the API never
     * emits frontend paths).
     */
    public const string EXISTING_OPPORTUNITY_KEY = 'existing_opportunity_id';

    public function __construct(private readonly OpportunityProductLineCoverage $coverage) {}

    /**
     * @throws ValidationException when $registryId already owns a blocking open opportunity
     */
    public function assertNoOpenOpportunity(int $registryId): void
    {
        $existing = $this->openOpportunityFor($registryId);

        if ($existing === null) {
            return;
        }

        throw ValidationException::withMessages([
            'registry_id' => [__(self::OPEN_OPPORTUNITY_MESSAGE, ['opportunity' => $existing->name])],
            self::EXISTING_OPPORTUNITY_KEY => [(string) $existing->id],
        ]);
    }

    /**
     * The blocking opportunity of every registry among $registryIds that has
     * one, keyed by `registry_id` — the batch form of openOpportunityFor(),
     * for callers pre-checking a whole selection.
     *
     * @param  array<int, int>  $registryIds
     * @return array<int, int> registry_id => opportunity id
     */
    public function openOpportunityIdsByRegistry(array $registryIds): array
    {
        /** @var array<int, int> $map */
        $map = $this->blockingOpportunities($registryIds)
            ->mapWithKeys(static fn (Opportunity $opportunity): array => [
                (int) $opportunity->registry_id => (int) $opportunity->id,
            ])
            ->all();

        return $map;
    }

    /**
     * The oldest open opportunity of $registryId that blocks a new one, or
     * null when the anagrafica has none (no open opportunity at all, or only
     * single-mode ones).
     */
    public function openOpportunityFor(int $registryId): ?Opportunity
    {
        return $this->blockingOpportunities([$registryId])->first();
    }

    /**
     * The open opportunities of $registryIds that actually block, in id order:
     * the single-mode ones are dropped, being one-shot deals rather than the
     * anagrafica's running business.
     *
     * @param  array<int, int>  $registryIds
     * @return Collection<int, Opportunity>
     */
    private function blockingOpportunities(array $registryIds): Collection
    {
        if ($registryIds === []) {
            /** @var Collection<int, Opportunity> $empty */
            $empty = new Collection;

            return $empty;
        }

        $opportunities = Opportunity::query()->whereIn('registry_id', $registryIds);

        // The quote-less branch resolves a workflow per row (it is the row's
        // own product category that decides it), so it is handed the SAME
        // registry restriction instead of every quote-less opportunity there
        // is — this runs on every opportunity creation.
        OpportunityStatusScope::whereGroupIn(
            $opportunities,
            OpportunityStatusScope::ACTIVE_GROUPS,
            Opportunity::query()->whereIn('registry_id', $registryIds),
        );

        return $opportunities
            ->orderBy('id')
            ->get()
            ->reject(fn (Opportunity $opportunity): bool => $this->coverage->managementModeOf($opportunity) === CategoryManagementMode::Single)
            ->values();
    }
}
