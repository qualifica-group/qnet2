<?php

use App\Models\CostType;
use App\Models\PurchaseOrderLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('AC-016: the migration seeds Collaboratori, Fitti, Utenze and Altro, active and ordered', function () {
    expect(CostType::query()->orderBy('sort_order')->pluck('name')->all())
        ->toBe(['Collaboratori', 'Fitti', 'Utenze', 'Altro'])
        ->and(CostType::query()->where('is_active', false)->count())->toBe(0);
});

it('AC-016: create persists the cost type and returns the abilities', function () {
    Sanctum::actingAs(costTypeUserWith(['create', 'update']));

    $this->postJson('/api/cost-types', ['name' => 'Trasferte', 'description' => 'Viaggi', 'nature' => 'indirect', 'color' => 'teal'])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Trasferte')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.abilities.update', true)
        ->assertJsonPath('data.abilities.delete', false);

    $this->assertDatabaseHas('cost_types', ['name' => 'Trasferte', 'nature' => 'indirect', 'color' => 'teal', 'is_active' => true]);
});

it('AC-016: a duplicated name is a 422, on create and on update', function () {
    Sanctum::actingAs(costTypeUserWith(['create', 'update']));
    $other = CostType::factory()->create(['name' => 'Licenze']);

    $this->postJson('/api/cost-types', ['name' => 'Fitti', 'nature' => 'direct', 'color' => 'slate'])->assertStatus(422)->assertJsonValidationErrors('name');
    $this->putJson("/api/cost-types/{$other->id}", ['name' => 'Fitti'])->assertStatus(422)->assertJsonValidationErrors('name');
    $this->putJson("/api/cost-types/{$other->id}", ['name' => 'Licenze', 'color' => 'blue'])->assertOk();
});

it('AC-016: update changes only the submitted fields', function () {
    Sanctum::actingAs(costTypeUserWith(['update']));
    $costType = CostType::factory()->create(['description' => 'Old', 'is_active' => true]);

    $this->putJson("/api/cost-types/{$costType->id}", ['is_active' => false, 'description' => null])
        ->assertOk()
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.description', null)
        ->assertJsonPath('data.name', $costType->name);
});

it('AC-016: show returns the record, 404 when missing', function () {
    Sanctum::actingAs(costTypeUserWith(['view']));
    $costType = CostType::factory()->create();

    $this->getJson("/api/cost-types/{$costType->id}")->assertOk()->assertJsonPath('data.id', $costType->id);
    $this->getJson('/api/cost-types/999999')->assertNotFound();
});

it('AC-016: delete removes an unused cost type', function () {
    Sanctum::actingAs(costTypeUserWith(['delete']));
    $costType = CostType::factory()->create();

    $this->deleteJson("/api/cost-types/{$costType->id}")->assertNoContent();

    $this->assertDatabaseMissing('cost_types', ['id' => $costType->id]);
});

it('AC-016: delete of a cost type used by a purchase order line is a 409', function () {
    Sanctum::actingAs(costTypeUserWith(['delete']));
    $line = PurchaseOrderLine::factory()->create();

    $this->deleteJson("/api/cost-types/{$line->cost_type_id}")->assertStatus(409);

    $this->assertDatabaseHas('cost_types', ['id' => $line->cost_type_id]);
});

it('AC-016: every write endpoint needs its permission (403)', function () {
    Sanctum::actingAs(costTypeUserWith(['view']));
    $costType = CostType::factory()->create();

    $this->postJson('/api/cost-types', ['name' => 'Nuovo', 'nature' => 'direct', 'color' => 'slate'])->assertForbidden();
    $this->putJson("/api/cost-types/{$costType->id}", ['name' => 'Nuovo'])->assertForbidden();
    $this->deleteJson("/api/cost-types/{$costType->id}")->assertForbidden();

    Sanctum::actingAs(costTypeUserWith([]));
    $this->getJson("/api/cost-types/{$costType->id}")->assertForbidden();
});

it('AC-016: the endpoints require authentication', function () {
    $this->getJson('/api/cost-types/for-select')->assertUnauthorized();
    $this->getJson('/api/cost-types/1')->assertUnauthorized();
});

it('AC-016: for-select lists only active types ordered by sort_order then name', function () {
    Sanctum::actingAs(costTypeUserWith([]));
    CostType::query()->delete();
    CostType::factory()->create(['name' => 'Zeta', 'sort_order' => 1]);
    CostType::factory()->create(['name' => 'Beta', 'sort_order' => 2]);
    CostType::factory()->create(['name' => 'Alfa', 'sort_order' => 2]);
    $inactive = CostType::factory()->inactive()->create(['name' => 'Spenta', 'sort_order' => 0]);

    $this->getJson('/api/cost-types/for-select')
        ->assertOk()
        ->assertJsonPath('items.0.label', 'Zeta')
        ->assertJsonPath('items.1.label', 'Alfa')
        ->assertJsonPath('items.2.label', 'Beta')
        ->assertJsonCount(3, 'items');

    $this->getJson('/api/cost-types/for-select?search=alf')->assertOk()->assertJsonCount(1, 'items');

    // edit-mode hydration keeps an inactive type already in use
    $this->getJson('/api/cost-types/for-select?ids[]='.$inactive->id)->assertOk()->assertJsonCount(4, 'items');
});
