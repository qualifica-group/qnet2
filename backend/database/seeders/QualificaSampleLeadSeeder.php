<?php

namespace Database\Seeders;

use App\DataObjects\Campaigns\CreateCampaignData;
use App\DataObjects\Leads\CreateLeadData;
use App\DataObjects\Projects\CreateProjectData;
use App\Models\Address;
use App\Models\Campaign;
use App\Models\City;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\PersonalData;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use App\Services\CampaignService;
use App\Services\LeadService;
use App\Services\ProjectService;
use Database\Seeders\Concerns\ResolvesCategoryBusinessFunction;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * The first step of the SAMPLE dataset (user directive 2026-07-31): a batch of
 * leads, part of them already converted into an opportunity, so the Lead and
 * Opportunity grids are not empty on a fresh install. Unlike the
 * QualificaProductionDataSeeder chain it is NOT client reference data — it is
 * fabricated, which is why it is named `Sample` and why the user directive
 * 2026-09-08 moved it out of that chain into QualificaSampleDataSeeder.
 *
 * It also creates the two rows the leads cannot exist without: a Lead's
 * `registry_id`/`campaign_id` are mandatory (spec 0024, BR-1) and neither
 * Anagrafiche nor Campagne are part of the client's reference data, so ONE
 * project and ONE campaign linked to it are provisioned here, plus one
 * Anagrafica per lead.
 *
 * The project — not the campaign — carries the business function / product
 * category pair: a linked campaign's own classification is forced null (spec
 * 0023, BR-2) and read through the project instead, which is exactly what
 * LeadOpportunityDefaultsResolver walks to derive the opportunity's product
 * line. Without that pair the conversion would be rejected (AC-012), so the
 * seeder skips itself rather than produce leads that cannot convert.
 *
 * Every row is written through the real Service (ProjectService/
 * CampaignService/LeadService::create) — the same path the POST endpoints
 * use, conversion included: `convertToOpportunity` is the request-level flag
 * of spec 0044, so the converted leads go through ConvertLeadToOpportunity
 * inside LeadService's own transaction, never a hand-rolled insert.
 *
 * ACCUMULATES, it does not converge (user directive 2026-09-08): every run
 * appends a fresh batch, so the seeder can be launched again whenever more
 * rows are wanted. The batch size is a `run()` parameter — `php artisan
 * qualifica:seed-sample --leads=200 --converted-leads=60` — so one run can
 * also be made bigger instead of repeated. Only the project and the campaign are looked
 * up by name and reused — one pair for the whole sample dataset, never a
 * second copy per run. The Faker generator is deliberately UNSEEDED for the
 * same reason: a fixed seed would make every run a carbon copy of the first.
 */
class QualificaSampleLeadSeeder extends Seeder
{
    use ResolvesCategoryBusinessFunction;

    /** The batch size when the caller names none (`--leads` of qualifica:seed-sample). */
    public const int DEFAULT_LEADS = 40;

    /** How many of that batch convert at most (`--converted-leads`). */
    public const int DEFAULT_CONVERTED_LEADS = 12;

    private const string PROJECT_NAME = 'Progetto commerciale di esempio';

    private const string CAMPAIGN_NAME = 'Campagna lead di esempio';

    private const int CITY_SAMPLE = 200;

    public function __construct(
        private readonly ProjectService $projects,
        private readonly CampaignService $campaigns,
        private readonly LeadService $leads,
    ) {}

    public function run(int $leads = self::DEFAULT_LEADS, int $convertedLeads = self::DEFAULT_CONVERTED_LEADS): void
    {
        // Step 1: the classification pair the conversion derives its product
        // line from. Without one no lead could ever convert (AC-012), so this
        // is a precondition of the whole batch, not of a single row.
        $pair = $this->coherentClassificationPairs(ProductCategory::query()->orderBy('id')->get())->first();

        if ($pair === null) {
            $this->command?->warn('Sample leads skipped: no product category with an effective business function.');

            return;
        }

        // Step 2: the mandatory owners of a Lead (BR-1), provisioned once.
        $campaign = $this->ensureCampaign($pair, $leads);

        $faker = FakerFactory::create('it_IT');

        $lookups = [
            'sources' => Source::query()->orderBy('id')->get(),
            'sites' => OperationalSite::query()->orderBy('id')->get(),
            'operators' => User::query()->orderBy('id')->get(),
            'cities' => City::query()->orderBy('id')->limit(self::CITY_SAMPLE)->get(),
        ];

        // Step 3: the batch itself.
        $converted = 0;

        for ($index = 0; $index < $leads; $index++) {
            // Every third lead converts, up to the cap: interleaved with the
            // plain ones instead of clustered at the head of the grid.
            $convert = $converted < $convertedLeads && $index % 3 === 0;
            $converted += $convert ? 1 : 0;

            $this->seedLead($faker, $index, $campaign, $lookups, $convert);
        }

        $this->command?->info(sprintf('%d sample leads seeded, %d of them converted to an opportunity.', $leads, $converted));
    }

    /**
     * The sample campaign, linked to the sample project that carries the
     * classification pair. Both are looked up by name first: the pair is the
     * natural key of this dataset, and neither Service exposes a
     * firstOrCreate.
     *
     * @param  array{product_category_id: int, business_function_id: int}  $pair
     */
    private function ensureCampaign(array $pair, int $leads): Campaign
    {
        $existing = Campaign::query()->where('name', self::CAMPAIGN_NAME)->first();

        if ($existing !== null) {
            return $existing;
        }

        return $this->campaigns->create(new CreateCampaignData(
            code: null,
            projectId: $this->ensureProject($pair, $leads)->getKey(),
            name: self::CAMPAIGN_NAME,
            description: 'Campagna di esempio a supporto dei lead dimostrativi.',
            partnerId: null,
            operationalSiteId: null,
            // `pipeline_status_id`/`product_lines` (spec 0094) are forced
            // null on a linked campaign (BR-2) — they live on the project
            // above.
            pipelineStatusId: null,
            productLines: null,
            stateId: null,
            startDate: null,
            endDate: null,
            totalBudget: null,
            targetLead: $leads,
        ));
    }

    /**
     * @param  array{product_category_id: int, business_function_id: int}  $pair
     */
    private function ensureProject(array $pair, int $leads): Project
    {
        $existing = Project::query()->where('name', self::PROJECT_NAME)->first();

        if ($existing !== null) {
            return $existing;
        }

        return $this->projects->create(new CreateProjectData(
            code: null,
            name: self::PROJECT_NAME,
            // Null: ProjectService falls back to the mandatory system_key
            // 'new' pipeline status (spec 0039, D-3).
            pipelineStatusId: null,
            description: 'Progetto di esempio a supporto dei lead dimostrativi.',
            productLines: [$pair],
            stateId: null,
            partnerId: null,
            operationalSiteId: null,
            startDate: null,
            endDate: null,
            totalBudget: null,
            targetLead: $leads,
            countryId: null,
            provinceId: null,
            cityId: null,
        ));
    }

    /**
     * @param  array{sources: Collection<int, Source>, sites: Collection<int, OperationalSite>, operators: Collection<int, User>, cities: Collection<int, City>}  $lookups
     */
    private function seedLead(Generator $faker, int $index, Campaign $campaign, array $lookups, bool $convert): void
    {
        $registry = $this->seedRegistry($faker, $index, $lookups);

        $this->leads->create(new CreateLeadData(
            registryId: $registry->getKey(),
            campaignId: $campaign->getKey(),
            // The sede drives the lead's Regione (spec 0047, D1), derived by
            // LeadService — hence a real site rather than null wherever the
            // legacy import provided one.
            operationalSiteId: $this->pick($lookups['sites'], $index)?->id,
            sourceId: $registry->source_id,
            operatorId: $this->pick($lookups['operators'], $index)?->id,
            notes: $faker->boolean(40) ? $faker->sentence() : null,
            convertToOpportunity: $convert,
        ));
    }

    /**
     * One Anagrafica per lead: the registry row plus its personal-data card
     * (the source of the denormalized `registries.name`), its contact
     * channels and one address — the same anagraphic stack the Anagrafiche
     * form writes, mirroring DemoRegistrySeeder.
     *
     * @param  array{sources: Collection<int, Source>, sites: Collection<int, OperationalSite>, operators: Collection<int, User>, cities: Collection<int, City>}  $lookups
     */
    private function seedRegistry(Generator $faker, int $index, array $lookups): Registry
    {
        $isCompany = $index % 4 !== 0;

        $registry = Registry::factory()->create([
            'source_id' => $this->pick($lookups['sources'], $index)?->id,
            'employee_count' => $isCompany ? $faker->numberBetween(1, 250) : null,
        ]);

        $factory = $isCompany ? PersonalData::factory()->company() : PersonalData::factory()->individual();

        /** @var PersonalData $card */
        $card = $factory->for($registry, 'personable')->create();

        $this->seedContacts($card, $isCompany);
        $this->seedAddress($faker, $card, $lookups['cities'], $index, $isCompany);

        $registry->forceFill(['name' => $card->full_name])->save();

        return $registry;
    }

    private function seedContacts(PersonalData $card, bool $isCompany): void
    {
        Contact::factory()->email()->primary()->for($card, 'contactable')->create([
            'label' => $isCompany ? 'General email' : 'Personal email',
        ]);

        Contact::factory()->mobile()->primary()->for($card, 'contactable')->create(['label' => 'Mobile']);

        if ($isCompany) {
            Contact::factory()->phone()->primary()->for($card, 'contactable')->create(['label' => 'Switchboard']);
        }
    }

    /**
     * A primary address on a REAL seeded city when the geo tables are
     * populated (`locations:add`, run by DatabaseSeeder — not by this chain),
     * a geo-less one otherwise.
     *
     * @param  Collection<int, City>  $cities
     */
    private function seedAddress(Generator $faker, PersonalData $card, Collection $cities, int $index, bool $isCompany): void
    {
        $siteType = $isCompany ? 'legal_seat' : 'billing';

        if ($cities->isEmpty()) {
            Address::factory()->primary()->for($card, 'addressable')->create(['site_type' => $siteType]);

            return;
        }

        Address::factory()
            ->forCity($cities[$index % $cities->count()])
            ->primary()
            ->for($card, 'addressable')
            ->create(['postal_code' => $faker->numerify('#####'), 'site_type' => $siteType]);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Collection<int, TModel>  $items
     * @return TModel|null
     */
    private function pick(Collection $items, int $index): mixed
    {
        return $items->isNotEmpty() ? $items[$index % $items->count()] : null;
    }
}
