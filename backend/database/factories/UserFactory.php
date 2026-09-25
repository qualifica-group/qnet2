<?php

namespace Database\Factories;

use App\Enums\LocaleEnum;
use App\Models\EmploymentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'locale' => fake()->randomElement(LocaleEnum::values()),
            'is_active' => true,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the account is inactive and therefore denied login.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Attach an employment profile (spec 0015) after creation. Pass a
     * closure to customize the EmploymentProfileFactory state (e.g.
     * ->manager() or ->reportsTo($manager)).
     */
    public function withEmployment(?callable $factory = null): static
    {
        return $this->afterCreating(function (User $user) use ($factory): void {
            $employment = EmploymentProfile::factory();
            $employment = $factory !== null ? $factory($employment) : $employment;

            $employment->for($user)->create();
        });
    }

    /**
     * A responsible manager (is_manager=true, no reports_to).
     */
    public function manager(): static
    {
        return $this->withEmployment(static fn (EmploymentProfileFactory $factory): EmploymentProfileFactory => $factory->manager());
    }

    /**
     * A subordinate reporting to the given manager(s) — spec 0166 D-2, a
     * user may report to more than one.
     */
    public function reportsTo(User ...$managers): static
    {
        return $this->withEmployment(static fn (EmploymentProfileFactory $factory): EmploymentProfileFactory => $factory->reportsTo(...$managers));
    }
}
