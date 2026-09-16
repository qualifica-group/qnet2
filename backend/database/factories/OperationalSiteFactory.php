<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\City;
use App\Models\OperationalSite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperationalSite>
 */
class OperationalSiteFactory extends Factory
{
    protected $model = OperationalSite::class;

    /**
     * The site's identity lives entirely on the primary address (spec 0011);
     * its only own flag is `is_active` (spec 0135).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['is_active' => true];
    }

    /**
     * A deactivated site (spec 0135): no longer offered by the for-select.
     */
    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /**
     * Attach a primary address to the site, tied to a REAL City (with its
     * full country/state ancestry) so geo-derived columns/filters have
     * something meaningful to resolve in tests/seeders.
     */
    public function withAddress(?City $city = null): static
    {
        return $this->afterCreating(function (OperationalSite $site) use ($city): void {
            Address::factory()->primary()->forCity($city ?? City::factory()->create())
                ->for($site, 'addressable')->create();
        });
    }
}
