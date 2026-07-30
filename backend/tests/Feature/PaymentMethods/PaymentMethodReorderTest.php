<?php

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| POST /api/payment-methods/reorder (spec 0068, D-1)
|--------------------------------------------------------------------------
|
| Unlike reward-statuses/opportunity-statuses (head/tail system rows),
| payment-methods has NO system-row concept: $orderedIds must be exactly the
| FULL id set (PaymentMethodOrderManager, not StatusOrderManager).
*/

if (! function_exists('paymentMethodUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function paymentMethodUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("payment-methods.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("payment-methods.{$ability}");
        }

        return $user;
    }
}

it('reorder: a valid permutation resequences all rows to 10/20/30 (AC-080)', function () {
    $actor = paymentMethodUserWith(['update']);
    $first = PaymentMethod::factory()->create(['name' => 'Alpha']);
    $second = PaymentMethod::factory()->create(['name' => 'Beta']);
    $third = PaymentMethod::factory()->create(['name' => 'Gamma']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/payment-methods/reorder', [
        'ordered_ids' => [$third->id, $first->id, $second->id],
    ])->assertOk();

    $rows = collect($response->json('data'))->keyBy('id');
    expect($rows[$third->id]['sort_order'])->toBe(10)
        ->and($rows[$first->id]['sort_order'])->toBe(20)
        ->and($rows[$second->id]['sort_order'])->toBe(30);

    $this->assertDatabaseHas('payment_methods', ['id' => $third->id, 'sort_order' => 10]);
});

it('reorder: every entry carries system_key: null (D-5, AC-081)', function () {
    $actor = paymentMethodUserWith(['update']);
    $only = PaymentMethod::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/payment-methods/reorder', ['ordered_ids' => [$only->id]])->assertOk();

    expect($response->json('data.0'))->toHaveKey('system_key')
        ->and($response->json('data.0.system_key'))->toBeNull();
});

it('reorder: 422 when ordered_ids is missing a row id (AC-082)', function () {
    $actor = paymentMethodUserWith(['update']);
    PaymentMethod::factory()->create();
    $second = PaymentMethod::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/payment-methods/reorder', ['ordered_ids' => [$second->id]])
        ->assertStatus(422);
});

it('reorder: 422 when ordered_ids contains a duplicate (AC-082)', function () {
    $actor = paymentMethodUserWith(['update']);
    $only = PaymentMethod::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/payment-methods/reorder', ['ordered_ids' => [$only->id, $only->id]])
        ->assertStatus(422)->assertJsonValidationErrors('ordered_ids.0');
});

it('reorder: 422 when ordered_ids includes a non-existent id (AC-082)', function () {
    $actor = paymentMethodUserWith(['update']);
    $only = PaymentMethod::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/payment-methods/reorder', ['ordered_ids' => [$only->id, 999999]])
        ->assertStatus(422);
});

it('reorder: 422 when ordered_ids is empty (AC-082)', function () {
    $actor = paymentMethodUserWith(['update']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/payment-methods/reorder', ['ordered_ids' => []])
        ->assertStatus(422)->assertJsonValidationErrors('ordered_ids');
});

it('create: a new row is placed after the last one (max + 10) (AC-083)', function () {
    $actor = paymentMethodUserWith(['create', 'update']);
    PaymentMethod::factory()->create(['sort_order' => 30]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/payment-methods', ['name' => 'New Last', 'code' => 'new_last'])
        ->assertCreated();

    expect($response->json('data.sort_order'))->toBe(40);
});

it('reorder: 403 without payment-methods.update, order unchanged (AC-084)', function () {
    $actor = paymentMethodUserWith([]);
    $only = PaymentMethod::factory()->create(['sort_order' => 20]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/payment-methods/reorder', ['ordered_ids' => [$only->id]])->assertForbidden();

    $this->assertDatabaseHas('payment_methods', ['id' => $only->id, 'sort_order' => 20]);
});
