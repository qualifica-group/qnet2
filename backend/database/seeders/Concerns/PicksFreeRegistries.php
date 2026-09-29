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
    /** Anagrafiche read per query: the pool can hold a million bulk-seeded rows. */
    private const int FREE_REGISTRY_CHUNK = 1000;

    /**
     * The first $limit anagrafiche with no blocking open opportunity, in id
     * order — walked in chunks and stopped as soon as $limit are found, never
     * loaded whole.
     *
     * @return Collection<int, Registry>
     */
    protected function freeRegistries(RegistryOpenOpportunityGuard $guard, int $limit): Collection
    {
        /** @var Collection<int, Registry> $free */
        $free = new Collection;

        if ($limit < 1) {
            return $free;
        }

        Registry::query()->chunkById(self::FREE_REGISTRY_CHUNK, static function (Collection $registries) use ($guard, $limit, $free): bool {
            $busy = $guard->openOpportunityIdsByRegistry($registries->modelKeys());

            foreach ($registries as $registry) {
                if (! isset($busy[$registry->getKey()])) {
                    $free->push($registry);
                }

                if ($free->count() === $limit) {
                    return false;
                }
            }

            return true;
        });

        return $free;
    }
}
