<?php

use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('paymentMethodUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function paymentMethodUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("payment-methods.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("payment-methods.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-030 — 403 without the matching permission, on every endpoint
// ---------------------------------------------------------------------------

it('GET show: 403 without payment-methods.view (AC-030)', function () {
    $actor = paymentMethodUserWith([]);
    $target = PaymentMethod::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/payment-methods/{$target->id}")->assertForbidden();
});

it('POST store: 403 without payment-methods.create, no row created (AC-030)', function () {
    $actor = paymentMethodUserWith([]);
    Sanctum::actingAs($actor);

    $countBefore = PaymentMethod::count();

    $this->postJson('/api/payment-methods', ['name' => 'Nope', 'code' => 'nope'])->assertForbidden();

    expect(PaymentMethod::count())->toBe($countBefore);
});

it('PATCH update: 403 without payment-methods.update, no change persisted (AC-030)', function () {
    $actor = paymentMethodUserWith([]);
    $target = PaymentMethod::factory()->create(['description' => 'Untouched']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/payment-methods/{$target->id}", ['description' => 'Nope'])->assertForbidden();

    $this->assertDatabaseHas('payment_methods', ['id' => $target->id, 'description' => 'Untouched']);
});

it('DELETE destroy: 403 without payment-methods.delete, record still exists (AC-030)', function () {
    $actor = paymentMethodUserWith([]);
    $target = PaymentMethod::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/payment-methods/{$target->id}")->assertForbidden();

    $this->assertDatabaseHas('payment_methods', ['id' => $target->id]);
});

it('GET for-select: 200 without payment-methods.viewAny (AC-030, ADR 0011 amended)', function () {
    $actor = paymentMethodUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/payment-methods/for-select')->assertOk();
});

it('POST reorder: 403 without payment-methods.update (AC-030)', function () {
    $actor = paymentMethodUserWith([]);
    $custom = PaymentMethod::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/payment-methods/reorder', ['ordered_ids' => [$custom->id]])->assertForbidden();
});

it('GET tables columns: 403 without payment-methods.viewAny (AC-030)', function () {
    $actor = paymentMethodUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/tables/payment-methods/columns')->assertForbidden();
});

it('POST export: 403 without payment-methods.export (AC-030)', function () {
    $actor = paymentMethodUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/exports/payment-methods', [
        'format' => 'csv',
        'columns' => [['colId' => 'name', 'header' => 'Name']],
    ])->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-031 — permissions:sync creates the 8 standard permissions
// ---------------------------------------------------------------------------

it('permissions:sync creates all 8 payment-methods.* permissions and no more (AC-031)', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
        expect(Permission::where('name', "payment-methods.{$ability}")->exists())->toBeTrue();
    }

    expect(Permission::where('name', 'like', 'payment-methods.%')->count())->toBe(8);
});

// ---------------------------------------------------------------------------
// AC-032 — navigation node gated by payment-methods.view
// ---------------------------------------------------------------------------

it('navigation: the payment-methods node only shows with payment-methods.view (AC-032)', function () {
    Permission::findOrCreate('payment-methods.view');

    $withoutView = User::factory()->create();
    Sanctum::actingAs($withoutView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->not->toContain('payment-methods');

    $withView = User::factory()->create();
    $withView->givePermissionTo('payment-methods.view');
    Sanctum::actingAs($withView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->toContain('payment-methods');
});

// ---------------------------------------------------------------------------
// AC-033 — 403 precedence over a field-level 422
// ---------------------------------------------------------------------------

it('a 403 (no base write ability) takes precedence over a field-level 422 (AC-033)', function () {
    $actor = paymentMethodUserWith([]);
    $target = PaymentMethod::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/payment-methods/{$target->id}", ['description' => 'blocked'])->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-034 — role_field_permissions restricting `description`
// ---------------------------------------------------------------------------

it('update: a DB row denying `description` 422s a change but 200s a no-touch PATCH (AC-034)', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("payment-methods.{$ability}");
    }

    $role = Role::create(['name' => 'payment-method-description-locked']);
    $role->givePermissionTo(['payment-methods.view', 'payment-methods.update']);
    $role->fieldPermissions()->create([
        'resource' => 'payment-methods',
        'field' => 'description',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = PaymentMethod::factory()->create(['name' => 'Original', 'description' => 'Kept']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/payment-methods/{$target->id}", ['description' => 'Changed'])
        ->assertStatus(422)->assertJsonValidationErrors('description');

    $this->patchJson("/api/payment-methods/{$target->id}", ['name' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed');
});

// ---------------------------------------------------------------------------
// AC-035 — mandatory fields (name/code) bypass a restrictive DB matrix
// ---------------------------------------------------------------------------

it('create: a restrictive DB row on `name` is ignored (mandatory bypass), write succeeds (AC-035)', function () {
    foreach (['viewAny', 'view', 'create'] as $ability) {
        Permission::findOrCreate("payment-methods.{$ability}");
    }

    $role = Role::create(['name' => 'payment-method-name-locked']);
    $role->givePermissionTo(['payment-methods.view', 'payment-methods.create']);
    $role->fieldPermissions()->create([
        'resource' => 'payment-methods',
        'field' => 'name',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);
    Sanctum::actingAs($actor);

    $this->postJson('/api/payment-methods', ['name' => 'Bypassed', 'code' => 'bypassed'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Bypassed');
});

it('create: a restrictive DB row on `code` is ignored (mandatory bypass), write succeeds (AC-035)', function () {
    foreach (['viewAny', 'view', 'create'] as $ability) {
        Permission::findOrCreate("payment-methods.{$ability}");
    }

    $role = Role::create(['name' => 'payment-method-code-locked']);
    $role->givePermissionTo(['payment-methods.view', 'payment-methods.create']);
    $role->fieldPermissions()->create([
        'resource' => 'payment-methods',
        'field' => 'code',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);
    Sanctum::actingAs($actor);

    $this->postJson('/api/payment-methods', ['name' => 'Bypassed Code', 'code' => 'bypassed_code'])
        ->assertCreated()
        ->assertJsonPath('data.code', 'bypassed_code');
});
