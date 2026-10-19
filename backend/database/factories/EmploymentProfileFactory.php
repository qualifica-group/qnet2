<?php

namespace Database\Factories;

use App\Enums\RelationshipTypeEnum;
use App\Enums\SalaryPeriodEnum;
use App\Models\BusinessFunction;
use App\Models\EmploymentContract;
use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\ProductCategory;
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
            'relationship_type' => fake()->randomElement(RelationshipTypeEnum::values()),
            'company_id' => null,
            'hired_at' => $hiredAt->format('Y-m-d'),
            'terminated_at' => null,
            'standard_daily_minutes' => 480,
            'break_daily_minutes' => 30,
        ];
    }

    /**
     * A subordinate reporting to the given manager(s) — spec 0166 D-2, one or
     * more. `afterCreating`, since the pivot needs the profile's id first;
     * `syncWithoutDetaching` so combining this with another reportsTo() call
     * (or with the seeder's own attach) never drops a prior manager.
     */
    public function reportsTo(User ...$managers): static
    {
        return $this->afterCreating(function (EmploymentProfile $profile) use ($managers): void {
            $profile->reportsTo()->syncWithoutDetaching(collect($managers)->pluck('id')->all());
        });
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

    /**
     * Attaches one competence row per category, all paired with $function
     * (spec 0111): same `afterCreating` timing as the site states above, and
     * freely combinable with them. The function is explicit, not derived
     * from the category, so a caller can build both the matching case and
     * the mismatching one (AC-011/AC-012).
     */
    public function competentIn(BusinessFunction $function, ProductCategory ...$categories): static
    {
        return $this->afterCreating(function (EmploymentProfile $profile) use ($function, $categories): void {
            foreach ($categories as $category) {
                $profile->productLines()->firstOrCreate([
                    'business_function_id' => $function->id,
                    'product_category_id' => $category->id,
                ]);
            }
        });
    }

    /**
     * Attaches a (function, null) row (spec 0129 D-3): "every category of
     * this row's function" — the same `afterCreating` timing as
     * competentIn(), and freely combinable with it (a profile with several
     * rows, one of which is a wildcard on its own function).
     */
    public function competentInEveryCategoryOf(BusinessFunction $function): static
    {
        return $this->afterCreating(function (EmploymentProfile $profile) use ($function): void {
            $profile->productLines()->firstOrCreate([
                'business_function_id' => $function->id,
                'product_category_id' => null,
            ]);
        });
    }

    /**
     * Makes the person a member of $function (spec 0275): the
     * `business_function_user` row the mandatory membership rule asks of an
     * active person.
     */
    public function memberOf(BusinessFunction $function): static
    {
        return $this->afterCreating(function (EmploymentProfile $profile) use ($function): void {
            $profile->businessFunctions()->syncWithoutDetaching([$function->id]);
        });
    }

    /**
     * A contract condition paid `$rate` per hour from `$since` (spec 0277):
     * the hourly cost the management control values the hours with.
     */
    public function paidHourly(string $rate, string $since = '2000-01-01'): static
    {
        return $this->afterCreating(function (EmploymentProfile $profile) use ($rate, $since): void {
            EmploymentContract::factory()->withSalary($rate, SalaryPeriodEnum::Hourly)->create(['employment_profile_id' => $profile->id, 'starts_on' => $since]);
        });
    }

    /**
     * The profile-wide wildcard flag (spec 0129 D-1): competent for ANY
     * category, any function. Only meaningful with no competence rows (D-2
     * clears them server-side on write), so this state does not touch
     * `productLines()` — a factory profile starts with none anyway.
     */
    public function coversAllProductCategories(): static
    {
        return $this->state(fn (): array => ['covers_all_product_categories' => true]);
    }

    /**
     * Spec 0194: the "Assegnabile" switch off — in no assignment pool,
     * whatever its Sedi and competence.
     */
    public function notAssignable(): static
    {
        return $this->state(fn (): array => ['is_assignable' => false]);
    }
}
