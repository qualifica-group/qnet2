<?php

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('attributeUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function attributeUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("attributes.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("attributes.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// show — GET /api/attributes/{attribute}
// ---------------------------------------------------------------------------

it('show: 200 with the full data shape', function () {
    $actor = attributeUserWith(['view']);
    $target = Attribute::factory()->enum(2)->create(['code' => 'color', 'name' => 'Color']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/attributes/{$target->id}")
        ->assertOk()
        ->assertJsonPath('data.code', 'color')
        ->assertJsonPath('data.type', 'enum');

    expect($response->json('data.options'))->toHaveCount(2);
    expect($response->json('permissions'))->toHaveKeys(['resource', 'fields', 'actions']);
});

it('show: 403 without attributes.view', function () {
    $actor = attributeUserWith([]);
    $target = Attribute::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/attributes/{$target->id}")->assertForbidden();
});

it('show: 404 for a non-existent attribute', function () {
    $actor = attributeUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/attributes/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// create — POST /api/attributes (AC-003)
// ---------------------------------------------------------------------------

it('create: 201 + persists a text attribute, ignoring any submitted options', function () {
    $actor = attributeUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/attributes', [
        'code' => 'material', 'name' => 'Material', 'type' => 'text',
        'options' => [['value' => 'ignored', 'label' => 'Ignored']],
    ])->assertCreated()->assertJsonPath('data.code', 'material');

    $this->assertDatabaseHas('attributes', ['code' => 'material', 'type' => 'text']);
    expect(Attribute::where('code', 'material')->first()->options)->toBeEmpty();
});

it('create: config is persisted and returned', function () {
    $actor = attributeUserWith(['create']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/attributes', [
        'code' => 'sla_hours', 'name' => 'SLA hours', 'type' => 'decimal',
        'config' => ['min' => 0, 'decimals' => 2],
    ])->assertCreated();

    expect($response->json('data.config'))->toBe(['min' => 0, 'decimals' => 2]);
    expect(Attribute::where('code', 'sla_hours')->first()->config)->toBe(['min' => 0, 'decimals' => 2]);
});

it('create: enum without options → 422', function () {
    $actor = attributeUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/attributes', ['code' => 'color', 'name' => 'Color', 'type' => 'enum'])
        ->assertStatus(422)->assertJsonValidationErrors('options');
});

it('create: enum with valid options → 201, options persisted with color/icon/is_default', function () {
    $actor = attributeUserWith(['create']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/attributes', [
        'code' => 'color', 'name' => 'Color', 'type' => 'enum',
        'options' => [
            ['value' => 'red', 'label' => 'Red', 'color' => 'red', 'icon' => 'circle', 'is_default' => true],
            ['value' => 'blue', 'label' => 'Blue'],
        ],
    ])->assertCreated();

    expect($response->json('data.options'))->toHaveCount(2);
    $this->assertDatabaseHas('attribute_options', [
        'value' => 'red', 'color' => 'red', 'icon' => 'circle', 'is_default' => true,
    ]);
    $this->assertDatabaseHas('attribute_options', ['value' => 'blue', 'is_default' => false]);
});

it('create: enum with duplicate option values → 422', function () {
    $actor = attributeUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/attributes', [
        'code' => 'color', 'name' => 'Color', 'type' => 'enum',
        'options' => [
            ['value' => 'red', 'label' => 'Red'],
            ['value' => 'red', 'label' => 'Red again'],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('options');
});

it('create: relation without a valid relation_target → 422', function () {
    $actor = attributeUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/attributes', ['code' => 'referent', 'name' => 'Referent', 'type' => 'relation'])
        ->assertStatus(422)->assertJsonValidationErrors('relation_target');

    $this->postJson('/api/attributes', [
        'code' => 'referent', 'name' => 'Referent', 'type' => 'relation',
        'relation_target' => ['entity_type' => 'not-a-domain', 'cardinality' => 'one', 'for_select_resource' => 'referents'],
    ])->assertStatus(422)->assertJsonValidationErrors('relation_target.entity_type');
});

it('create: relation with a valid relation_target → 201', function () {
    $actor = attributeUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/attributes', [
        'code' => 'referent', 'name' => 'Referent', 'type' => 'relation',
        'relation_target' => ['entity_type' => 'referents', 'cardinality' => 'one', 'for_select_resource' => 'referents'],
    ])->assertCreated()->assertJsonPath('data.relation_target.entity_type', 'referents');
});

it('create: 403 without attributes.create', function () {
    $actor = attributeUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/attributes', ['code' => 'x', 'name' => 'X', 'type' => 'text'])->assertForbidden();
});

it('create: 422 on duplicate code / invalid type', function () {
    $actor = attributeUserWith(['create']);
    Attribute::factory()->create(['code' => 'existing']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/attributes', ['code' => 'existing', 'name' => 'Dup', 'type' => 'text'])
        ->assertStatus(422)->assertJsonValidationErrors('code');

    $this->postJson('/api/attributes', ['code' => 'new_code', 'name' => 'X', 'type' => 'not_a_type'])
        ->assertStatus(422)->assertJsonValidationErrors('type');
});

// ---------------------------------------------------------------------------
// update — PUT/PATCH /api/attributes/{attribute} (AC-004)
// ---------------------------------------------------------------------------

it('update: options is a full-replace', function () {
    $actor = attributeUserWith(['update']);
    $attribute = Attribute::factory()->enum(2)->create();
    $originalOptionIds = $attribute->options->pluck('id');
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/attributes/{$attribute->id}", [
        'options' => [['value' => 'green', 'label' => 'Green']],
    ])->assertOk();

    expect($response->json('data.options'))->toHaveCount(1)
        ->and($response->json('data.options.0.value'))->toBe('green');
    expect(AttributeOption::whereIn('id', $originalOptionIds)->count())->toBe(0);
});

it('update: 403 without attributes.update', function () {
    $actor = attributeUserWith([]);
    $target = Attribute::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/attributes/{$target->id}", ['name' => 'Nope'])->assertForbidden();
});

it('update: type not a registered FieldTypeRegistry key → 422', function () {
    $actor = attributeUserWith(['update']);
    $target = Attribute::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/attributes/{$target->id}", ['type' => 'not_a_type'])
        ->assertStatus(422)->assertJsonValidationErrors('type');
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/attributes/{attribute} (AC-005)
// ---------------------------------------------------------------------------

it('delete: 204 when the attribute is unused', function () {
    $actor = attributeUserWith(['delete']);
    $target = Attribute::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/attributes/{$target->id}")->assertNoContent();
    $this->assertDatabaseMissing('attributes', ['id' => $target->id]);
});

it('delete: 409 when assigned to a category', function () {
    $actor = attributeUserWith(['delete']);
    $target = Attribute::factory()->create();
    $category = ProductCategory::factory()->create();
    $category->attributes()->attach($target->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/attributes/{$target->id}")->assertStatus(409);
    $this->assertDatabaseHas('attributes', ['id' => $target->id]);
});

it('delete: 403 without attributes.delete', function () {
    $actor = attributeUserWith([]);
    $target = Attribute::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/attributes/{$target->id}")->assertForbidden();
});
