<?php

namespace Database\Seeders;

use App\DataObjects\Leads\CreateLeadData;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use App\Services\LeadService;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Development seed for the leads module (spec 0024, spec 0041 D-1):
 * round-robins existing registries/campaigns (BR-1, mandatory)
 * against the optional site/source/operator lookups, so the demo grid
 * exercises every derived table column (registry/campaign/lead_status/
 * operational_site/source/operator) with realistic, varied values.
 *
 * Every lead is created through LeadService::create() — the same path
 * POST /api/leads uses — so this exercises the real write path, not a raw
 * insert. Idempotent: existing leads are cleared first (harmless — nothing
 * else references a Lead, restrictOnDelete only runs the OTHER way).
 *
 * Depends on DemoRegistrySeeder, DemoCampaignSeeder, DemoOperationalSiteSeeder,
 * DemoSourceSeeder and DemoUsersSeeder (all seeded earlier in DemoDataSeeder)
 * — a no-op (nothing to seed) if registries or campaigns are empty.
 */
class DemoLeadSeeder extends Seeder
{
    private const int LEADS = 60;

    public function __construct(private readonly LeadService $leads) {}

    public function run(): void
    {
        Lead::query()->delete();

        $faker = FakerFactory::create('it_IT');
        $faker->seed(20260713);

        $registries = Registry::query()->orderBy('id')->get();
        $campaigns = Campaign::query()->orderBy('id')->get();

        if ($registries->isEmpty() || $campaigns->isEmpty()) {
            // Nothing sensible to seed without the mandatory relations (BR-1).
            return;
        }

        $sites = OperationalSite::query()->orderBy('id')->get();
        $sources = Source::query()->orderBy('id')->get();
        $operators = User::query()->orderBy('id')->get();

        for ($index = 0; $index < self::LEADS; $index++) {
            $this->createLead($faker, $index, $registries, $campaigns, $sites, $sources, $operators);
        }
    }

    /**
     * @param  Collection<int, Registry>  $registries
     * @param  Collection<int, Campaign>  $campaigns
     * @param  Collection<int, OperationalSite>  $sites
     * @param  Collection<int, Source>  $sources
     * @param  Collection<int, User>  $operators
     */
    private function createLead(
        Generator $faker,
        int $index,
        Collection $registries,
        Collection $campaigns,
        Collection $sites,
        Collection $sources,
        Collection $operators,
    ): void {
        $data = new CreateLeadData(
            registryId: $registries[$index % $registries->count()]->id,
            campaignId: $campaigns[$index % $campaigns->count()]->id,
            operationalSiteId: $this->maybePick($sites, $index, $faker, 60)?->id,
            // Spec 0176: the Fonte is mandatory on a lead.
            sourceId: $this->maybePick($sources, $index + 1, $faker, 100)?->id,
            operatorId: $this->maybePick($operators, $index + 2, $faker, 50)?->id,
            notes: $faker->boolean(40) ? $faker->sentence() : null,
        );

        $this->leads->create($data);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Collection<int, TModel>  $items
     * @return TModel|null
     */
    private function maybePick(Collection $items, int $index, Generator $faker, int $probability): mixed
    {
        if ($items->isEmpty() || ! $faker->boolean($probability)) {
            return null;
        }

        return $items[$index % $items->count()];
    }
}
