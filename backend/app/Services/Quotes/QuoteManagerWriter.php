<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use App\Support\ManagerPositions;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The SOLE writer of an Offerta's "Gestori Account" (spec 0087, D-4): every
 * write channel that touches `quote_user`/`quotes.operator_id` funnels
 * through sync(), so the two-column coherence obligation (D-3/INV-2) and the
 * bidirectional-sync obligation (D-7/INV-4) each keep exactly one point of
 * truth. `QuoteService` sits at 475/500 lines with no room left (D-4
 * context) — a second reason this lives here rather than inline, alongside
 * the four side effects needing to stay atomic in one place.
 *
 * `$slots` is the SAME ordered, gap-aware shape `ValidatesManagerSlots`
 * already validates (index+1 = position, null = empty slot) that
 * `Opportunity::managers()`/`Registry::managers()` are synced from — the
 * caller (StoreQuoteRequest/UpdateQuoteRequest path) hands it over verbatim.
 *
 * NOTE (D-4): `OpportunityService` performs the equivalent work inline
 * (managerSyncMap()/sync(), :226-229/:295-298/:400-411) — deliberately NOT
 * refactored onto this class here, out of this spec's scope.
 */
final class QuoteManagerWriter
{
    public function __construct(private readonly QuoteManagerSyncMode $syncMode) {}

    /**
     * @param  array<int, int|null>  $slots
     *
     * @throws ValidationException a mapped user is not a Gestore Account of the Offerta's Opportunita' and $promoteToOpportunity is false (D-6)
     */
    public function sync(Quote $quote, array $slots, bool $promoteToOpportunity): void
    {
        $opportunity = $this->resolveOpportunity($quote);
        $syncMap = $this->managerSyncMap($slots);
        $synchronized = $this->syncMode->isSynchronized($opportunity);

        // Step 1: outside synchronized categories, every mapped user must
        // already be a Gestore Account of the Opportunita' (D-6) — reject
        // with a 422 naming them, or promote the missing ones onto the
        // Opportunita's first free slots when the caller opted in. Inert in
        // synchronized mode: Step 4 replaces the Opportunita's own list
        // wholesale, so membership holds by construction (D-7).
        if (! $synchronized) {
            $this->assertMembership($opportunity, $syncMap, $promoteToOpportunity);
        }

        // Step 2: full-replace sync of the Offerta's own pivot.
        $quote->managers()->sync($syncMap);
        $quote->unsetRelation('managers');

        // Step 3: `quotes.operator_id` always mirrors the OPERATOR slot,
        // derived from THIS SAME map — it can never drift from Step 2's
        // pivot (D-3/INV-2).
        $this->writeOperatorId($quote, $syncMap);

        // Step 4: synchronized categories replicate the SAME map onto the
        // Opportunita's own pivot — a direct sync(), never a call back into
        // this method, so the bidirectionality terminates (D-7, R-2).
        if ($synchronized) {
            $opportunity->managers()->sync($syncMap);
            $opportunity->unsetRelation('managers');
        }
    }

    /**
     * Turns the ordered, gap-aware manager slots into the pivot sync map
     * `[userId => ['position' => n]]` (mirrors OpportunityService::
     * managerSyncMap()/RegistryService's own copy verbatim — identical pivot
     * shape).
     *
     * @param  array<int, int|null>  $slots
     * @return array<int, array{position: int}>
     */
    private function managerSyncMap(array $slots): array
    {
        $map = [];

        foreach (array_values($slots) as $index => $userId) {
            if ($userId !== null) {
                $map[$userId] = ['position' => $index + 1];
            }
        }

        return $map;
    }

    /**
     * @param  array<int, array{position: int}>  $syncMap
     *
     * @throws ValidationException see sync()
     */
    private function assertMembership(Opportunity $opportunity, array $syncMap, bool $promote): void
    {
        $currentManagers = $opportunity->managers()->get();
        $missingUserIds = array_values(array_diff(array_keys($syncMap), $currentManagers->pluck('id')->all()));

        if ($missingUserIds === []) {
            return;
        }

        if (! $promote) {
            $missingNames = User::query()->whereIn('id', $missingUserIds)->orderBy('id')->pluck('name')->all();

            throw ValidationException::withMessages([
                'manager_slots' => [__(
                    "The following users are not Gestori Account of this offer's opportunity: :users. Resubmit with promote_managers_to_opportunity to add them.",
                    ['users' => implode(', ', $missingNames)],
                )],
            ]);
        }

        $this->promoteToOpportunity($opportunity, $currentManagers, $missingUserIds);
    }

    /**
     * Appends $missingUserIds to the Opportunita's first FREE slots — never
     * overwriting an occupied position, so promoting one user never demotes
     * another already in charge (D-6).
     *
     * @param  Collection<int, User>  $currentManagers
     * @param  array<int, int>  $missingUserIds
     */
    private function promoteToOpportunity(Opportunity $opportunity, Collection $currentManagers, array $missingUserIds): void
    {
        $occupiedPositions = $currentManagers
            ->map(static fn (User $manager): int => (int) $manager->pivot->position)
            ->all();
        $nextPosition = 1;

        foreach ($missingUserIds as $userId) {
            while (in_array($nextPosition, $occupiedPositions, true)) {
                $nextPosition++;
            }

            $opportunity->managers()->attach($userId, ['position' => $nextPosition]);
            $occupiedPositions[] = $nextPosition;
            $nextPosition++;
        }

        $opportunity->unsetRelation('managers');
    }

    /**
     * @param  array<int, array{position: int}>  $syncMap
     */
    private function writeOperatorId(Quote $quote, array $syncMap): void
    {
        $operatorId = null;

        foreach ($syncMap as $userId => $pivot) {
            if ($pivot['position'] === ManagerPositions::OPERATOR) {
                $operatorId = (int) $userId;

                break;
            }
        }

        $currentOperatorId = $quote->operator_id === null ? null : (int) $quote->operator_id;

        if ($currentOperatorId === $operatorId) {
            return;
        }

        $quote->forceFill(['operator_id' => $operatorId])->save();
    }

    /**
     * Explicit query when the relation is not already loaded — never a bare
     * lazy-loaded property access, so Model::preventLazyLoading() stays
     * satisfied regardless of what the caller eager-loaded on $quote
     * (mirrors Opportunity::operatorManager()'s own discipline).
     */
    private function resolveOpportunity(Quote $quote): Opportunity
    {
        if ($quote->relationLoaded('opportunity')) {
            return $quote->opportunity;
        }

        return $quote->opportunity()->firstOrFail();
    }
}
