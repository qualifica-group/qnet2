<?php

use App\Models\BusinessFunction;
use App\Models\EmploymentProfile;
use App\Models\User;
use Database\Factories\EmploymentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0272, AC-001 — the hourly cost of the employment profile.
uses(RefreshDatabase::class);

function hourlyCostActor(): User
{
    Permission::findOrCreate('users.update');

    return User::factory()->create()->givePermissionTo('users.update');
}

it('AC-001: the migration adds a nullable hourly_cost and rolls back cleanly', function () {
    $migration = require database_path('migrations/2026_10_20_100000_add_hourly_cost_to_employment_profiles_table.php');

    expect(Schema::hasColumn('employment_profiles', 'hourly_cost'))->toBeTrue();

    $migration->down();
    expect(Schema::hasColumn('employment_profiles', 'hourly_cost'))->toBeFalse();

    $migration->up();
    expect(Schema::hasColumn('employment_profiles', 'hourly_cost'))->toBeTrue();
});

it('AC-001: the hourly cost is saved with two decimals and returned by the resource', function () {
    $target = User::factory()->withEmployment(fn (EmploymentProfileFactory $factory): EmploymentProfileFactory => $factory->memberOf(BusinessFunction::factory()->create()))->create();
    Sanctum::actingAs(hourlyCostActor());

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['hourly_cost' => 31.5]])
        ->assertOk()
        ->assertJsonPath('data.employment.hourly_cost', 31.5);

    expect(EmploymentProfile::where('user_id', $target->id)->value('hourly_cost'))->toBe('31.50');
});

it('AC-001: a blank or null hourly cost clears the value', function () {
    $target = User::factory()->withEmployment(fn (EmploymentProfileFactory $factory): EmploymentProfileFactory => $factory->state(['hourly_cost' => '25.00'])->memberOf(BusinessFunction::factory()->create()))->create();
    Sanctum::actingAs(hourlyCostActor());

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['hourly_cost' => null]])
        ->assertOk()
        ->assertJsonPath('data.employment.hourly_cost', null);

    expect(EmploymentProfile::where('user_id', $target->id)->value('hourly_cost'))->toBeNull();
});

it('AC-001: a negative, oversized or non numeric hourly cost is rejected with 422', function (mixed $value) {
    $target = User::factory()->create();
    Sanctum::actingAs(hourlyCostActor());

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['hourly_cost' => $value]])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['employment.hourly_cost']);
})->with([-1, 10000, 'abc']);

it('AC-001: the maximum 9999.99 is accepted', function () {
    $target = User::factory()->withEmployment(fn (EmploymentProfileFactory $factory): EmploymentProfileFactory => $factory->memberOf(BusinessFunction::factory()->create()))->create();
    Sanctum::actingAs(hourlyCostActor());

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['hourly_cost' => 9999.99]])->assertOk();

    expect(EmploymentProfile::where('user_id', $target->id)->value('hourly_cost'))->toBe('9999.99');
});

it('AC-001: without users.update the hourly cost cannot be written', function () {
    $target = User::factory()->create();
    Sanctum::actingAs(User::factory()->create());

    $this->patchJson("/api/users/{$target->id}", ['employment' => ['hourly_cost' => 10]])->assertForbidden();
});
