<?php

use App\Models\Address;
use App\Models\City;
use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\User;
use Database\Seeders\QualificaSelfFundedTemplateRequestSeeder;
use Database\Seeders\Support\RequestSheetLookups;
use Database\Seeders\Support\RequestSheetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The Sedi the request sheet seeders give to operators without one (user decisions
 * 2026-11-28), and the lookups the structured sheets added. Synthetic data only.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->siteIn = static function (City $city): OperationalSite {
        $site = OperationalSite::factory()->create();
        Address::factory()->create(['addressable_type' => $site->getMorphClass(), 'addressable_id' => $site->id, 'city_id' => $city->id]);

        return $site;
    };
    $this->assignSites = static function (string $operator, array $sites): void {
        // Any concrete sheet seeder: the method lives on their shared base.
        $seeder = app(QualificaSelfFundedTemplateRequestSeeder::class);
        Closure::bind(fn () => $this->assignSites($operator, $sites), $seeder, RequestSheetSeeder::class)();
    };
});

it('gives an operator without a Sede the primary one and the remote ones', function () {
    $city = City::factory()->create(['name' => 'Comune Sedi']);
    [$primary, $remote] = [($this->siteIn)($city), ($this->siteIn)($city)];
    $profile = EmploymentProfile::factory()->create(['user_id' => User::factory()->create(['name' => 'Operatrice Test'])->id]);

    ($this->assignSites)('Operatrice Test', ['city' => 'Comune Sedi', 'primary' => $primary->id, 'remote' => [$remote->id]]);

    $sites = $profile->operationalSites()->get()->mapWithKeys(fn ($site) => [$site->id => (bool) $site->pivot->is_primary])->all();

    expect($sites)->toBe([$primary->id => true, $remote->id => false]);
});

it('leaves alone an operator who already has a primary Sede', function () {
    $city = City::factory()->create(['name' => 'Comune Sedi']);
    [$own, $other] = [($this->siteIn)($city), ($this->siteIn)($city)];
    $profile = EmploymentProfile::factory()->create(['user_id' => User::factory()->create(['name' => 'Operatrice Test'])->id]);
    $profile->operationalSites()->attach($own->id, ['is_primary' => true]);

    ($this->assignSites)('Operatrice Test', ['city' => 'Comune Sedi', 'primary' => $other->id, 'remote' => []]);

    expect($profile->operationalSites()->pluck('operational_sites.id')->all())->toBe([$own->id]);
});

it('refuses Sedi that are not in the expected Comune', function () {
    $site = ($this->siteIn)(City::factory()->create(['name' => 'Altro Comune']));
    EmploymentProfile::factory()->create(['user_id' => User::factory()->create(['name' => 'Operatrice Test'])->id]);

    ($this->assignSites)('Operatrice Test', ['city' => 'Comune Sedi', 'primary' => $site->id, 'remote' => []]);
})->throws(RuntimeException::class, 'are not all in Comune Sedi');

it('picks the Comune with the exact accent, not its unaccented namesake', function () {
    City::factory()->create(['name' => 'Paterno', 'country_code' => 'IT']);
    $accented = City::factory()->create(['name' => 'Paternò', 'country_code' => 'IT']);

    expect(app(RequestSheetLookups::class)->city('Paternò')->id)->toBe($accented->id);
});

it('finds a transfer origin by Sede id where the Comune holds two Sedi', function () {
    $city = City::factory()->create(['name' => 'Comune Doppio']);
    [$first] = [($this->siteIn)($city), ($this->siteIn)($city)];
    $lookups = app(RequestSheetLookups::class);

    expect($lookups->transferOriginId($first->id))->toBe($first->id)
        ->and(fn () => $lookups->transferOriginId('Comune Doppio'))->toThrow(RuntimeException::class);
});
