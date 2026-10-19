<?php

namespace Database\Factories;

use App\Models\BusinessFunction;
use App\Models\OperationalSite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;

/**
 * @extends Factory<BusinessFunction>
 */
class BusinessFunctionFactory extends Factory
{
    protected $model = BusinessFunction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Mutually exclusive type (spec 0010): business unit, business
        // service, or neither — never both at once.
        $type = fake()->randomElement(['business_unit', 'business_service', null]);

        return [
            'name' => fake()->unique()->company(),
            'is_business_unit' => $type === 'business_unit',
            'is_business_service' => $type === 'business_service',
            'manager_id' => null,
            'parent_id' => null,
        ];
    }

    /**
     * Attach $parent as this function's parent (spec 0010 REV).
     */
    public function childOf(BusinessFunction $parent): static
    {
        return $this->state(fn (): array => ['parent_id' => $parent->id]);
    }

    /**
     * Switch the function's own "Attiva" flag off (spec 0280).
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    /**
     * Force the exclusive "business unit" type.
     */
    public function businessUnit(): static
    {
        return $this->state(fn (): array => [
            'is_business_unit' => true,
            'is_business_service' => false,
        ]);
    }

    /**
     * Force the exclusive "business service" type.
     */
    public function businessService(): static
    {
        return $this->state(fn (): array => [
            'is_business_unit' => false,
            'is_business_service' => true,
        ]);
    }

    /**
     * Assign a responsible manager: the given user, or a freshly created one.
     */
    public function withManager(?User $manager = null): static
    {
        return $this->state(fn (): array => [
            'manager_id' => $manager?->id ?? User::factory(),
        ]);
    }

    /**
     * Attach associated users after creation. Pass existing users to avoid
     * creating new ones; otherwise $count users are created.
     *
     * @param  Collection<int, User>|array<int, User>|null  $users
     */
    public function withUsers(int $count = 3, Collection|array|null $users = null): static
    {
        return $this->afterCreating(function (BusinessFunction $businessFunction) use ($count, $users): void {
            $members = $users !== null
                ? collect($users)->take($count)
                : User::factory()->count($count)->create();

            $businessFunction->users()->syncWithoutDetaching(
                collect($members)->pluck('id')->all(),
            );
        });
    }

    /**
     * Attach associated operational sites after creation (spec 0010 REV). Pass
     * existing sites to avoid creating new ones; otherwise $count sites (each
     * with an address, so geo-derived label composition has data to work
     * with) are created.
     *
     * @param  Collection<int, OperationalSite>|array<int, OperationalSite>|null  $sites
     */
    public function withOperationalSites(int $count = 2, Collection|array|null $sites = null): static
    {
        return $this->afterCreating(function (BusinessFunction $businessFunction) use ($count, $sites): void {
            $members = $sites !== null
                ? collect($sites)->take($count)
                : OperationalSite::factory()->withAddress()->count($count)->create();

            $businessFunction->operationalSites()->syncWithoutDetaching(
                collect($members)->pluck('id')->all(),
            );
        });
    }
}
