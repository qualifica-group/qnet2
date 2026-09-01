<?php

use App\Models\Product;
use App\Models\QuoteLine;
use App\Models\Role;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\RoleAssignmentGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('unitOfMeasureUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function unitOfMeasureUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("units-of-measure.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("units-of-measure.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// create — POST /api/units-of-measure (AC-010..012)
// ---------------------------------------------------------------------------

it('create: 201 + persists all fields (AC-010)', function () {
    $actor = unitOfMeasureUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/units-of-measure', ['name' => 'Chilogrammi', 'symbol' => 'kg', 'code' => 'kilogram', 'description' => 'Peso in chilogrammi'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Chilogrammi')
        ->assertJsonPath('data.symbol', 'kg')
        ->assertJsonPath('data.code', 'kilogram')
        ->assertJsonPath('data.description', 'Peso in chilogrammi')
        ->assertJsonStructure(['data' => ['id', 'code', 'name', 'symbol', 'description', 'created_at', 'updated_at'], 'permissions']);

    $this->assertDatabaseHas('units_of_measure', ['name' => 'Chilogrammi', 'symbol' => 'kg', 'code' => 'kilogram']);
});

it('create: 201 with only name+symbol+code, description defaults to null (AC-010)', function () {
    $actor = unitOfMeasureUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/units-of-measure', ['name' => 'Litri', 'symbol' => 'l', 'code' => 'litre'])
        ->assertCreated()
        ->assertJsonPath('data.description', null);
});

it('create: 422 when name/symbol/code already exist (AC-011)', function () {
    $actor = unitOfMeasureUserWith(['create']);
    UnitOfMeasure::factory()->create(['name' => 'Taken Name', 'symbol' => 'tn', 'code' => 'taken_code']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/units-of-measure', ['name' => 'Taken Name', 'symbol' => 'x1', 'code' => 'fresh_1'])
        ->assertStatus(422)->assertJsonValidationErrors('name');

    $this->postJson('/api/units-of-measure', ['name' => 'Fresh Name 1', 'symbol' => 'tn', 'code' => 'fresh_2'])
        ->assertStatus(422)->assertJsonValidationErrors('symbol');

    $this->postJson('/api/units-of-measure', ['name' => 'Fresh Name 2', 'symbol' => 'x2', 'code' => 'taken_code'])
        ->assertStatus(422)->assertJsonValidationErrors('code');

    expect(UnitOfMeasure::where('name', 'Taken Name')->count())->toBe(1);
});

it('create: 422 when code is out of the snake_case regex (AC-012)', function (string $badCode) {
    $actor = unitOfMeasureUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/units-of-measure', ['name' => "Bad Code {$badCode}", 'symbol' => 's'.$badCode, 'code' => $badCode])
        ->assertStatus(422)->assertJsonValidationErrors('code');
})->with(['Litri', '1abc', 'a-b', 'a b']);

it('create: 422 when name/symbol/code is missing', function () {
    $actor = unitOfMeasureUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/units-of-measure', ['symbol' => 's', 'code' => 'has_symbol_code'])
        ->assertStatus(422)->assertJsonValidationErrors('name');

    $this->postJson('/api/units-of-measure', ['name' => 'Has Name', 'code' => 'has_name_code'])
        ->assertStatus(422)->assertJsonValidationErrors('symbol');

    $this->postJson('/api/units-of-measure', ['name' => 'Has Name 2', 'symbol' => 's2'])
        ->assertStatus(422)->assertJsonValidationErrors('code');
});

// ---------------------------------------------------------------------------
// show — GET /api/units-of-measure/{unitOfMeasure}
// ---------------------------------------------------------------------------

it('show: 200 with the full contract shape + permissions block', function () {
    $actor = unitOfMeasureUserWith(['view']);
    $target = UnitOfMeasure::factory()->create(['name' => 'Visible', 'symbol' => 'vs', 'code' => 'visible']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/units-of-measure/{$target->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $target->id)
        ->assertJsonPath('data.name', 'Visible')
        ->assertJsonPath('data.code', 'visible')
        ->assertJsonStructure(['data', 'permissions']);
});

it('show: 404 for a non-existent id, no class/model name leaked', function () {
    $actor = unitOfMeasureUserWith(['view']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/units-of-measure/999999')->assertNotFound();

    expect($response->json('message'))->not->toContain('UnitOfMeasure')->not->toContain('App\\');
});

// ---------------------------------------------------------------------------
// update — PATCH /api/units-of-measure/{unitOfMeasure} (AC-013)
// ---------------------------------------------------------------------------

it('update: PATCH partial {description} updates only that field', function () {
    $actor = unitOfMeasureUserWith(['update']);
    $target = UnitOfMeasure::factory()->create(['name' => 'Kept Name', 'description' => 'Before']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/units-of-measure/{$target->id}", ['description' => 'After'])
        ->assertOk()
        ->assertJsonPath('data.description', 'After')
        ->assertJsonPath('data.name', 'Kept Name');

    $this->assertDatabaseHas('units_of_measure', ['id' => $target->id, 'name' => 'Kept Name', 'description' => 'After']);
});

it('update: 422 when name/symbol is submitted with a value duplicating ANOTHER record', function () {
    $actor = unitOfMeasureUserWith(['update']);
    UnitOfMeasure::factory()->create(['name' => 'Other', 'symbol' => 'ot']);
    $target = UnitOfMeasure::factory()->create(['name' => 'Mine', 'symbol' => 'mn']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/units-of-measure/{$target->id}", ['name' => 'Other'])
        ->assertStatus(422)->assertJsonValidationErrors('name');

    $this->patchJson("/api/units-of-measure/{$target->id}", ['symbol' => 'ot'])
        ->assertStatus(422)->assertJsonValidationErrors('symbol');
});

it('update: accepts resubmitting its own unchanged name/symbol', function () {
    $actor = unitOfMeasureUserWith(['update']);
    $target = UnitOfMeasure::factory()->create(['name' => 'Mine', 'symbol' => 'mn']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/units-of-measure/{$target->id}", ['name' => 'Mine', 'symbol' => 'mn'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Mine');
});

it('update: 422 when code is submitted with a DIFFERENT value, code unchanged at DB (AC-013)', function () {
    $actor = unitOfMeasureUserWith(['update']);
    $target = UnitOfMeasure::factory()->create(['code' => 'original_code']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/units-of-measure/{$target->id}", ['code' => 'changed_code'])
        ->assertStatus(422)->assertJsonValidationErrors('code');

    $this->assertDatabaseHas('units_of_measure', ['id' => $target->id, 'code' => 'original_code']);
});

it('update: 422 when code is submitted with the SAME value (prohibited rejects presence, not just change) (AC-013)', function () {
    $actor = unitOfMeasureUserWith(['update']);
    $target = UnitOfMeasure::factory()->create(['code' => 'same_code']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/units-of-measure/{$target->id}", ['code' => 'same_code'])
        ->assertStatus(422)->assertJsonValidationErrors('code');
});

it('update: 422 on code even for the privileged super-admin role, no exception (AC-013)', function () {
    Role::create(['name' => RoleAssignmentGuard::PRIVILEGED_ROLE]);
    $actor = User::factory()->create();
    $actor->assignRole(RoleAssignmentGuard::PRIVILEGED_ROLE);
    $target = UnitOfMeasure::factory()->create(['code' => 'locked_code']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/units-of-measure/{$target->id}", ['code' => 'attempted_change'])
        ->assertStatus(422)->assertJsonValidationErrors('code');

    $this->assertDatabaseHas('units_of_measure', ['id' => $target->id, 'code' => 'locked_code']);
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/units-of-measure/{unitOfMeasure} (AC-014..016, D-7)
// ---------------------------------------------------------------------------

it('delete: 204 + removed from DB when unreferenced (AC-014)', function () {
    $actor = unitOfMeasureUserWith(['delete']);
    $target = UnitOfMeasure::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/units-of-measure/{$target->id}")->assertNoContent();

    $this->assertDatabaseMissing('units_of_measure', ['id' => $target->id]);
});

it('delete: 404 for a non-existent id', function () {
    $actor = unitOfMeasureUserWith(['delete']);
    Sanctum::actingAs($actor);

    $this->deleteJson('/api/units-of-measure/999999')->assertNotFound();
});

it('delete: 409 when used by a product, row NOT removed (AC-015)', function () {
    $actor = unitOfMeasureUserWith(['delete']);
    $target = UnitOfMeasure::factory()->create();
    Product::factory()->create(['unit_of_measure_id' => $target->id]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/units-of-measure/{$target->id}")
        ->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'This unit of measure is used by a product and cannot be deleted.');

    $this->assertDatabaseHas('units_of_measure', ['id' => $target->id]);
});

it('delete: 409 when used only by a quote line, row NOT removed (AC-016)', function () {
    $actor = unitOfMeasureUserWith(['delete']);
    $target = UnitOfMeasure::factory()->create();
    QuoteLine::factory()->create(['unit_of_measure_id' => $target->id]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/units-of-measure/{$target->id}")
        ->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'This unit of measure is used by a quote line and cannot be deleted.');

    $this->assertDatabaseHas('units_of_measure', ['id' => $target->id]);
});
