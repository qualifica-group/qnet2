<?php

namespace Database\Factories;

use App\Enums\QualificationTypeEnum;
use App\Enums\RelationshipTypeEnum;
use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmploymentProfile>
 */
class EmploymentProfileFactory extends Factory
{
    protected $model = EmploymentProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hiredAt = fake()->dateTimeBetween('-5 years', '-1 month');

        return [
            'user_id' => User::factory(),
            'is_manager' => false,
            'job_description' => fake()->optional()->jobTitle(),
            'reports_to_id' => null,
            'business_function_id' => null,
            'relationship_type' => fake()->randomElement(RelationshipTypeEnum::values()),
            'company_id' => null,
            'qualification_type' => fake()->randomElement(QualificationTypeEnum::values()),
            'hired_at' => $hiredAt->format('Y-m-d'),
            'terminated_at' => null,
            'standard_daily_minutes' => 480,
            'break_daily_minutes' => 30,
        ];
    }

    /**
     * A responsible manager: never reports to anyone (spec 0015 server rule).
     */
    public function manager(): static
    {
        return $this->state(fn (): array => [
            'is_manager' => true,
            'reports_to_id' => null,
        ]);
    }

    /**
     * A subordinate reporting to the given manager.
     */
    public function reportsTo(User $manager): static
    {
        return $this->state(fn (): array => [
            'is_manager' => false,
            'reports_to_id' => $manager->id,
        ]);
    }

    /**
     * Attaches the given site as the PHYSICAL membership (spec 0103 D-3):
     * `afterCreating`, since the pivot needs the profile's id first. Replaces
     * any other physical row rather than stacking one, so this stays safe to
     * combine with remoteSites() regardless of call order.
     */
    public function physicalSite(OperationalSite $site): static
    {
        return $this->afterCreating(function (EmploymentProfile $profile) use ($site): void {
            $profile->operationalSites()->syncWithoutDetaching([$site->id => ['is_primary' => true]]);
        });
    }

    /**
     * Attaches the given sites as REMOTE memberships (spec 0103 D-1): same
     * `afterCreating` timing as physicalSite(), independently combinable.
     */
    public function remoteSites(OperationalSite ...$sites): static
    {
        return $this->afterCreating(function (EmploymentProfile $profile) use ($sites): void {
            $profile->operationalSites()->syncWithoutDetaching(
                collect($sites)->mapWithKeys(fn (OperationalSite $site): array => [$site->id => ['is_primary' => false]])->all()
            );
        });
    }
}
