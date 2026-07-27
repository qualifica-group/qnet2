<?php

namespace Database\Factories;

use App\Enums\GenderEnum;
use App\Enums\PersonalDataTypeEnum;
use App\Models\PersonalData;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for PersonalData cards.
 *
 *     PersonalData::factory()->create();             // random: individual or company
 *     PersonalData::factory()->individual()->create(); // force a natural person
 *     PersonalData::factory()->company()->create();    // force a legal entity
 *     PersonalData::factory()->for($user, 'personable')->create(); // owned
 *
 * The default state is a valid card of a RANDOM type (individual or company), so
 * seeded/test data exercises both shapes. Use individual() or company() when a
 * test depends on a specific type.
 *
 * @extends Factory<PersonalData>
 */
class PersonalDataFactory extends Factory
{
    protected $model = PersonalData::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return $this->faker->boolean()
            ? $this->individualState()
            : $this->companyState();
    }

    /**
     * Force a company card: legal entity with a company name and VAT number,
     * plus a contact person (first/last name) used by the `ceo` accessor.
     */
    public function company(): static
    {
        return $this->state(fn (array $attributes): array => $this->companyState());
    }

    /**
     * Force an individual card (a natural person).
     */
    public function individual(): static
    {
        return $this->state(fn (array $attributes): array => $this->individualState());
    }

    /**
     * The complete, valid attribute set for a natural person. Returned in full
     * (every type-specific field set) so it fully overrides a random default.
     *
     * @return array<string, mixed>
     */
    private function individualState(): array
    {
        return [
            'type' => PersonalDataTypeEnum::Individual->value,
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'company_name' => null,
            'tax_code' => strtoupper($this->faker->bothify('??????##?##?###?')),
            'vat_number' => null,
            'sdi_code' => null,
            'birth_date' => $this->faker->dateTimeBetween('-80 years', '-18 years')->format('Y-m-d'),
            // Left to the caller: seeding it would create a city row for every
            // card, and the place of birth is not needed to make one valid.
            'birth_city_id' => null,
            'gender' => $this->faker->randomElement(GenderEnum::cases())->value,
        ];
    }

    /**
     * The complete, valid attribute set for a legal entity. Returned in full
     * (every type-specific field set) so it fully overrides a random default.
     *
     * @return array<string, mixed>
     */
    private function companyState(): array
    {
        return [
            'type' => PersonalDataTypeEnum::Company->value,
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'company_name' => $this->faker->company(),
            'tax_code' => null,
            'vat_number' => (string) $this->faker->numerify('###########'),
            'sdi_code' => strtoupper($this->faker->bothify('???####')),
            'birth_date' => null,
            'birth_city_id' => null,
            'gender' => null,
        ];
    }
}
