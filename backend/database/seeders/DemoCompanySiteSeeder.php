<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\City;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\Contact;
use App\Models\PersonalData;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Development seed for the company-sites module (spec 0020 — "Società Sedi").
 *
 * Each site reuses the users/referents anagraphic stack via HasPersonalData: a
 * company personal-data card (morph `personable`) that owns its contacts and
 * ONE address (tied to a real seeded City), mirroring DemoRegistrySeeder. Each
 * seeded company gets 1-2 sites (alternating by its own position, so the
 * pattern is stable across re-runs) plus 0-2 banks. Exactly ONE site across the
 * WHOLE table ends up `is_default` (the flag is globally exclusive, not
 * per-company — see CompanySiteService::setDefault): `$defaultAssigned` is a
 * monotonic flag, set once and never un-set.
 *
 * Idempotent across repeated db:seed: existing sites are cleared per-model at
 * the start (the HasPersonalData deleting hook cascades each card + its
 * contacts/address; banks cascade via their own FK), mirroring
 * DemoRegistrySeeder.
 */
class DemoCompanySiteSeeder extends Seeder
{
    private const int CITY_SAMPLE = 200;

    public function run(): void
    {
        $faker = FakerFactory::create('it_IT');
        $faker->seed(20260707);

        // Idempotent: per-model delete so HasPersonalData's deleting hook fires
        // and cascades each site's card (and its contacts/address); banks
        // cascade away via their own cascadeOnDelete FK.
        CompanySite::query()->get()->each(fn (CompanySite $site) => $site->delete());

        $companies = Company::query()->get();
        $cities = City::query()
            ->with(['country', 'state', 'province'])
            ->inRandomOrder()
            ->limit(self::CITY_SAMPLE)
            ->get();

        if ($companies->isEmpty() || $cities->isEmpty()) {
            return;
        }

        $sequence = 0;
        $defaultAssigned = false;

        foreach ($companies as $companyPosition => $company) {
            // Alternate 1/2 sites by the company's own position (stable
            // across re-runs, unlike an accumulator carried across companies).
            $siteCount = $companyPosition % 2 === 0 ? 1 : 2;

            for ($site = 0; $site < $siteCount; $site++) {
                $sequence++;
                $this->seedSite($faker, $company, $cities, $sequence, ! $defaultAssigned);
                $defaultAssigned = true;
            }
        }
    }

    /**
     * @param  Collection<int, City>  $cities
     */
    private function seedSite(Generator $faker, Company $company, Collection $cities, int $sequence, bool $isDefault): void
    {
        $companySite = CompanySite::create([
            'name' => $faker->company().' - '.$faker->city(),
            'company_id' => $company->id,
            'is_default' => $isDefault,
        ]);

        $card = $this->seedCard($faker, $companySite);
        $this->seedContacts($faker, $card);
        $this->seedAddress($faker, $card, $cities, $sequence);
        $this->seedBanks($faker, $companySite, $sequence);
    }

    /**
     * Attach the site's company personal-data card (morph `personable`).
     */
    private function seedCard(Generator $faker, CompanySite $companySite): PersonalData
    {
        /** @var PersonalData $card */
        $card = PersonalData::factory()->company()->for($companySite, 'personable')->create([
            'company_name' => $companySite->name,
            'vat_number' => (string) $faker->numerify('###########'),
        ]);

        return $card;
    }

    /**
     * Seed a small set of contact channels on the card.
     */
    private function seedContacts(Generator $faker, PersonalData $card): void
    {
        Contact::factory()->email()->primary()->for($card, 'contactable')->create([
            'label' => 'General email',
        ]);
        Contact::factory()->phone()->primary()->for($card, 'contactable')->create([
            'label' => 'Switchboard',
        ]);
        Contact::factory()->pec()->for($card, 'contactable')->create([
            'label' => 'Certified email',
        ]);
    }

    /**
     * Seed the site's single (primary) address on the card, tied to a real
     * seeded city with its full geo ancestry.
     *
     * @param  Collection<int, City>  $cities
     */
    private function seedAddress(Generator $faker, PersonalData $card, Collection $cities, int $sequence): void
    {
        $city = $cities[$sequence % $cities->count()];

        Address::factory()->forCity($city)->primary()->for($card, 'addressable')->create([
            'postal_code' => $faker->numerify('#####'),
            'site_type' => 'legal_seat',
        ]);
    }

    /**
     * Reconcile to a small, deterministic bank list.
     */
    private function seedBanks(Generator $faker, CompanySite $companySite, int $sequence): void
    {
        $bankCount = $sequence % 3; // 0, 1 or 2 banks.

        for ($bank = 0; $bank < $bankCount; $bank++) {
            $companySite->banks()->create([
                'name' => $faker->company().' Bank',
                'iban' => $faker->iban('IT'),
                'notes' => $faker->optional()->sentence(),
                // The first bank is the site's preferred one (single-primary).
                'is_primary' => $bank === 0,
            ]);
        }
    }
}
