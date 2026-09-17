<?php

namespace Database\Factories;

use App\Enums\ContactTypeEnum;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for Contact channels.
 *
 *     Contact::factory()->create();            // a phone (default)
 *     Contact::factory()->email()->create();   // an email
 *     Contact::factory()->website()->create(); // a website
 *     Contact::factory()->primary()->create(); // marked as preferred
 *     Contact::factory()->for($card, 'contactable')->create(); // owned
 *
 * The default state is a valid phone contact; states switch the type and emit a
 * matching, valid `value` so cards pass the per-type validation untouched.
 *
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ContactTypeEnum::Phone->value,
            'label' => $this->faker->randomElement(['Work', 'Home', 'Support', null]),
            'value' => $this->faker->numerify('+39 ### ### ####'),
            'is_primary' => false,
        ];
    }

    /**
     * An explicit landline phone contact.
     */
    public function phone(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => ContactTypeEnum::Phone->value,
            'value' => $this->faker->numerify('+39 0## #######'),
        ]);
    }

    /**
     * A fax contact.
     */
    public function fax(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => ContactTypeEnum::Fax->value,
            'value' => $this->faker->numerify('+39 0## #######'),
        ]);
    }

    /**
     * An email contact with a valid email value.
     */
    public function email(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => ContactTypeEnum::Email->value,
            'value' => $this->faker->safeEmail(),
        ]);
    }

    /**
     * A certified-email (PEC) contact with a valid email value.
     */
    public function pec(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => ContactTypeEnum::Pec->value,
            'value' => $this->faker->userName().'@pec.example.com',
        ]);
    }

    /**
     * A website contact with a valid URL value.
     */
    public function website(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => ContactTypeEnum::Website->value,
            'value' => $this->faker->url(),
        ]);
    }

    /**
     * Mark this contact as the preferred one of its type for the owner.
     */
    public function primary(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_primary' => true,
        ]);
    }
}
