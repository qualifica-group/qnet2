<?php

use App\Models\CollectiveAgreement;
use App\Models\CollectiveAgreementLevel;
use App\Models\Company;
use App\Models\EmploymentContract;
use App\Models\EmploymentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * @param  array<int, string>  $permissions  full permission names
 */
function contractsActor(array $permissions): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $actor = User::factory()->create();
    $actor->givePermissionTo($permissions);

    return $actor;
}

function contractsProfile(): EmploymentProfile
{
    return EmploymentProfile::factory()->create(['relationship_type' => null, 'company_id' => null]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function contractPayload(array $overrides = []): array
{
    return array_merge(['starts_on' => '2026-01-01', 'relationship_type' => 'employee'], $overrides);
}

beforeEach(fn () => Carbon::setTestNow('2026-10-16 10:00:00'));
afterEach(fn () => Carbon::setTestNow());

it('0272 AC-004 a past start updates the profile copy, a future one does not', function () {
    Sanctum::actingAs(contractsActor(['users.update']));
    $profile = contractsProfile();
    $company = Company::factory()->create();

    $this->postJson("/api/users/{$profile->user_id}/employment-contracts", contractPayload([
        'relationship_type' => 'self_employed', 'company_id' => $company->id,
    ]))->assertCreated()->assertJsonPath('data.is_current', true);

    expect($profile->fresh())
        ->relationship_type->value->toBe('self_employed')
        ->company_id->toBe($company->id);

    $this->postJson("/api/users/{$profile->user_id}/employment-contracts", contractPayload([
        'starts_on' => '2026-12-01', 'relationship_type' => 'other',
    ]))->assertCreated()->assertJsonPath('data.is_current', false);

    expect($profile->fresh()->relationship_type->value)->toBe('self_employed');
});

it('0272 AC-005 the list is ordered by start desc with derived ends_on and is_current', function () {
    Sanctum::actingAs(contractsActor(['users.view']));
    $profile = contractsProfile();
    foreach (['2024-01-01', '2025-06-01', '2026-12-01'] as $date) {
        EmploymentContract::factory()->create(['employment_profile_id' => $profile->id, 'starts_on' => $date]);
    }

    $response = $this->getJson("/api/users/{$profile->user_id}/employment-contracts")->assertOk();

    expect($response->json('data.*.starts_on'))->toBe(['2026-12-01', '2025-06-01', '2024-01-01'])
        ->and($response->json('data.*.ends_on'))->toBe([null, '2026-11-30', '2025-05-31'])
        ->and($response->json('data.*.is_current'))->toBe([false, true, false]);
});

it('0272 AC-005 exposes the company and the collective agreement level references', function () {
    Sanctum::actingAs(contractsActor(['users.view']));
    $profile = contractsProfile();
    $company = Company::factory()->create();
    $agreement = CollectiveAgreement::factory()->create(['name' => 'Commercio']);
    $level = CollectiveAgreementLevel::factory()->create(['collective_agreement_id' => $agreement->id, 'code' => 'Q1']);
    EmploymentContract::factory()->create([
        'employment_profile_id' => $profile->id, 'company_id' => $company->id, 'collective_agreement_level_id' => $level->id,
    ]);

    $this->getJson("/api/users/{$profile->user_id}/employment-contracts")
        ->assertOk()
        ->assertJsonPath('data.0.company', ['id' => $company->id, 'label' => $company->denomination])
        ->assertJsonPath('data.0.collective_agreement_level', ['id' => $level->id, 'label' => 'Commercio – Q1']);
});

it('0272 AC-006 a duplicated start date is 422, for the same person only', function () {
    Sanctum::actingAs(contractsActor(['users.update']));
    $profile = contractsProfile();
    EmploymentContract::factory()->create(['employment_profile_id' => $profile->id, 'starts_on' => '2026-01-01']);

    $this->postJson("/api/users/{$profile->user_id}/employment-contracts", contractPayload())
        ->assertStatus(422)->assertJsonValidationErrors('starts_on');

    $other = contractsProfile();
    $this->postJson("/api/users/{$other->user_id}/employment-contracts", contractPayload())->assertCreated();
});

it('0272 AC-006 deleting a non-latest condition is 409', function () {
    Sanctum::actingAs(contractsActor(['users.update']));
    $profile = contractsProfile();
    $first = EmploymentContract::factory()->create(['employment_profile_id' => $profile->id, 'starts_on' => '2025-01-01']);
    EmploymentContract::factory()->create(['employment_profile_id' => $profile->id, 'starts_on' => '2026-01-01']);

    $this->deleteJson("/api/users/{$profile->user_id}/employment-contracts/{$first->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', __('employment_contracts.only_latest_deletable'));

    expect($first->fresh())->not->toBeNull();
});

it('0272 AC-006 deleting the latest realigns the copy to the previous condition, then to null', function () {
    Sanctum::actingAs(contractsActor(['users.update']));
    $profile = contractsProfile();
    $url = "/api/users/{$profile->user_id}/employment-contracts";
    $first = $this->postJson($url, contractPayload(['starts_on' => '2025-01-01', 'relationship_type' => 'employee']))->json('data.id');
    $latest = $this->postJson($url, contractPayload(['starts_on' => '2026-01-01', 'relationship_type' => 'other']))->json('data.id');
    expect($profile->fresh()->relationship_type->value)->toBe('other');

    $this->deleteJson("{$url}/{$latest}")->assertNoContent();
    expect($profile->fresh()->relationship_type->value)->toBe('employee');

    $this->deleteJson("{$url}/{$first}")->assertNoContent();
    expect($profile->fresh()->relationship_type)->toBeNull()
        ->and($profile->contracts()->count())->toBe(0);
});

it('0272 AC-007 salary is hidden without users.manageCompensation and shown with it', function () {
    $profile = contractsProfile();
    EmploymentContract::factory()->withSalary('2500.00')->create(['employment_profile_id' => $profile->id]);

    Sanctum::actingAs(contractsActor(['users.view']));
    $hidden = $this->getJson("/api/users/{$profile->user_id}/employment-contracts")->assertOk();
    expect($hidden->json('data.0'))->not->toHaveKeys(['salary_amount', 'salary_period']);

    Sanctum::actingAs(contractsActor(['users.view', 'users.manageCompensation']));
    $this->getJson("/api/users/{$profile->user_id}/employment-contracts")
        ->assertOk()
        ->assertJsonPath('data.0.salary_amount', '2500.00')
        ->assertJsonPath('data.0.salary_period', 'monthly');
});

it('0272 AC-007 writing salary needs users.manageCompensation (403) and is saved with it', function () {
    $profile = contractsProfile();
    $payload = contractPayload(['salary_amount' => 1800.5, 'salary_period' => 'monthly']);

    Sanctum::actingAs(contractsActor(['users.update']));
    $this->postJson("/api/users/{$profile->user_id}/employment-contracts", $payload)->assertForbidden();
    expect(EmploymentContract::count())->toBe(0);

    Sanctum::actingAs(contractsActor(['users.update', 'users.manageCompensation']));
    $this->postJson("/api/users/{$profile->user_id}/employment-contracts", $payload)
        ->assertCreated()
        ->assertJsonPath('data.salary_amount', '1800.50')
        ->assertJsonPath('data.salary_period', 'monthly');
    expect(EmploymentContract::first()->salary_amount)->toBe('1800.50');
});

it('0272 validates the store payload', function () {
    Sanctum::actingAs(contractsActor(['users.update', 'users.manageCompensation']));
    $profile = contractsProfile();
    $url = "/api/users/{$profile->user_id}/employment-contracts";

    $this->postJson($url, [])->assertStatus(422)->assertJsonValidationErrors(['starts_on', 'relationship_type']);
    $this->postJson($url, contractPayload(['relationship_type' => 'bogus']))->assertStatus(422)->assertJsonValidationErrors('relationship_type');
    $this->postJson($url, contractPayload(['company_id' => 999999]))->assertStatus(422)->assertJsonValidationErrors('company_id');
    $this->postJson($url, contractPayload(['collective_agreement_level_id' => 999999]))->assertStatus(422)->assertJsonValidationErrors('collective_agreement_level_id');
    $this->postJson($url, contractPayload(['salary_amount' => 100]))->assertStatus(422)->assertJsonValidationErrors('salary_period');
    $this->postJson($url, contractPayload(['salary_amount' => -1, 'salary_period' => 'yearly']))->assertStatus(422)->assertJsonValidationErrors('salary_amount');
    $this->postJson($url, contractPayload(['salary_amount' => 1, 'salary_period' => 'weekly']))->assertStatus(422)->assertJsonValidationErrors('salary_period');
    $this->postJson($url, contractPayload(['change_reason' => str_repeat('x', 256)]))->assertStatus(422)->assertJsonValidationErrors('change_reason');
});

it('0272 enforces authorization on every endpoint', function () {
    $profile = contractsProfile();
    $contract = EmploymentContract::factory()->create(['employment_profile_id' => $profile->id]);
    $base = "/api/users/{$profile->user_id}/employment-contracts";

    Sanctum::actingAs(contractsActor(['users.update']));
    $this->getJson($base)->assertForbidden();

    Sanctum::actingAs(contractsActor(['users.view']));
    $this->postJson($base, contractPayload())->assertForbidden();
    $this->deleteJson("{$base}/{$contract->id}")->assertForbidden();
    expect($contract->fresh())->not->toBeNull();
});

it('0272 a user without an employment profile has an empty list and is 404 on store', function () {
    Sanctum::actingAs(contractsActor(['users.view', 'users.update']));
    $user = User::factory()->create();

    // REQUIREMENT CHANGED (spec 0272 rev.2): the list is empty instead of a 404.
    $this->getJson("/api/users/{$user->id}/employment-contracts")->assertOk()->assertJsonPath('data', []);
    $this->postJson("/api/users/{$user->id}/employment-contracts", contractPayload())->assertNotFound();
    $this->getJson('/api/users/999999/employment-contracts')->assertNotFound();
});

it('0272 a condition of another person is a 404 on delete (nested scoping)', function () {
    Sanctum::actingAs(contractsActor(['users.update']));
    $mine = contractsProfile();
    $theirs = EmploymentContract::factory()->create(['employment_profile_id' => contractsProfile()->id]);

    $this->deleteJson("/api/users/{$mine->user_id}/employment-contracts/{$theirs->id}")->assertNotFound();
    $this->deleteJson("/api/users/{$mine->user_id}/employment-contracts/999999")->assertNotFound();
    expect($theirs->fresh())->not->toBeNull();
});

it('0272 requires authentication', function () {
    $this->getJson('/api/users/1/employment-contracts')->assertUnauthorized();
});
