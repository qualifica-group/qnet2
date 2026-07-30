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

// ---------------------------------------------------------------------------
// auth + authorization
// ---------------------------------------------------------------------------

it('requires authentication (401)', function () {
    $this->getJson('/api/payment-methods/for-select')->assertUnauthorized();
});

it('forbids actors without payment-methods.viewAny (403)', function () {
    $actor = paymentMethodUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/payment-methods/for-select')->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-070 — mapping shape
// ---------------------------------------------------------------------------

it('maps a payment method to { id, label: name, subtitle: code, meta: { payment_days } } (AC-070)', function () {
    $actor = paymentMethodUserWith(['viewAny']);
    $target = PaymentMethod::factory()->create(['name' => 'Wire Transfer', 'code' => 'wire_transfer', 'payment_days' => 15]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/payment-methods/for-select?search=Wire Transfer')
        ->assertOk()
        ->assertJsonStructure([
            'items' => [['id', 'label', 'subtitle', 'meta' => ['payment_days']]],
            'export_link',
            'pagination' => ['total', 'offset', 'limit', 'total_pages'],
        ]);

    $item = collect($response->json('items'))->firstWhere('id', $target->id);
    expect($item)->toMatchArray(['id' => $target->id, 'label' => 'Wire Transfer', 'subtitle' => 'wire_transfer', 'meta' => ['payment_days' => 15]]);
});

it('meta.payment_days is present as null when the record has no payment_days (AC-070)', function () {
    $actor = paymentMethodUserWith(['viewAny']);
    $target = PaymentMethod::factory()->create(['name' => 'No Days', 'code' => 'no_days', 'payment_days' => null]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/payment-methods/for-select?search=No Days')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    expect($item['meta'])->toBe(['payment_days' => null]);
});

// ---------------------------------------------------------------------------
// AC-071 — active-only, sort_order-first ordering
// ---------------------------------------------------------------------------

it('excludes inactive methods (AC-071)', function () {
    $actor = paymentMethodUserWith(['viewAny']);
    $active = PaymentMethod::factory()->create(['name' => 'Active One', 'is_active' => true]);
    $inactive = PaymentMethod::factory()->create(['name' => 'Inactive One', 'is_active' => false]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/payment-methods/for-select')->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($active->id)->and($ids)->not->toContain($inactive->id);
});

it('orders by sort_order asc, not alphabetically (AC-071)', function () {
    $actor = paymentMethodUserWith(['viewAny']);
    $zLast = PaymentMethod::factory()->create(['name' => 'Zeta', 'sort_order' => 100]);
    $aFirst = PaymentMethod::factory()->create(['name' => 'Alpha', 'sort_order' => 101]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/payment-methods/for-select')->assertOk();
    $ids = collect($response->json('items'))
        ->pluck('id')
        ->filter(fn (int $id): bool => in_array($id, [$zLast->id, $aFirst->id], true))
        ->values()->all();

    expect($ids)->toBe([$zLast->id, $aFirst->id]);
});

// ---------------------------------------------------------------------------
// AC-072 — ids[] hydration even for inactive/off-page, does not inflate total
// ---------------------------------------------------------------------------

it('hydrates ids[] even when the method is inactive, and does not inflate total (AC-072)', function () {
    $actor = paymentMethodUserWith(['viewAny']);
    $inactive = PaymentMethod::factory()->create(['name' => 'Deactivated One', 'is_active' => false]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/payment-methods/for-select?ids[]={$inactive->id}")->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($inactive->id)
        ->and($response->json('pagination.total'))->toBe(0);
});

it('appends ids[] even when filtered out by search and does NOT inflate total (AC-072)', function () {
    $actor = paymentMethodUserWith(['viewAny']);
    $searchMatch = PaymentMethod::factory()->create(['name' => 'Zephyr Searchable']);
    $selected = PaymentMethod::factory()->create(['name' => 'Quentin Selected']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/payment-methods/for-select?search=Zephyr&ids[]={$selected->id}")->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($searchMatch->id)
        ->and($ids)->toContain($selected->id)
        ->and($response->json('pagination.total'))->toBe(1);
});

// ---------------------------------------------------------------------------
// AC-073 — search filters on name, limit cap
// ---------------------------------------------------------------------------

it('search="appr" returns only names containing "appr" (AC-073)', function () {
    $actor = paymentMethodUserWith(['viewAny']);
    $match = PaymentMethod::factory()->create(['name' => 'Approved Wire']);
    PaymentMethod::factory()->create(['name' => 'Cash On Delivery']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/payment-methods/for-select?search=appr')->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($match->id);
});

it('rejects a limit above 100 (422, AC-073)', function () {
    $actor = paymentMethodUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/payment-methods/for-select?limit=101')
        ->assertStatus(422)->assertJsonValidationErrors('limit');
});
