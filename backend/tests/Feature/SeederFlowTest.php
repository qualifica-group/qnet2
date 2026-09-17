<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Province;
use App\Models\State;
use App\Models\User;
use Database\Seeders\DemoNotificationSeeder;
use Database\Seeders\DemoPersonalDataSeeder;
use Database\Seeders\DemoRolesSeeder;
use Database\Seeders\DemoUserAddressSeeder;
use Database\Seeders\DemoUserContactSeeder;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seededUsers()
{
    return User::query()
        ->where('email', 'demo@app.com')
        ->orWhere('email', 'like', '%@example.test');
}

function seedGeoFixture(): void
{
    $country = Country::factory()->create(['iso2' => 'IT', 'iso3' => 'ITA', 'name' => 'Italy']);
    $state = State::factory()->for($country, 'country')->create();

    for ($i = 0; $i < 6; $i++) {
        $province = Province::factory()->forState($state)->create();
        City::factory()->forProvince($province)->create();
    }
}

it('gives a complete profile to every deterministic development user', function () {
    seedGeoFixture();

    $this->seed(RolePermissionSeeder::class);
    $this->seed(DemoRolesSeeder::class);
    $this->seed(DemoUserSeeder::class);
    $this->seed(DemoUsersSeeder::class);
    $this->seed(DemoPersonalDataSeeder::class);
    $this->seed(DemoUserContactSeeder::class);
    $this->seed(DemoUserAddressSeeder::class);

    $users = seededUsers()->with(['personalData.contacts', 'personalData.addresses'])->get();

    expect($users)->toHaveCount(51)
        ->and($users->every(fn (User $user): bool => $user->personalData !== null))->toBeTrue()
        ->and($users->every(fn (User $user): bool => $user->personalData->contacts->isNotEmpty()))->toBeTrue()
        ->and($users->every(fn (User $user): bool => $user->personalData->addresses->isNotEmpty()))->toBeTrue();
});

it('seeds notifications only for users that follow the personal-data flow', function () {
    seedGeoFixture();

    $this->seed(RolePermissionSeeder::class);
    $this->seed(DemoRolesSeeder::class);
    $this->seed(DemoUserSeeder::class);
    $this->seed(DemoUsersSeeder::class);
    $this->seed(DemoPersonalDataSeeder::class);
    $this->seed(DemoUserContactSeeder::class);
    $this->seed(DemoUserAddressSeeder::class);
    $this->seed(DemoNotificationSeeder::class);

    seededUsers()->whereHas('personalData')->get()->each(
        fn (User $user) => expect($user->notifications()->count())->toBe(24)
    );

    $withoutCard = User::query()->whereDoesntHave('personalData')->get();

    $withoutCard->each(
        fn (User $user) => expect($user->notifications()->count())->toBe(0)
    );
});

it('seeds both individual and company profiles with realistic nested data', function () {
    seedGeoFixture();

    $this->seed(RolePermissionSeeder::class);
    $this->seed(DemoRolesSeeder::class);
    $this->seed(DemoUserSeeder::class);
    $this->seed(DemoUsersSeeder::class);
    $this->seed(DemoPersonalDataSeeder::class);
    $this->seed(DemoUserContactSeeder::class);
    $this->seed(DemoUserAddressSeeder::class);

    $users = seededUsers()->with(['personalData.contacts', 'personalData.addresses'])->get();
    $companies = $users->filter(fn (User $user): bool => $user->personalData?->type->value === 'company');
    $individuals = $users->filter(fn (User $user): bool => $user->personalData?->type->value === 'individual');

    expect($companies)->not->toBeEmpty()
        ->and($individuals)->not->toBeEmpty()
        ->and($companies->every(fn (User $user): bool => $user->personalData->contacts()->where('type', 'website')->exists()))->toBeTrue()
        ->and($companies->every(fn (User $user): bool => $user->personalData->addresses()->count() >= 2))->toBeTrue()
        ->and($individuals->every(fn (User $user): bool => $user->personalData->contacts()->where('type', 'phone')->where('is_primary', true)->exists()))->toBeTrue()
        ->and($users->every(fn (User $user): bool => $user->personalData->addresses()->whereNotNull('country_id')->whereNotNull('state_id')->whereNotNull('city_id')->exists()))->toBeTrue();
});
