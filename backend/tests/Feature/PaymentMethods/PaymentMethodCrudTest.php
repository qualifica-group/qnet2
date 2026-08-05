<?php

use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleAssignmentGuard;
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
// create — POST /api/payment-methods (AC-010..017)
// ---------------------------------------------------------------------------

it('create: 201 + persists all fields, server-assigned sort_order (AC-010)', function () {
    $actor = paymentMethodUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/payment-methods', [
        'name' => 'Bonifico bancario', 'code' => 'bank_transfer', 'description' => 'Bonifico ordinario',
        'payment_instructions' => 'IBAN in fattura', 'payment_days' => 30, 'is_active' => true,
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Bonifico bancario')
        ->assertJsonPath('data.code', 'bank_transfer')
        ->assertJsonPath('data.description', 'Bonifico ordinario')
        ->assertJsonPath('data.payment_instructions', 'IBAN in fattura')
        ->assertJsonPath('data.payment_days', 30)
        ->assertJsonPath('data.is_active', true)
        ->assertJsonStructure(['data' => ['id', 'name', 'code', 'description', 'payment_instructions', 'payment_days', 'sort_order', 'is_active', 'created_at', 'updated_at'], 'permissions']);

    $this->assertDatabaseHas('payment_methods', ['name' => 'Bonifico bancario', 'code' => 'bank_transfer']);
});

it('create: 201 with only name+code, the rest defaults (AC-011)', function () {
    $actor = paymentMethodUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/payment-methods', ['name' => 'Minimal', 'code' => 'minimal'])
        ->assertCreated()
        ->assertJsonPath('data.description', null)
        ->assertJsonPath('data.payment_instructions', null)
        ->assertJsonPath('data.payment_days', null)
        ->assertJsonPath('data.is_active', true);
});

it('create: a submitted sort_order is ignored, the server sequence wins (AC-012)', function () {
    $actor = paymentMethodUserWith(['create']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/payment-methods', ['name' => 'Ignored Sort', 'code' => 'ignored_sort', 'sort_order' => 999])
        ->assertCreated();

    expect($response->json('data.sort_order'))->not->toBe(999);
});

// Requirement changed (user directive 2026-08-05): `code` is the ONLY unique
// field. AC-013 read "422 when name already exists"; a homonymous method is
// now legal — the legacy catalogues carry same-named modalities with different
// terms — so the assertion is inverted to lock the new rule in.
it('create: a duplicate name is accepted, only the code is unique (AC-013)', function () {
    $actor = paymentMethodUserWith(['create']);
    PaymentMethod::factory()->create(['name' => 'Taken Name']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/payment-methods', ['name' => 'Taken Name', 'code' => 'fresh_code'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Taken Name');

    expect(PaymentMethod::where('name', 'Taken Name')->count())->toBe(2);
});

it('create: 422 when code already exists, no row created (AC-014)', function () {
    $actor = paymentMethodUserWith(['create']);
    PaymentMethod::factory()->create(['code' => 'taken_code']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/payment-methods', ['name' => 'Fresh Name', 'code' => 'taken_code'])
        ->assertStatus(422)->assertJsonValidationErrors('code');

    expect(PaymentMethod::where('code', 'taken_code')->count())->toBe(1);
});

it('create: 422 when code is out of the snake_case regex (AC-015)', function (string $badCode) {
    $actor = paymentMethodUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/payment-methods', ['name' => "Bad Code {$badCode}", 'code' => $badCode])
        ->assertStatus(422)->assertJsonValidationErrors('code');
})->with(['Bonifico', '1abc', 'a-b', 'a b']);

it('create: 422 when payment_days is out of bounds, accepted at the boundaries (AC-016)', function () {
    $actor = paymentMethodUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/payment-methods', ['name' => 'Negative Days', 'code' => 'negative_days', 'payment_days' => -1])
        ->assertStatus(422)->assertJsonValidationErrors('payment_days');

    $this->postJson('/api/payment-methods', ['name' => 'Too Many Days', 'code' => 'too_many_days', 'payment_days' => 3651])
        ->assertStatus(422)->assertJsonValidationErrors('payment_days');

    $this->postJson('/api/payment-methods', ['name' => 'Zero Days', 'code' => 'zero_days', 'payment_days' => 0])
        ->assertCreated();

    $this->postJson('/api/payment-methods', ['name' => 'Max Days', 'code' => 'max_days', 'payment_days' => 3650])
        ->assertCreated();
});

it('create: 422 when name or code is missing (AC-017)', function () {
    $actor = paymentMethodUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/payment-methods', ['name' => '', 'code' => 'has_code'])
        ->assertStatus(422)->assertJsonValidationErrors('name');

    $this->postJson('/api/payment-methods', ['name' => 'Has Name'])
        ->assertStatus(422)->assertJsonValidationErrors('code');
});

// ---------------------------------------------------------------------------
// show — GET /api/payment-methods/{paymentMethod} (AC-018/019)
// ---------------------------------------------------------------------------

it('show: 200 with the full contract shape + permissions block (AC-018)', function () {
    $actor = paymentMethodUserWith(['view']);
    $target = PaymentMethod::factory()->create(['name' => 'Visible', 'code' => 'visible']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/payment-methods/{$target->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $target->id)
        ->assertJsonPath('data.name', 'Visible')
        ->assertJsonPath('data.code', 'visible')
        ->assertJsonStructure(['data', 'permissions']);
});

it('show: 404 for a non-existent id, no class/model name leaked (AC-019)', function () {
    $actor = paymentMethodUserWith(['view']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/payment-methods/999999')->assertNotFound();

    expect($response->json('message'))->not->toContain('PaymentMethod')->not->toContain('App\\');
});

// ---------------------------------------------------------------------------
// update — PATCH /api/payment-methods/{paymentMethod} (AC-020..025)
// ---------------------------------------------------------------------------

it('update: PATCH partial {description} updates only that field (AC-020)', function () {
    $actor = paymentMethodUserWith(['update']);
    $target = PaymentMethod::factory()->create(['name' => 'Kept Name', 'description' => 'Before']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/payment-methods/{$target->id}", ['description' => 'After'])
        ->assertOk()
        ->assertJsonPath('data.description', 'After')
        ->assertJsonPath('data.name', 'Kept Name');

    $this->assertDatabaseHas('payment_methods', ['id' => $target->id, 'name' => 'Kept Name', 'description' => 'After']);
});

it('update: PATCH {is_active: false} deactivates the record (AC-021)', function () {
    $actor = paymentMethodUserWith(['update']);
    $target = PaymentMethod::factory()->create(['is_active' => true]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/payment-methods/{$target->id}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);
});

// Same requirement change as AC-013: renaming onto another record's name is
// allowed now that `name` carries no uniqueness rule.
it('update: accepts a name that duplicates ANOTHER record, and its own unchanged name (AC-022)', function () {
    $actor = paymentMethodUserWith(['update']);
    PaymentMethod::factory()->create(['name' => 'Other']);
    $target = PaymentMethod::factory()->create(['name' => 'Mine']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/payment-methods/{$target->id}", ['name' => 'Other'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Other');

    $this->patchJson("/api/payment-methods/{$target->id}", ['name' => 'Other'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Other');
});

it('update: 422 when code is submitted with a DIFFERENT value, code unchanged at DB (AC-023)', function () {
    $actor = paymentMethodUserWith(['update']);
    $target = PaymentMethod::factory()->create(['code' => 'original_code']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/payment-methods/{$target->id}", ['code' => 'changed_code'])
        ->assertStatus(422)->assertJsonValidationErrors('code');

    $this->assertDatabaseHas('payment_methods', ['id' => $target->id, 'code' => 'original_code']);
});

it('update: 422 when code is submitted with the SAME value (prohibited rejects presence, not just change) (AC-024)', function () {
    $actor = paymentMethodUserWith(['update']);
    $target = PaymentMethod::factory()->create(['code' => 'same_code']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/payment-methods/{$target->id}", ['code' => 'same_code'])
        ->assertStatus(422)->assertJsonValidationErrors('code');
});

it('update: 422 on code even for the privileged super-admin role, no exception (AC-025, D-3)', function () {
    Role::create(['name' => RoleAssignmentGuard::PRIVILEGED_ROLE]);
    $actor = User::factory()->create();
    $actor->assignRole(RoleAssignmentGuard::PRIVILEGED_ROLE);
    $target = PaymentMethod::factory()->create(['code' => 'locked_code']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/payment-methods/{$target->id}", ['code' => 'attempted_change'])
        ->assertStatus(422)->assertJsonValidationErrors('code');

    $this->assertDatabaseHas('payment_methods', ['id' => $target->id, 'code' => 'locked_code']);
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/payment-methods/{paymentMethod} (AC-026/027, D-2 no guard)
// ---------------------------------------------------------------------------

it('delete: 204 + removed from DB, no guard of any kind (AC-026, D-2)', function () {
    $actor = paymentMethodUserWith(['delete']);
    $target = PaymentMethod::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/payment-methods/{$target->id}")->assertNoContent();

    $this->assertDatabaseMissing('payment_methods', ['id' => $target->id]);
});

it('delete: 404 for a non-existent id (AC-027)', function () {
    $actor = paymentMethodUserWith(['delete']);
    Sanctum::actingAs($actor);

    $this->deleteJson('/api/payment-methods/999999')->assertNotFound();
});
