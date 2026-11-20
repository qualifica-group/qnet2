<?php

use App\Jobs\PromoteCustomFieldIndexJob;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldOption;
use App\Models\CustomFieldValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// spec 0021 — T9: admin CRUD for custom field DEFINITIONS. AC-018 (create
// validation), AC-019 (update: options full-replace, immutability, is_indexed
// promotion hook).
uses(RefreshDatabase::class);

if (! function_exists('customFieldUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function customFieldUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("custom-fields.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("custom-fields.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// show — GET /api/custom-fields/{customField}
// ---------------------------------------------------------------------------

it('show: 200 with the full data shape including options', function () {
    $actor = customFieldUserWith(['view']);
    $definition = CustomFieldDefinition::factory()->forEntity('companies')->ofType('enum')->create(['key' => 'segment']);
    $definition->options()->create(['value' => 'retail', 'label' => 'Retail', 'sort_order' => 0]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/custom-fields/{$definition->id}")
        ->assertOk()
        ->assertJsonPath('data.entity_type', 'companies')
        ->assertJsonPath('data.key', 'segment')
        ->assertJsonPath('data.type', 'enum');

    expect($response->json('data.options'))->toHaveCount(1);
    expect($response->json('permissions'))->toHaveKeys(['resource', 'fields', 'actions']);
});

it('show: 403 without custom-fields.view', function () {
    $actor = customFieldUserWith([]);
    $definition = CustomFieldDefinition::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/custom-fields/{$definition->id}")->assertForbidden();
});

it('show: 404 for a non-existent definition', function () {
    $actor = customFieldUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/custom-fields/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// create — POST /api/custom-fields (AC-018)
// ---------------------------------------------------------------------------

it('create: 201 + persists a text field on a custom-fieldable entity', function () {
    $actor = customFieldUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/custom-fields', [
        'entity_type' => 'companies',
        'key' => 'notes',
        'type' => 'text',
        'label' => 'Notes',
    ])->assertCreated()->assertJsonPath('data.key', 'notes');

    $this->assertDatabaseHas('custom_field_definitions', ['entity_type' => 'companies', 'key' => 'notes']);
});

it('create: 403 without custom-fields.create', function () {
    $actor = customFieldUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/custom-fields', [
        'entity_type' => 'companies', 'key' => 'x', 'type' => 'text', 'label' => 'X',
    ])->assertForbidden();
});

it('create: entity_type not custom-fieldable → 422', function () {
    $actor = customFieldUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/custom-fields', [
        'entity_type' => 'not-a-domain', 'key' => 'x', 'type' => 'text', 'label' => 'X',
    ])->assertStatus(422)->assertJsonValidationErrors('entity_type');
});

it('create: key duplicated per entity_type → 422', function () {
    $actor = customFieldUserWith(['create']);
    CustomFieldDefinition::factory()->forEntity('companies')->create(['key' => 'notes']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/custom-fields', [
        'entity_type' => 'companies', 'key' => 'notes', 'type' => 'text', 'label' => 'Notes again',
    ])->assertStatus(422)->assertJsonValidationErrors('key');
});

it('create: same key on a DIFFERENT entity_type is allowed', function () {
    $actor = customFieldUserWith(['create']);
    CustomFieldDefinition::factory()->forEntity('companies')->create(['key' => 'notes']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/custom-fields', [
        'entity_type' => 'products', 'key' => 'notes', 'type' => 'text', 'label' => 'Notes',
    ])->assertCreated();
});

it('create: type=enum without options → 422', function () {
    $actor = customFieldUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/custom-fields', [
        'entity_type' => 'companies', 'key' => 'segment', 'type' => 'enum', 'label' => 'Segment',
    ])->assertStatus(422)->assertJsonValidationErrors('options');
});

it('create: type=enum with valid options → 201, options persisted', function () {
    $actor = customFieldUserWith(['create']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/custom-fields', [
        'entity_type' => 'companies', 'key' => 'segment', 'type' => 'enum', 'label' => 'Segment',
        'options' => [
            ['value' => 'retail', 'label' => 'Retail'],
            ['value' => 'wholesale', 'label' => 'Wholesale'],
        ],
    ])->assertCreated();

    expect($response->json('data.options'))->toHaveCount(2);
    $this->assertDatabaseHas('custom_field_options', ['value' => 'retail']);
});

it('create: type=enum with duplicate option values → 422', function () {
    $actor = customFieldUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/custom-fields', [
        'entity_type' => 'companies', 'key' => 'segment', 'type' => 'enum', 'label' => 'Segment',
        'options' => [
            ['value' => 'retail', 'label' => 'Retail'],
            ['value' => 'retail', 'label' => 'Retail again'],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('options');
});

it('create: type=relation without a valid relation_target → 422', function () {
    $actor = customFieldUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/custom-fields', [
        'entity_type' => 'companies', 'key' => 'referent', 'type' => 'relation', 'label' => 'Referent',
    ])->assertStatus(422)->assertJsonValidationErrors('relation_target');

    $this->postJson('/api/custom-fields', [
        'entity_type' => 'companies', 'key' => 'referent', 'type' => 'relation', 'label' => 'Referent',
        'relation_target' => ['entity_type' => 'not-a-domain', 'cardinality' => 'one', 'for_select_resource' => 'referents'],
    ])->assertStatus(422)->assertJsonValidationErrors('relation_target.entity_type');

    $this->postJson('/api/custom-fields', [
        'entity_type' => 'companies', 'key' => 'referent', 'type' => 'relation', 'label' => 'Referent',
        'relation_target' => ['entity_type' => 'referents', 'cardinality' => 'invalid', 'for_select_resource' => 'referents'],
    ])->assertStatus(422)->assertJsonValidationErrors('relation_target.cardinality');
});

it('create: type=relation with a valid relation_target → 201', function () {
    $actor = customFieldUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/custom-fields', [
        'entity_type' => 'companies', 'key' => 'referent', 'type' => 'relation', 'label' => 'Referent',
        'relation_target' => ['entity_type' => 'referents', 'cardinality' => 'one', 'for_select_resource' => 'referents'],
    ])->assertCreated()->assertJsonPath('data.relation_target.entity_type', 'referents');
});

// ---------------------------------------------------------------------------
// update — PUT/PATCH /api/custom-fields/{customField} (AC-019)
// ---------------------------------------------------------------------------

it('update: options is a full-replace', function () {
    $actor = customFieldUserWith(['update']);
    $definition = CustomFieldDefinition::factory()->forEntity('companies')->ofType('enum')->create();
    $definition->options()->create(['value' => 'a', 'label' => 'A']);
    $definition->options()->create(['value' => 'b', 'label' => 'B']);
    $originalOptionIds = $definition->options->pluck('id');
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/custom-fields/{$definition->id}", [
        'options' => [['value' => 'c', 'label' => 'C']],
    ])->assertOk();

    expect($response->json('data.options'))->toHaveCount(1)
        ->and($response->json('data.options.0.value'))->toBe('c');
    expect(CustomFieldOption::whereIn('id', $originalOptionIds)->count())->toBe(0);
});

it('update: 403 without custom-fields.update', function () {
    $actor = customFieldUserWith([]);
    $definition = CustomFieldDefinition::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/custom-fields/{$definition->id}", ['label' => 'Nope'])->assertForbidden();
});

it('update: changing key when the definition already has recorded values → 422', function () {
    $actor = customFieldUserWith(['update']);
    $definition = CustomFieldDefinition::factory()->forEntity('companies')->create(['key' => 'notes']);
    CustomFieldValue::factory()->create(['entity_type' => 'companies', 'entity_id' => 1, 'values' => ['notes' => 'hello']]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/custom-fields/{$definition->id}", ['key' => 'renamed'])->assertStatus(422);
    expect($definition->fresh()->key)->toBe('notes');
});

it('update: changing type when the definition already has recorded values → 422', function () {
    $actor = customFieldUserWith(['update']);
    $definition = CustomFieldDefinition::factory()->forEntity('companies')->ofType('text')->create(['key' => 'notes']);
    CustomFieldValue::factory()->create(['entity_type' => 'companies', 'entity_id' => 1, 'values' => ['notes' => 'hello']]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/custom-fields/{$definition->id}", ['type' => 'integer'])->assertStatus(422);
    expect($definition->fresh()->type)->toBe('text');
});

it('update: changing entity_type when the definition already has recorded values → 422', function () {
    $actor = customFieldUserWith(['update']);
    $definition = CustomFieldDefinition::factory()->forEntity('companies')->create(['key' => 'notes']);
    CustomFieldValue::factory()->create(['entity_type' => 'companies', 'entity_id' => 1, 'values' => ['notes' => 'hello']]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/custom-fields/{$definition->id}", ['entity_type' => 'products'])->assertStatus(422);
    expect($definition->fresh()->entity_type)->toBe('companies');
});

it('update: key/type/entity_type may change freely when no values are recorded yet', function () {
    $actor = customFieldUserWith(['update']);
    $definition = CustomFieldDefinition::factory()->forEntity('companies')->ofType('text')->create(['key' => 'notes']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/custom-fields/{$definition->id}", [
        'key' => 'renamed', 'type' => 'integer', 'entity_type' => 'products',
    ])->assertOk();

    $definition->refresh();
    expect($definition->key)->toBe('renamed')
        ->and($definition->type)->toBe('integer')
        ->and($definition->entity_type)->toBe('products');
});

it('update: is_indexed false→true dispatches PromoteCustomFieldIndexJob to the queue (T15)', function () {
    Queue::fake();
    $actor = customFieldUserWith(['update']);
    $definition = CustomFieldDefinition::factory()->create(['is_indexed' => false]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/custom-fields/{$definition->id}", ['is_indexed' => true])
        ->assertOk()
        ->assertJsonPath('data.is_indexed', true);

    expect($definition->fresh()->is_indexed)->toBeTrue();
    Queue::assertPushed(PromoteCustomFieldIndexJob::class, fn (PromoteCustomFieldIndexJob $job): bool => $job->definitionId() === $definition->id);
});

it('update: is_indexed already true does NOT re-dispatch on an unrelated change', function () {
    Queue::fake();
    $actor = customFieldUserWith(['update']);
    $definition = CustomFieldDefinition::factory()->create(['is_indexed' => true, 'label' => 'Old label']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/custom-fields/{$definition->id}", ['label' => 'New label'])->assertOk();

    Queue::assertNotPushed(PromoteCustomFieldIndexJob::class);
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/custom-fields/{customField}
// ---------------------------------------------------------------------------

// REQUIREMENT CHANGED (spec 0384 D-6, supersedes the purge of spec 0021): a field with recorded values is refused (409) and its values stay.
it('delete: 409 when a record holds a value for that key, the values stay', function () {
    $actor = customFieldUserWith(['delete']);
    $definition = CustomFieldDefinition::factory()->forEntity('companies')->create(['key' => 'notes']);
    CustomFieldValue::factory()->create([
        'entity_type' => 'companies', 'entity_id' => 1, 'values' => ['notes' => 'hello', 'other' => 'kept'],
    ]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/custom-fields/{$definition->id}")->assertStatus(409)->assertJsonPath('success', false);

    $this->assertDatabaseHas('custom_field_definitions', ['id' => $definition->id]);
    $row = CustomFieldValue::where('entity_type', 'companies')->where('entity_id', 1)->first();
    expect($row->values)->toBe(['notes' => 'hello', 'other' => 'kept']);
});

it('delete: 204 when no record holds a value for that key', function () {
    $actor = customFieldUserWith(['delete']);
    $definition = CustomFieldDefinition::factory()->forEntity('companies')->create(['key' => 'notes']);
    CustomFieldValue::factory()->create(['entity_type' => 'companies', 'entity_id' => 1, 'values' => ['other' => 'kept']]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/custom-fields/{$definition->id}")->assertNoContent();

    $this->assertDatabaseMissing('custom_field_definitions', ['id' => $definition->id]);
});

it('delete: 403 without custom-fields.delete', function () {
    $actor = customFieldUserWith([]);
    $definition = CustomFieldDefinition::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/custom-fields/{$definition->id}")->assertForbidden();
});
