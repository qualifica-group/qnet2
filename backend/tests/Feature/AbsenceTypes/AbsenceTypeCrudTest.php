<?php

use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

const ABSENCE_TYPE_PAYLOAD = ['name' => 'Ferie', 'deducts_balance' => true, 'requires_approval' => true];

it('0278 AC-001: every endpoint answers 403 without the permission', function () {
    Sanctum::actingAs(hrCatalogUserWith('absence-types', []));
    $type = AbsenceType::factory()->create();

    $this->getJson("/api/absence-types/{$type->id}")->assertForbidden();
    $this->postJson('/api/absence-types', ABSENCE_TYPE_PAYLOAD)->assertForbidden();
    $this->patchJson("/api/absence-types/{$type->id}", ['name' => 'X'])->assertForbidden();
    $this->deleteJson("/api/absence-types/{$type->id}")->assertForbidden();
    $this->postJson('/api/tables/absence-types/rows', ['startRow' => 0, 'endRow' => 10])->assertForbidden();
});

it('0278 AC-001: create answers 201 with data and the permissions block', function () {
    Sanctum::actingAs(hrCatalogUserWith('absence-types', ['create']));

    $this->postJson('/api/absence-types', ABSENCE_TYPE_PAYLOAD)
        ->assertCreated()
        ->assertJsonPath('data.name', 'Ferie')
        ->assertJsonPath('data.deducts_balance', true)
        ->assertJsonPath('data.is_active', true)
        ->assertJsonStructure(['success', 'message', 'data' => ['id', 'sort_order'], 'permissions']);

    expect(AbsenceType::where('name', 'Ferie')->exists())->toBeTrue();
});

it('0278 AC-001: show and patch answer 200 with the permissions block', function () {
    Sanctum::actingAs(hrCatalogUserWith('absence-types', ['view', 'update']));
    $type = AbsenceType::factory()->create(['name' => 'Permesso']);

    $this->getJson("/api/absence-types/{$type->id}")
        ->assertOk()->assertJsonPath('data.name', 'Permesso')->assertJsonStructure(['permissions']);
    $this->patchJson("/api/absence-types/{$type->id}", ['name' => 'Permesso retribuito', 'is_active' => false, 'sort_order' => 4])
        ->assertOk()
        ->assertJsonPath('data.name', 'Permesso retribuito')
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.sort_order', 4)
        ->assertJsonStructure(['permissions']);
});

it('0278 AC-001: validation rejects a duplicated name, missing flags and a negative order', function () {
    Sanctum::actingAs(hrCatalogUserWith('absence-types', ['create', 'update']));
    AbsenceType::factory()->create(['name' => 'Ferie']);
    $other = AbsenceType::factory()->create(['name' => 'Malattia']);

    $this->postJson('/api/absence-types', ABSENCE_TYPE_PAYLOAD)->assertStatus(422)->assertJsonValidationErrors('name');
    $this->postJson('/api/absence-types', ['name' => 'Nuovo', 'sort_order' => -1])
        ->assertStatus(422)->assertJsonValidationErrors(['deducts_balance', 'requires_approval', 'sort_order']);
    $this->patchJson("/api/absence-types/{$other->id}", ['name' => 'Ferie'])->assertStatus(422)->assertJsonValidationErrors('name');
    $this->patchJson("/api/absence-types/{$other->id}", ['name' => 'Malattia'])->assertOk();
});

it('0278 AC-001: deleting a type used by a request is 409, a free one is 204', function () {
    Sanctum::actingAs(hrCatalogUserWith('absence-types', ['delete']));
    $used = AbsenceType::factory()->create();
    AbsenceRequest::factory()->create(['absence_type_id' => $used->id]);
    $free = AbsenceType::factory()->create();

    $this->deleteJson("/api/absence-types/{$used->id}")->assertStatus(409);
    $this->deleteJson("/api/absence-types/{$free->id}")->assertNoContent();

    expect(AbsenceType::find($used->id))->not->toBeNull()
        ->and(AbsenceType::find($free->id))->toBeNull();
});

it('0278 AC-001: for-select lists only active types ordered by sort_order and name, auth only', function () {
    AbsenceType::factory()->create(['name' => 'Zeta', 'sort_order' => 1]);
    AbsenceType::factory()->create(['name' => 'Alfa', 'sort_order' => 1]);
    AbsenceType::factory()->create(['name' => 'Primo', 'sort_order' => 0, 'deducts_balance' => false, 'requires_approval' => false]);
    AbsenceType::factory()->inactive()->create(['name' => 'Spento']);

    $this->getJson('/api/absence-types/for-select')->assertUnauthorized();

    Sanctum::actingAs(User::factory()->create());
    $response = $this->getJson('/api/absence-types/for-select')->assertOk();

    expect(collect($response->json('data'))->pluck('name')->all())->toBe(['Primo', 'Alfa', 'Zeta'])
        ->and($response->json('data.0'))->toHaveKeys(['id', 'name', 'deducts_balance', 'requires_approval'])
        ->and($response->json('data.0.deducts_balance'))->toBeFalse();
});

it('0278 AC-001: the table lists the rows and the meta endpoint answers', function () {
    Sanctum::actingAs(hrCatalogUserWith('absence-types', ['viewAny', 'update']));
    AbsenceType::factory()->create(['name' => 'Ferie', 'sort_order' => 0]);

    $columns = collect($this->getJson('/api/tables/absence-types/columns')->assertOk()->json('data.columns'))->pluck('id')->all();
    $row = $this->postJson('/api/tables/absence-types/rows', ['startRow' => 0, 'endRow' => 10])->assertOk()->json('items.0');

    expect($columns)->toContain('name', 'deducts_balance', 'requires_approval', 'is_active', 'sort_order')
        ->and($row['name'])->toBe('Ferie')
        ->and($row['abilities']['update'])->toBeTrue();

    $this->getJson('/api/meta/absence-types')->assertOk();
});
