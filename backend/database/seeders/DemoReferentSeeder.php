<?php

namespace Database\Seeders;

use App\Enums\PersonalDataTypeEnum;
use App\Enums\ReferentContactScopeEnum;
use App\Models\Address;
use App\Models\City;
use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\Referent;
use App\Models\ReferentType;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Development seed for the referents module (Referenti, spec 0016).
 *
 * Each referent reuses the users' anagraphic stack unchanged via
 * HasPersonalData: it owns exactly one personal-data card, which in turn owns
 * its own contacts and addresses. The card is shaped as an individual or a
 * company (round-robin) and gets a COMPLETE contact/address form — several
 * channels (email/phone, plus pec/website for companies) and one or two
 * addresses tied to REAL seeded cities — so the derived grid columns
 * (referent_type, contact_scope, primary_contact) and the detail form have
 * realistic values to exercise.
 *
 * Depends on DemoReferentTypeSeeder for the classification select; degrades to
 * an unclassified referent when no type exists. Deterministic faker seed for
 * reproducibility; existing referents are cleared per-model at the start of the
 * run (idempotent across repeated db:seed — the HasPersonalData deleting hook
 * cascades each card, and the card cascades its contacts/addresses), mirroring
 * DemoOperationalSiteSeeder.
 */
class DemoReferentSeeder extends Seeder
{
    private const int REFERENTS = 30;

    private const int CITY_SAMPLE = 200;

    public function run(): void
    {
        $faker = FakerFactory::create('it_IT');
        $faker->seed(20260707);

        // Idempotent: per-model delete so HasPersonalData's deleting hook fires
        // and cascades each referent's card (and its contacts/addresses).
        Referent::query()->get()->each(fn (Referent $referent) => $referent->delete());

        $types = ReferentType::query()->orderBy('name')->get();
        $scopes = ReferentContactScopeEnum::cases();
        $cities = City::query()
            ->with(['country', 'state', 'province'])
            ->inRandomOrder()
            ->limit(self::CITY_SAMPLE)
            ->get();

        for ($index = 0; $index < self::REFERENTS; $index++) {
            $this->seedReferent($faker, $types, $scopes, $cities, $index);
        }
    }

    /**
     * Create one referent with a full anagraphic card, then re-derive its
     * denormalized `name` from the card (mirrors ReferentProfileWriter).
     *
     * @param  Collection<int, ReferentType>  $types
     * @param  array<int, ReferentContactScopeEnum>  $scopes
     * @param  Collection<int, City>  $cities
     */
    private function seedReferent(Generator $faker, Collection $types, array $scopes, Collection $cities, int $index): void
    {
        $isCompany = $index % 3 === 0;
        $type = $types->isNotEmpty() ? $types[$index % $types->count()] : null;
        $scope = $scopes[$index % count($scopes)];

        $referent = Referent::factory()->create([
            'referent_type_id' => $type?->id,
            'contact_scope' => $scope->value,
            'notes' => $faker->boolean(60) ? $faker->sentence() : null,
        ]);

        $card = $this->seedCard($referent, $isCompany);
        $this->seedContacts($faker, $card);
        $this->seedAddresses($faker, $card, $cities, $index);

        $referent->forceFill(['name' => $card->full_name])->save();
    }

    /**
     * Attach the referent's personal-data card (morph `personable`), shaped as a
     * company or an individual.
     */
    private function seedCard(Referent $referent, bool $isCompany): PersonalData
    {
        $factory = $isCompany
            ? PersonalData::factory()->company()
            : PersonalData::factory()->individual();

        /** @var PersonalData $card */
        $card = $factory->for($referent, 'personable')->create();

        return $card;
    }

    /**
     * Seed a complete set of contact channels on the card. Companies get the
     * fuller institutional form (switchboard + pec + website); individuals a
     * personal one, mirroring DemoUserContactSeeder.
     */
    private function seedContacts(Generator $faker, PersonalData $card): void
    {
        $isCompany = $card->type === PersonalDataTypeEnum::Company;

        Contact::factory()->email()->primary()->for($card, 'contactable')->create([
            'label' => $isCompany ? 'General email' : 'Personal email',
        ]);

        Contact::factory()->phone()->primary()->for($card, 'contactable')->create();

        if ($isCompany) {
            Contact::factory()->phone()->for($card, 'contactable')->create([
                'label' => 'Switchboard',
            ]);
            Contact::factory()->pec()->for($card, 'contactable')->create([
                'label' => 'Certified email',
            ]);
            Contact::factory()->website()->for($card, 'contactable')->create([
                'label' => 'Website',
            ]);

            return;
        }

        Contact::factory()->phone()->for($card, 'contactable')->create([
            'label' => 'Home',
        ]);

        if ($faker->boolean(45)) {
            Contact::factory()->email()->for($card, 'contactable')->create([
                'label' => 'Backup email',
            ]);
        }
    }

    /**
     * Seed one primary address (plus an optional secondary for companies) tied
     * to a real seeded city; falls back to null geo when no city is available,
     * mirroring DemoUserAddressSeeder.
     *
     * @param  Collection<int, City>  $cities
     */
    private function seedAddresses(Generator $faker, PersonalData $card, Collection $cities, int $index): void
    {
        if ($cities->isEmpty()) {
            Address::factory()->primary()->for($card, 'addressable')->create();

            return;
        }

        $primaryCity = $cities[$index % $cities->count()];

        Address::factory()->forCity($primaryCity)->primary()->for($card, 'addressable')->create([
            'postal_code' => $this->postalCode($faker, $primaryCity->country?->iso2),
        ]);

        $needsSecondary = $card->type === PersonalDataTypeEnum::Company || $faker->boolean(30);

        if ($needsSecondary) {
            $secondaryCity = $cities[($index + 17) % $cities->count()];

            Address::factory()->forCity($secondaryCity)->for($card, 'addressable')->create([
                'postal_code' => $this->postalCode($faker, $secondaryCity->country?->iso2),
            ]);
        }
    }

    private function postalCode(Generator $faker, ?string $countryCode): string
    {
        return match (strtoupper((string) $countryCode)) {
            'GB' => strtoupper($faker->bothify('?## #??')),
            default => $faker->numerify('#####'),
        };
    }
}
