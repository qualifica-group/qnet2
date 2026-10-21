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

// REQUIREMENT CHANGED (spec 0291 D-3): an employee condition now needs a working time, so the
// employee payloads below carry `working_time`; the contract type rules are unchanged.
beforeEach(fn () => Carbon::setTestNow('2026-10-16 10:00:00'));
afterEach(fn () => Carbon::setTestNow());

function contractTypeActor(): User
{
    foreach (['users.view', 'users.update'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $actor = User::factory()->create();
    $actor->givePermissionTo(['users.view', 'users.update']);

    return $actor;
}

function contractTypeProfile(): EmploymentProfile
{
    return EmploymentProfile::factory()->create(['relationship_type' => null, 'company_id' => null, 'contract_type_id' => null]);
}

function contractTypeUrl(EmploymentProfile $profile): string
{
    return "/api/users/{$profile->user_id}/employment-contracts";
}

it('0290 AC-005 an employee condition needs an active contract type, others do not', function () {
    Sanctum::actingAs(contractTypeActor());
    $profile = contractTypeProfile();
    $url = contractTypeUrl($profile);

    $this->postJson($url, ['starts_on' => '2026-01-01', 'relationship_type' => 'employee'])
        ->assertStatus(422)->assertJsonValidationErrors('contract_type_id');

    $this->postJson($url, ['starts_on' => '2026-01-01', 'relationship_type' => 'employee', 'contract_type_id' => ContractType::factory()->inactive()->create()->id])
        ->assertStatus(422)->assertJsonValidationErrors('contract_type_id');

    $this->postJson($url, ['starts_on' => '2026-01-01', 'relationship_type' => 'employee', 'contract_type_id' => 999999])
        ->assertStatus(422)->assertJsonValidationErrors('contract_type_id');

    $this->postJson($url, ['starts_on' => '2026-01-01', 'relationship_type' => 'self_employed'])
        ->assertCreated()->assertJsonPath('data.contract_type', null);
});

it('0290 AC-006 the condition returns its contract type on creation and in the list', function () {
    Sanctum::actingAs(contractTypeActor());
    $profile = contractTypeProfile();
    $type = ContractType::factory()->create(['name' => 'Apprendistato']);
    $url = contractTypeUrl($profile);

    $this->postJson($url, ['starts_on' => '2025-01-01', 'relationship_type' => 'self_employed'])->assertCreated();
    $this->postJson($url, ['starts_on' => '2026-01-01', 'relationship_type' => 'employee', 'contract_type_id' => $type->id, 'working_time' => 'full_time'])
        ->assertCreated()
        ->assertJsonPath('data.contract_type', ['id' => $type->id, 'name' => 'Apprendistato']);

    $this->getJson($url)->assertOk()
        ->assertJsonPath('data.0.contract_type', ['id' => $type->id, 'name' => 'Apprendistato'])
        ->assertJsonPath('data.1.contract_type', null);
});

it('0290 AC-007 a past start copies the type on the profile and the user record', function () {
    Sanctum::actingAs(contractTypeActor());
    $profile = contractTypeProfile();
    $type = ContractType::factory()->create(['name' => 'Tirocinio']);

    $this->postJson(contractTypeUrl($profile), ['starts_on' => '2026-01-01', 'relationship_type' => 'employee', 'contract_type_id' => $type->id, 'working_time' => 'full_time'])
        ->assertCreated();

    expect($profile->fresh()->contract_type_id)->toBe($type->id);

    $this->getJson("/api/users/{$profile->user_id}")->assertOk()
        ->assertJsonPath('data.employment.current_contract.contract_type', ['id' => $type->id, 'name' => 'Tirocinio']);
});

it('0290 AC-007 a future start leaves the copy untouched until the daily sync reaches it', function () {
    Sanctum::actingAs(contractTypeActor());
    $profile = contractTypeProfile();
    $old = ContractType::factory()->create();
    $new = ContractType::factory()->create();
    $url = contractTypeUrl($profile);

    $this->postJson($url, ['starts_on' => '2026-01-01', 'relationship_type' => 'employee', 'contract_type_id' => $old->id, 'working_time' => 'full_time'])->assertCreated();
    $this->postJson($url, ['starts_on' => '2026-12-01', 'relationship_type' => 'employee', 'contract_type_id' => $new->id, 'working_time' => 'full_time'])->assertCreated();

    expect($profile->fresh()->contract_type_id)->toBe($old->id);

    $this->artisan('employment:daily-sync')->assertSuccessful();
    expect($profile->fresh()->contract_type_id)->toBe($old->id);

    $this->travelTo('2026-12-01 00:30:00');
    $this->artisan('employment:daily-sync')->assertSuccessful();
    expect($profile->fresh()->contract_type_id)->toBe($new->id);
});

it('0290 AC-007 deleting the latest condition restores the previous type, then null', function () {
    Sanctum::actingAs(contractTypeActor());
    $profile = contractTypeProfile();
    $first = ContractType::factory()->create();
    $second = ContractType::factory()->create();
    $url = contractTypeUrl($profile);

    $firstId = $this->postJson($url, ['starts_on' => '2025-01-01', 'relationship_type' => 'employee', 'contract_type_id' => $first->id, 'working_time' => 'full_time'])->json('data.id');
    $secondId = $this->postJson($url, ['starts_on' => '2026-01-01', 'relationship_type' => 'employee', 'contract_type_id' => $second->id, 'working_time' => 'full_time'])->json('data.id');
    expect($profile->fresh()->contract_type_id)->toBe($second->id);

    $this->deleteJson("{$url}/{$secondId}")->assertNoContent();
    expect($profile->fresh()->contract_type_id)->toBe($first->id);

    $this->deleteJson("{$url}/{$firstId}")->assertNoContent();
    expect($profile->fresh()->contract_type_id)->toBeNull();
});

it('0290 AC-008 PUT users ignores employment.contract_type_id', function () {
    Sanctum::actingAs(contractTypeActor());
    $type = ContractType::factory()->create();
    $other = ContractType::factory()->create();
    $profile = contractTypeProfile();
    EmploymentContract::factory()->create([
        'employment_profile_id' => $profile->id, 'starts_on' => '2026-01-01', 'relationship_type' => 'employee', 'contract_type_id' => $type->id,
    ]);
    $profile->update(['contract_type_id' => $type->id]);

    $this->putJson("/api/users/{$profile->user_id}", [
        'employment' => ['contract_type_id' => $other->id, 'hired_at' => '2024-01-15',
            'covers_all_product_categories' => true, 'business_function_ids' => [membershipFunctionId()],
        ],
    ])->assertOk();

    expect($profile->fresh()->contract_type_id)->toBe($type->id);
});
