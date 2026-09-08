<?php

namespace Database\Seeders\Concerns;

use App\Models\Registry;
use App\Services\Opportunities\RegistryOpenOpportunityGuard;
use Illuminate\Support\Collection;

/**
 * The Anagrafiche a new Opportunity may hang on, for the sample seeders that
 * create one per Anagrafica.
 *
 * User directive 2026-08-31: an anagrafica carries ONE open opportunity at a
 * time. The two sample seeders that create opportunities (standalone deals
 * and requests) run one after the other on the SAME pool of Anagrafiche, so
 * each must exclude what the previous one has just taken — through the very
 * guard the opportunity form uses, never a hand-rolled `whereDoesntHave`,
 * which would miss the single-mode exemption the guard applies.
 */
trait PicksFreeRegistries
{
    /**
     * The anagrafiche with no blocking open opportunity, in id order.
     *
     * @return Collection<int, Registry>
     */
    protected function freeRegistries(RegistryOpenOpportunityGuard $guard): Collection
    {
        /** @var Collection<int, Registry> $registries */
        $registries = Registry::query()->orderBy('id')->get();

        $busy = $guard->openOpportunityIdsByRegistry($registries->modelKeys());

        return $registries
            ->reject(static fn (Registry $registry): bool => isset($busy[$registry->getKey()]))
            ->values();
    }
}
