<?php

use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * Spec 0414: the root-owned `request_offer_hidden` setting of a product
 * category (AC-001 root write and sync, AC-002 child guard, AC-003 reparent).
 */
uses(RefreshDatabase::class);

it('AC-001: a fresh root shows the offer; a root may hide it', function () {
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', ['name' => 'Formazione'])
        ->assertCreated()
        ->assertJsonPath('data.request_offer_hidden', false);

    $this->postJson('/api/product-categories', ['name' => 'Consulenza', 'request_offer_hidden' => true])
        ->assertCreated()
        ->assertJsonPath('data.request_offer_hidden', true);
});

it('AC-001: changing the root value realigns the whole branch', function () {
    $root = ProductCategory::factory()->create();
    $child = ProductCategory::factory()->childOf($root)->create();
    $grandchild = ProductCategory::factory()->childOf($child)->create();
    Sanctum::actingAs(productCategoryUserWith(['update']));

    $this->patchJson("/api/product-categories/{$root->id}", ['request_offer_hidden' => true])
        ->assertOk()
        ->assertJsonPath('data.request_offer_hidden', true);

    expect($child->fresh()->request_offer_hidden)->toBeTrue()
        ->and($grandchild->fresh()->request_offer_hidden)->toBeTrue();
});

it('AC-001: detail and tree expose the effective value', function () {
    $root = ProductCategory::factory()->create(['request_offer_hidden' => true]);
    $child = ProductCategory::factory()->childOf($root)->create(['request_offer_hidden' => true]);
    Sanctum::actingAs(productCategoryUserWith(['view', 'viewAny']));

    $this->getJson("/api/product-categories/{$child->id}")
        ->assertOk()
        ->assertJsonPath('data.request_offer_hidden', true)
        ->assertJsonPath('data.request_offer_hidden_source_category.id', $root->id);

    $this->getJson('/api/product-categories/tree')
        ->assertOk()
        ->assertJsonPath('data.0.request_offer_hidden', true)
        ->assertJsonPath('data.0.children.0.request_offer_hidden', true);
});

it('AC-002: a child inherits the value of its root when none is submitted', function () {
    $root = ProductCategory::factory()->create(['request_offer_hidden' => true]);
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', ['name' => 'Figlio', 'parent_id' => $root->id])
        ->assertCreated()
        ->assertJsonPath('data.request_offer_hidden', true);
});

it('AC-002: a child may not define a value different from its root', function () {
    $root = ProductCategory::factory()->create(['request_offer_hidden' => true]);
    $child = ProductCategory::factory()->childOf($root)->create(['request_offer_hidden' => true]);
    Sanctum::actingAs(productCategoryUserWith(['create', 'update']));

    $this->postJson('/api/product-categories', ['name' => 'X', 'parent_id' => $root->id, 'request_offer_hidden' => false])
        ->assertStatus(422)
        ->assertJsonPath('message', 'This category inherits the request offer visibility from its root category and cannot define its own.');
    $this->patchJson("/api/product-categories/{$child->id}", ['request_offer_hidden' => false])->assertStatus(422);
    $this->patchJson("/api/product-categories/{$child->id}", ['request_offer_hidden' => true])->assertOk();
});

it('rejects a non boolean value', function () {
    Sanctum::actingAs(productCategoryUserWith(['create']));

    $this->postJson('/api/product-categories', ['name' => 'Bad', 'request_offer_hidden' => 'maybe'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('request_offer_hidden');
});

it('AC-003: moving a category under another root takes that root value', function () {
    $visible = ProductCategory::factory()->create(['request_offer_hidden' => false]);
    $hidden = ProductCategory::factory()->create(['request_offer_hidden' => true]);
    $child = ProductCategory::factory()->childOf($visible)->create(['request_offer_hidden' => false]);
    Sanctum::actingAs(productCategoryUserWith(['update']));

    $this->patchJson("/api/product-categories/{$child->id}", ['parent_id' => $hidden->id])->assertOk();

    expect($child->fresh()->request_offer_hidden)->toBeTrue();
});

it('a user without the permission cannot change the value', function () {
    $root = ProductCategory::factory()->create();
    Sanctum::actingAs(productCategoryUserWith([]));

    $this->patchJson("/api/product-categories/{$root->id}", ['request_offer_hidden' => true])->assertForbidden();
});
