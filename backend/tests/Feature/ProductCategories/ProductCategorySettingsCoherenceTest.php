<?php

use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * `multi_quote_work_order` requires `generates_contract` on a root (user
 * decision 2026-10-15, spec 0267): refused with a 422, nothing written.
 */
uses(RefreshDatabase::class);

const MULTI_REQUIRES_CONTRACT_IT = 'Per attivare Commessa multi-offerta il ramo deve prevedere il contratto: attiva prima Prevede un contratto.';
const CONTRACT_BLOCKED_BY_MULTI_IT = 'Non puoi togliere Prevede un contratto mentre Commessa multi-offerta è attiva: spegni prima Commessa multi-offerta.';

beforeEach(function (): void {
    foreach (['product-categories.create', 'product-categories.update'] as $permission) {
        Permission::findOrCreate($permission);
    }

    $user = User::factory()->create();
    $user->givePermissionTo(['product-categories.create', 'product-categories.update']);
    Sanctum::actingAs($user);
});

it('refuses turning multi-offer on while the root has no contract, and saves nothing', function (): void {
    $root = ProductCategory::factory()->create(['generates_contract' => false, 'multi_quote_work_order' => false]);

    $this->withHeader('Accept-Language', 'it')
        ->putJson("/api/product-categories/{$root->id}", ['multi_quote_work_order' => true])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['multi_quote_work_order' => MULTI_REQUIRES_CONTRACT_IT]);

    expect($root->fresh()->multi_quote_work_order)->toBeFalse();
});

it('refuses turning the contract off while multi-offer stays on', function (): void {
    $root = ProductCategory::factory()->create(['generates_contract' => true, 'multi_quote_work_order' => true]);

    $this->withHeader('Accept-Language', 'it')
        ->putJson("/api/product-categories/{$root->id}", ['generates_contract' => false])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['generates_contract' => CONTRACT_BLOCKED_BY_MULTI_IT]);

    expect($root->fresh()->generates_contract)->toBeTrue();
});

it('answers in English when asked to', function (): void {
    $root = ProductCategory::factory()->create(['generates_contract' => false, 'multi_quote_work_order' => false]);

    $this->withHeader('Accept-Language', 'en')
        ->putJson("/api/product-categories/{$root->id}", ['multi_quote_work_order' => true])
        ->assertJsonValidationErrors(['multi_quote_work_order' => 'To turn on Multi-offer work order the branch must include a contract: turn on Includes a contract first.']);
});

it('accepts turning both on in one request', function (): void {
    $root = ProductCategory::factory()->create(['generates_contract' => false, 'multi_quote_work_order' => false]);

    $this->putJson("/api/product-categories/{$root->id}", ['generates_contract' => true, 'multi_quote_work_order' => true])
        ->assertOk();

    expect($root->fresh()->only('generates_contract', 'multi_quote_work_order'))->toBe(['generates_contract' => true, 'multi_quote_work_order' => true]);
});

it('accepts turning both off in one request', function (): void {
    $root = ProductCategory::factory()->create(['generates_contract' => true, 'multi_quote_work_order' => true]);

    $this->putJson("/api/product-categories/{$root->id}", ['generates_contract' => false, 'multi_quote_work_order' => false])
        ->assertOk();

    expect($root->fresh()->only('generates_contract', 'multi_quote_work_order'))->toBe(['generates_contract' => false, 'multi_quote_work_order' => false]);
});

it('refuses creating a root with multi-offer on and no contract', function (): void {
    $this->withHeader('Accept-Language', 'it')
        ->postJson('/api/product-categories', ['name' => 'Incoerente', 'multi_quote_work_order' => true, 'generates_contract' => false])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['multi_quote_work_order' => MULTI_REQUIRES_CONTRACT_IT]);

    expect(ProductCategory::query()->where('name', 'Incoerente')->exists())->toBeFalse();
});

it('creates a root with multi-offer on and the contract omitted (defaults to true)', function (): void {
    $this->postJson('/api/product-categories', ['name' => 'Coerente', 'multi_quote_work_order' => true])
        ->assertCreated()
        ->assertJsonPath('data.generates_contract', true)
        ->assertJsonPath('data.multi_quote_work_order', true);
});
