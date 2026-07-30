<?php

use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// PATCH /api/tables/payment-methods/rows/{row} — spec 0068 D-4: the FIRST
// exercise of the generic engine's `type: 'boolean'` + `editable: true`
// pairing (CellValueValidator::typeRules()'s 'boolean' branch existed but
// was never covered by a test until this domain).

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
// AC-060/061 — happy path, both directions, value:false is not dropped
// ---------------------------------------------------------------------------

it('PATCH {column: is_active, value: false} -> 200, persisted, data.editable present (AC-060)', function () {
    $actor = paymentMethodUserWith(['viewAny', 'update']);
    $target = PaymentMethod::factory()->create(['is_active' => true]);
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/tables/payment-methods/rows/{$target->id}", [
        'column' => 'is_active', 'value' => false,
    ])->assertOk();

    $response->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $target->id)
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.editable', true);

    expect($target->fresh()->is_active)->toBeFalse();
});

it('PATCH {value: true} on an inactive record reactivates it (AC-061)', function () {
    $actor = paymentMethodUserWith(['viewAny', 'update']);
    $target = PaymentMethod::factory()->create(['is_active' => false]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/payment-methods/rows/{$target->id}", [
        'column' => 'is_active', 'value' => true,
    ])->assertOk()->assertJsonPath('data.is_active', true);

    expect($target->fresh()->is_active)->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-062 — column allow-list: only is_active is editable
// ---------------------------------------------------------------------------

it('PATCH column=name (real, not editable) -> 422, no write (AC-062)', function () {
    $actor = paymentMethodUserWith(['viewAny', 'update']);
    $target = PaymentMethod::factory()->create(['name' => 'Untouched']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/payment-methods/rows/{$target->id}", [
        'column' => 'name', 'value' => 'Hacked',
    ])->assertStatus(422);

    expect($target->fresh()->name)->toBe('Untouched');
});

it('PATCH column=sort_order -> 422, no write (AC-062)', function () {
    $actor = paymentMethodUserWith(['viewAny', 'update']);
    $target = PaymentMethod::factory()->create(['sort_order' => 10]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/payment-methods/rows/{$target->id}", [
        'column' => 'sort_order', 'value' => 999,
    ])->assertStatus(422);

    expect($target->fresh()->sort_order)->toBe(10);
});

// ---------------------------------------------------------------------------
// AC-063 — value validation: non-boolean, missing
// ---------------------------------------------------------------------------

it('PATCH value="maybe" (not boolean) -> 422, no write (AC-063)', function () {
    $actor = paymentMethodUserWith(['viewAny', 'update']);
    $target = PaymentMethod::factory()->create(['is_active' => true]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/payment-methods/rows/{$target->id}", [
        'column' => 'is_active', 'value' => 'maybe',
    ])->assertStatus(422);

    expect($target->fresh()->is_active)->toBeTrue();
});

it('PATCH without the value key -> 422 (is_active is not nullable) (AC-063)', function () {
    $actor = paymentMethodUserWith(['viewAny', 'update']);
    $target = PaymentMethod::factory()->create(['is_active' => true]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/payment-methods/rows/{$target->id}", [
        'column' => 'is_active',
    ])->assertStatus(422);

    expect($target->fresh()->is_active)->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-064 — authorization: base ability + field permission
// ---------------------------------------------------------------------------

it('PATCH without payment-methods.update -> 403, no write (AC-064)', function () {
    $actor = paymentMethodUserWith(['viewAny']);
    $target = PaymentMethod::factory()->create(['is_active' => true]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/payment-methods/rows/{$target->id}", [
        'column' => 'is_active', 'value' => false,
    ])->assertForbidden();

    expect($target->fresh()->is_active)->toBeTrue();
});

it('PATCH with a DB row denying is_active -> 403, no write (AC-064)', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("payment-methods.{$ability}");
    }

    $role = Role::create(['name' => 'payment-method-is-active-locked']);
    $role->givePermissionTo(['payment-methods.view', 'payment-methods.update']);
    $role->fieldPermissions()->create([
        'resource' => 'payment-methods',
        'field' => 'is_active',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = PaymentMethod::factory()->create(['is_active' => true]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/payment-methods/rows/{$target->id}", [
        'column' => 'is_active', 'value' => false,
    ])->assertForbidden();

    expect($target->fresh()->is_active)->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-065 — audit
// ---------------------------------------------------------------------------

it('a successful PATCH writes an activity-log entry (log name payment_methods) for is_active (AC-065)', function () {
    $actor = paymentMethodUserWith(['viewAny', 'update']);
    $target = PaymentMethod::factory()->create(['is_active' => true]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/payment-methods/rows/{$target->id}", [
        'column' => 'is_active', 'value' => false,
    ])->assertOk();

    $activity = Activity::query()
        ->where('log_name', 'payment_methods')
        ->where('subject_type', $target->getMorphClass())
        ->where('subject_id', $target->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($actor->id);
    expect($activity->properties->get('attributes'))->toHaveKey('is_active', false);
});
