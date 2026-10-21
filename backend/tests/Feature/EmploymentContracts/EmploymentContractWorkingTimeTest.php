<?php

use App\Models\ContractType;
use App\Models\EmploymentContract;
use App\Models\EmploymentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(fn () => Carbon::setTestNow('2026-10-16 10:00:00'));
afterEach(fn () => Carbon::setTestNow());

function workingTimeActor(): User
{
    foreach (['users.view', 'users.update'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $actor = User::factory()->create();
    $actor->givePermissionTo(['users.view', 'users.update']);

    return $actor;
}

function workingTimeProfile(): EmploymentProfile
{
    return EmploymentProfile::factory()->create([
        'relationship_type' => null, 'company_id' => null, 'contract_type_id' => null,
        'working_time' => null, 'contract_expires_on' => null,
    ]);
}

function workingTimeUrl(EmploymentProfile $profile): string
{
    return "/api/users/{$profile->user_id}/employment-contracts";
}

it('0291 AC-001 an intern condition needs no type, working time or expiry; an unknown type is rejected', function () {
    Sanctum::actingAs(workingTimeActor());
    $url = workingTimeUrl(workingTimeProfile());

    $this->postJson($url, ['starts_on' => '2026-01-01', 'relationship_type' => 'intern'])
        ->assertCreated()
        ->assertJsonPath('data.relationship_type', 'intern')
        ->assertJsonPath('data.working_time', null)
        ->assertJsonPath('data.contract_expires_on', null);

    $this->postJson($url, ['starts_on' => '2026-02-01', 'relationship_type' => 'apprentice'])
        ->assertStatus(422)->assertJsonValidationErrors('relationship_type');
});

it('0291 AC-002 working time is required for employees only and limited to the enum', function () {
    Sanctum::actingAs(workingTimeActor());
    $url = workingTimeUrl(workingTimeProfile());
    $type = ContractType::factory()->create();
    $employee = ['relationship_type' => 'employee', 'contract_type_id' => $type->id];

    $this->postJson($url, ['starts_on' => '2026-01-01', ...$employee])
        ->assertStatus(422)->assertJsonValidationErrors('working_time');

    $this->postJson($url, ['starts_on' => '2026-01-01', ...$employee, 'working_time' => 'half'])
        ->assertStatus(422)->assertJsonValidationErrors('working_time');

    $this->postJson($url, ['starts_on' => '2026-01-01', ...$employee, 'working_time' => 'part_time'])
        ->assertCreated()->assertJsonPath('data.working_time', 'part_time');

    $this->postJson($url, ['starts_on' => '2026-02-01', 'relationship_type' => 'self_employed'])
        ->assertCreated()->assertJsonPath('data.working_time', null);
});

it('0291 AC-003 the expiry cannot precede the start and is returned as Y-m-d', function () {
    Sanctum::actingAs(workingTimeActor());
    $url = workingTimeUrl(workingTimeProfile());
    $base = ['relationship_type' => 'self_employed'];

    $this->postJson($url, ['starts_on' => '2026-03-01', ...$base, 'contract_expires_on' => '2026-02-28'])
        ->assertStatus(422)->assertJsonValidationErrors('contract_expires_on');

    $this->postJson($url, ['starts_on' => '2026-03-01', ...$base, 'contract_expires_on' => 'not-a-date'])
        ->assertStatus(422)->assertJsonValidationErrors('contract_expires_on');

    $this->postJson($url, ['starts_on' => '2026-03-01', ...$base, 'contract_expires_on' => '2026-03-01'])
        ->assertCreated()->assertJsonPath('data.contract_expires_on', '2026-03-01');

    $this->postJson($url, ['starts_on' => '2026-04-01', ...$base])
        ->assertCreated()->assertJsonPath('data.contract_expires_on', null);
});

it('0291 AC-004 a past start copies working time and expiry on the profile and the user record', function () {
    Sanctum::actingAs(workingTimeActor());
    $profile = workingTimeProfile();

    $this->postJson(workingTimeUrl($profile), [
        'starts_on' => '2026-01-01', 'relationship_type' => 'intern',
        'working_time' => 'full_time', 'contract_expires_on' => '2027-01-01',
    ])->assertCreated();

    $fresh = $profile->fresh();
    expect($fresh->working_time->value)->toBe('full_time')
        ->and($fresh->contract_expires_on->toDateString())->toBe('2027-01-01');

    $this->getJson("/api/users/{$profile->user_id}")->assertOk()
        ->assertJsonPath('data.employment.current_contract.working_time', 'full_time')
        ->assertJsonPath('data.employment.current_contract.contract_expires_on', '2027-01-01');
});

it('0291 AC-004 a future start changes the copy only with the daily sync', function () {
    Sanctum::actingAs(workingTimeActor());
    $profile = workingTimeProfile();
    $url = workingTimeUrl($profile);

    $this->postJson($url, ['starts_on' => '2026-01-01', 'relationship_type' => 'other', 'working_time' => 'full_time'])->assertCreated();
    $this->postJson($url, ['starts_on' => '2026-12-01', 'relationship_type' => 'other', 'working_time' => 'part_time', 'contract_expires_on' => '2027-06-30'])->assertCreated();

    expect($profile->fresh()->working_time->value)->toBe('full_time');

    $this->artisan('employment:daily-sync')->assertSuccessful();
    expect($profile->fresh()->working_time->value)->toBe('full_time');

    $this->travelTo('2026-12-01 00:30:00');
    $this->artisan('employment:daily-sync')->assertSuccessful();
    expect($profile->fresh()->working_time->value)->toBe('part_time')
        ->and($profile->fresh()->contract_expires_on->toDateString())->toBe('2027-06-30');
});

it('0291 AC-004 deleting the latest condition restores the previous values, then null', function () {
    Sanctum::actingAs(workingTimeActor());
    $profile = workingTimeProfile();
    $url = workingTimeUrl($profile);

    $firstId = $this->postJson($url, ['starts_on' => '2025-01-01', 'relationship_type' => 'other', 'working_time' => 'full_time', 'contract_expires_on' => '2025-12-31'])->json('data.id');
    $secondId = $this->postJson($url, ['starts_on' => '2026-01-01', 'relationship_type' => 'other', 'working_time' => 'part_time'])->json('data.id');
    expect($profile->fresh()->working_time->value)->toBe('part_time')
        ->and($profile->fresh()->contract_expires_on)->toBeNull();

    $this->deleteJson("{$url}/{$secondId}")->assertNoContent();
    expect($profile->fresh()->working_time->value)->toBe('full_time')
        ->and($profile->fresh()->contract_expires_on->toDateString())->toBe('2025-12-31');

    $this->deleteJson("{$url}/{$firstId}")->assertNoContent();
    expect($profile->fresh()->working_time)->toBeNull()
        ->and($profile->fresh()->contract_expires_on)->toBeNull();
});

it('0291 AC-004 PUT users ignores employment.working_time and contract_expires_on', function () {
    Sanctum::actingAs(workingTimeActor());
    $profile = workingTimeProfile();
    EmploymentContract::factory()->create([
        'employment_profile_id' => $profile->id, 'starts_on' => '2026-01-01', 'relationship_type' => 'other',
        'working_time' => 'full_time', 'contract_expires_on' => '2027-01-01',
    ]);
    $profile->update(['working_time' => 'full_time', 'contract_expires_on' => '2027-01-01']);

    $this->putJson("/api/users/{$profile->user_id}", [
        'employment' => ['working_time' => 'part_time', 'contract_expires_on' => '2030-01-01', 'hired_at' => '2024-01-15',
            'covers_all_product_categories' => true, 'business_function_ids' => [membershipFunctionId()],
        ],
    ])->assertOk();

    expect($profile->fresh()->working_time->value)->toBe('full_time')
        ->and($profile->fresh()->contract_expires_on->toDateString())->toBe('2027-01-01');
});
