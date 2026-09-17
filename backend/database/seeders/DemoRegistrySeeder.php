<?php

namespace Database\Seeders;

use App\Enums\AgreementStatusEnum;
use App\Enums\PersonalDataTypeEnum;
use App\Enums\SizeClassEnum;
use App\Models\Address;
use App\Models\City;
use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Sector;
use App\Models\Source;
use App\Models\User;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Development seed for the registries module (Anagrafiche, spec 0020).
 *
 * Each registry reuses the users/referents anagraphic stack unchanged via
 * HasPersonalData (own card + contacts + addresses, mirrors
 * DemoReferentSeeder), plus the business-specific relations: source,
 * sectors (multi), referents (multi), internal managers (multi, max 4),
 * supervisor (internal user), commercial/reporter (referents) and the supplier/agreement/size
 * scalars. Roughly a third are suppliers (some qualified), so the derived
 * grid columns and the form have realistic values to exercise.
 *
 * Depends on DemoSourceSeeder/DemoSectorSeeder/DemoReferentSeeder (lookups)
 * and DemoUsersSeeder (internal managers) — degrades gracefully when any of
 * them produced nothing. Deterministic faker seed for reproducibility;
 * existing registries are cleared per-model at the start of the run
 * (idempotent across repeated db:seed — the HasPersonalData deleting hook
 * cascades each card, the pivots cascade via their own FKs), mirroring
 * DemoReferentSeeder.
 */
class DemoRegistrySeeder extends Seeder
{
    private const int REGISTRIES = 20;

    private const int CITY_SAMPLE = 200;

    public function run(): void
    {
        $faker = FakerFactory::create('it_IT');
        $faker->seed(20260708);

        // Idempotent: per-model delete so HasPersonalData's deleting hook fires
        // and cascades each registry's card (and its contacts/addresses); the
        // 3 pivot tables cascade away via their own cascadeOnDelete FKs.
        Registry::query()->get()->each(fn (Registry $registry) => $registry->delete());

        $sources = Source::query()->orderBy('name')->get();
        $sectors = Sector::query()->orderBy('name')->get();
        $referents = Referent::query()->orderBy('name')->get();
        $managers = User::query()->orderBy('id')->get();
        $cities = City::query()
            ->with(['country', 'state', 'province'])
            ->inRandomOrder()
            ->limit(self::CITY_SAMPLE)
            ->get();

        for ($index = 0; $index < self::REGISTRIES; $index++) {
            $this->seedRegistry($faker, $sources, $sectors, $referents, $managers, $cities, $index);
        }
    }

    /**
     * @param  Collection<int, Source>  $sources
     * @param  Collection<int, Sector>  $sectors
     * @param  Collection<int, Referent>  $referents
     * @param  Collection<int, User>  $managers
     * @param  Collection<int, City>  $cities
     */
    private function seedRegistry(
        Generator $faker,
        Collection $sources,
        Collection $sectors,
        Collection $referents,
        Collection $managers,
        Collection $cities,
        int $index,
    ): void {
        $isCompany = $index % 4 !== 0;
        $isSupplier = $index % 3 === 0;
        $isQualifiedSupplier = $isSupplier && $index % 6 === 0;
        $agreementStatuses = AgreementStatusEnum::cases();
        $sizeClasses = SizeClassEnum::cases();

        $registry = Registry::factory()->create([
            'source_id' => $sources->isNotEmpty() ? $sources[$index % $sources->count()]->id : null,
            'vat_group' => $faker->boolean(40) ? $faker->bothify('VG-####') : null,
            'is_supplier' => $isSupplier,
            'is_qualified_supplier' => $isQualifiedSupplier,
            'agreement_status' => $agreementStatuses[$index % count($agreementStatuses)]->value,
            'agreement_notes' => $faker->boolean(50) ? $faker->sentence() : null,
            'size_class' => $sizeClasses[$index % count($sizeClasses)]->value,
            // Supervisor is an INTERNAL user (managers pool); commercial/reporter
            // stay external referents.
            'supervisor_id' => $managers->isNotEmpty() ? $managers[$index % $managers->count()]->id : null,
            'commercial_id' => $referents->isNotEmpty() ? $referents[($index + 1) % $referents->count()]->id : null,
            'reporter_id' => $referents->isNotEmpty() ? $referents[($index + 2) % $referents->count()]->id : null,
            'employee_count' => $faker->boolean(70) ? $faker->numberBetween(1, 500) : null,
        ]);

        $this->syncPivots($registry, $sectors, $referents, $managers, $index);

        $card = $this->seedCard($registry, $isCompany);
        $this->seedContacts($faker, $card);
        $this->seedAddresses($faker, $card, $cities, $index);

        $registry->forceFill(['name' => $card->full_name])->save();
    }

    /**
     * Attach 1-3 sectors, 1-2 referents and up to 4 internal managers
     * (spec 0020 MAX 4 rule), all round-robin so every seeded lookup row gets
     * exercised across the batch.
     *
     * @param  Collection<int, Sector>  $sectors
     * @param  Collection<int, Referent>  $referents
     * @param  Collection<int, User>  $managers
     */
    private function syncPivots(Registry $registry, Collection $sectors, Collection $referents, Collection $managers, int $index): void
    {
        if ($sectors->isNotEmpty()) {
            $registry->sectors()->sync($this->roundRobinIds($sectors, $index, 2));
        }

        if ($referents->isNotEmpty()) {
            $registry->referents()->sync($this->roundRobinIds($referents, $index, 2));
        }

        if ($managers->isNotEmpty()) {
            // Assign 1-based "G.A. n" positions in round-robin order.
            $registry->managers()->sync(
                collect($this->roundRobinIds($managers, $index, 3))
                    ->values()
                    ->mapWithKeys(fn (int $id, int $slot): array => [$id => ['position' => $slot + 1]])
                    ->all(),
            );
        }
    }

    /**
     * @param  Collection<int, Model>  $items
     * @return array<int, int>
     */
    private function roundRobinIds(Collection $items, int $index, int $count): array
    {
        $ids = [];

        for ($offset = 0; $offset < min($count, $items->count()); $offset++) {
            $ids[] = $items[($index + $offset) % $items->count()]->id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * Attach the registry's personal-data card (morph `personable`), shaped
     * as a company or an individual — mirrors DemoReferentSeeder.
     */
    private function seedCard(Registry $registry, bool $isCompany): PersonalData
    {
        $factory = $isCompany
            ? PersonalData::factory()->company()
            : PersonalData::factory()->individual();

        /** @var PersonalData $card */
        $card = $factory->for($registry, 'personable')->create();

        return $card;
    }

    /**
     * Seed a complete set of contact channels on the card, mirrors
     * DemoReferentSeeder.
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

            return;
        }

        Contact::factory()->phone()->for($card, 'contactable')->create([
            'label' => 'Home',
        ]);
    }

    /**
     * Seed one primary address tied to a real seeded city, with site_type
     * (spec 0020) — the Anagrafiche form is the only one that exposes the
     * select, so this is the seeder that gives it realistic values. Falls
     * back to null geo when no city is available, mirrors DemoReferentSeeder.
     *
     * @param  Collection<int, City>  $cities
     */
    private function seedAddresses(Generator $faker, PersonalData $card, Collection $cities, int $index): void
    {
        $siteType = $card->type === PersonalDataTypeEnum::Company ? 'legal_seat' : 'billing';

        if ($cities->isEmpty()) {
            Address::factory()->primary()->for($card, 'addressable')->create(['site_type' => $siteType]);

            return;
        }

        $city = $cities[$index % $cities->count()];

        Address::factory()->forCity($city)->primary()->for($card, 'addressable')->create([
            'postal_code' => $this->postalCode($faker, $city->country?->iso2),
            'site_type' => $siteType,
        ]);
    }

    private function postalCode(Generator $faker, ?string $countryCode): string
    {
        return match (strtoupper((string) $countryCode)) {
            'GB' => strtoupper($faker->bothify('?## #??')),
            default => $faker->numerify('#####'),
        };
    }
}
