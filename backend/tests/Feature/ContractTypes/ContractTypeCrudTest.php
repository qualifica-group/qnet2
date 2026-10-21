<?php

use App\Models\ContractType;
use App\Models\EmploymentContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('0290 AC-001: create answers 201 with defaults and the permissions block', function () {
    Sanctum::actingAs(hrCatalogUserWith('contract-types', ['create']));

    $this->postJson('/api/contract-types', ['name' => 'Stagionale'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Stagionale')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.sort_order', 0)
        ->assertJsonStructure(['success', 'message', 'data' => ['id', 'created_at', 'updated_at'], 'permissions']);

    expect(ContractType::where('name', 'Stagionale')->exists())->toBeTrue();
});

it('0290 AC-001: a duplicated, missing or too long name and a negative order are 422', function () {
    Sanctum::actingAs(hrCatalogUserWith('contract-types', ['create', 'update']));
    ContractType::factory()->create(['name' => 'Stagionale']);
    $other = ContractType::factory()->create(['name' => 'Altro']);

    $this->postJson('/api/contract-types', ['name' => 'Stagionale'])->assertStatus(422)->assertJsonValidationErrors('name');
    $this->postJson('/api/contract-types', [])->assertStatus(422)->assertJsonValidationErrors('name');
    $this->postJson('/api/contract-types', ['name' => str_repeat('a', 101), 'sort_order' => -1])
        ->assertStatus(422)->assertJsonValidationErrors(['name', 'sort_order']);
    $this->patchJson("/api/contract-types/{$other->id}", ['name' => 'Stagionale'])->assertStatus(422)->assertJsonValidationErrors('name');
    $this->patchJson("/api/contract-types/{$other->id}", ['name' => 'Altro'])->assertOk();
});

it('0290 AC-001: show and update answer 200, deactivation included', function () {
    Sanctum::actingAs(hrCatalogUserWith('contract-types', ['view', 'update']));
    $type = ContractType::factory()->create(['name' => 'Stagionale']);

    $this->getJson("/api/contract-types/{$type->id}")
        ->assertOk()->assertJsonPath('data.name', 'Stagionale')->assertJsonStructure(['permissions']);
    $this->putJson("/api/contract-types/{$type->id}", ['name' => 'Stagionale 2', 'is_active' => false, 'sort_order' => 4])
        ->assertOk()
        ->assertJsonPath('data.name', 'Stagionale 2')
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.sort_order', 4);
});

it('0290 AC-002: deleting a type used by a condition is 409, a free one is 204', function () {
    Sanctum::actingAs(hrCatalogUserWith('contract-types', ['delete']));
    $used = ContractType::factory()->create();
    EmploymentContract::factory()->create(['contract_type_id' => $used->id]);
    $free = ContractType::factory()->create();

    $this->deleteJson("/api/contract-types/{$used->id}")->assertStatus(409);
    $this->deleteJson("/api/contract-types/{$free->id}")->assertNoContent();

    expect(ContractType::find($used->id))->not->toBeNull()
        ->and(ContractType::find($free->id))->toBeNull();
});

it('0290 AC-003: every endpoint answers 403 without the permission', function () {
    Sanctum::actingAs(hrCatalogUserWith('contract-types', []));
    $type = ContractType::factory()->create();

    $this->getJson("/api/contract-types/{$type->id}")->assertForbidden();
    $this->postJson('/api/contract-types', ['name' => 'X'])->assertForbidden();
    $this->patchJson("/api/contract-types/{$type->id}", ['name' => 'X'])->assertForbidden();
    $this->deleteJson("/api/contract-types/{$type->id}")->assertForbidden();
    $this->postJson('/api/tables/contract-types/rows', ['startRow' => 0, 'endRow' => 10])->assertForbidden();
});

it('0290 AC-003: the table lists the rows and exposes name, is_active and sort_order', function () {
    Sanctum::actingAs(hrCatalogUserWith('contract-types', ['viewAny', 'update']));
    ContractType::factory()->create(['name' => 'Stagionale']);

    $columns = collect($this->getJson('/api/tables/contract-types/columns')->assertOk()->json('data.columns'))->pluck('id')->all();
    $row = $this->postJson('/api/tables/contract-types/rows', ['startRow' => 0, 'endRow' => 10])->assertOk()->json('items.0');

    expect($columns)->toBe(['id', 'name', 'is_active', 'sort_order'])
        ->and($row['name'])->toBe('Stagionale')
        ->and($row['abilities']['update'])->toBeTrue();
});

it('0290 AC-003: for-select needs only login, lists active types in order and hydrates ids[]', function () {
    ContractType::factory()->create(['name' => 'Zeta', 'sort_order' => 1]);
    ContractType::factory()->create(['name' => 'Alfa', 'sort_order' => 1]);
    ContractType::factory()->create(['name' => 'Primo', 'sort_order' => 0]);
    $off = ContractType::factory()->inactive()->create(['name' => 'Spento']);

    $this->getJson('/api/contract-types/for-select')->assertUnauthorized();

    Sanctum::actingAs(User::factory()->create());
    $response = $this->getJson('/api/contract-types/for-select')->assertOk();

    expect(collect($response->json('items'))->pluck('label')->all())->toBe(['Primo', 'Alfa', 'Zeta'])
        ->and($response->json('items.0'))->toHaveKeys(['id', 'label']);

    $hydrated = $this->getJson('/api/contract-types/for-select?'.http_build_query(['ids' => [$off->id]]))->assertOk();
    expect(collect($hydrated->json('items'))->pluck('label')->all())->toContain('Spento');

    $searched = $this->getJson('/api/contract-types/for-select?search=alf')->assertOk();
    expect(collect($searched->json('items'))->pluck('label')->all())->toBe(['Alfa']);
});
