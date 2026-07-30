<?php

use App\Models\PaymentMethod;
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

/**
 * AC-090/091: this is the ONLY test that proves LogsModelActivity,
 * config/activity-log.php ('payment-methods' => PaymentMethod::class) and the
 * morph map ('payment_method' => PaymentMethod::class in AppServiceProvider)
 * are ALL THREE wired correctly (mirrors RewardStatusActivityLogTest).
 */
it('create + update produce created/updated activity-log events with the changed fields (AC-090)', function () {
    $actor = paymentMethodUserWith(['create', 'update', 'view', 'viewActivity']);
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/payment-methods', ['name' => 'Wire Transfer', 'code' => 'wire_transfer'])
        ->assertCreated();
    $id = $created->json('data.id');

    $this->patchJson("/api/payment-methods/{$id}", ['name' => 'Wire Transfer Plus', 'is_active' => false])
        ->assertOk();

    $response = $this->getJson("/api/activity-log/payment-methods/{$id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['success', 'message', 'data' => ['items', 'next_cursor']]);

    $items = collect($response->json('data.items'));

    $createdEvent = $items->firstWhere('event', 'created');
    $updatedEvent = $items->firstWhere('event', 'updated');

    expect($createdEvent)->not->toBeNull()
        ->and($updatedEvent)->not->toBeNull();

    $updatedFields = collect($updatedEvent['changes'])->pluck('field')->all();
    expect($updatedFields)->toContain('name', 'is_active');

    $byField = collect($updatedEvent['changes'])->keyBy('field');
    expect($byField['name']['old_value'])->toBe('Wire Transfer')
        ->and($byField['name']['new_value'])->toBe('Wire Transfer Plus');
});

it('403 without payment-methods.viewActivity (AC-091)', function () {
    $actor = paymentMethodUserWith(['view']);
    $target = PaymentMethod::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/activity-log/payment-methods/{$target->id}")->assertForbidden();
});

it('403 with payment-methods.viewActivity but without payment-methods.view (AC-091)', function () {
    $actor = paymentMethodUserWith(['viewActivity']);
    $target = PaymentMethod::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/activity-log/payment-methods/{$target->id}")->assertForbidden();
});
