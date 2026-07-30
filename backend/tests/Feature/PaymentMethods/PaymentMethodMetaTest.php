<?php

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

it('403 without payment-methods.viewAny', function () {
    $actor = paymentMethodUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/payment-methods')->assertForbidden();
});

it('200: field catalogue is [name, code, description, payment_instructions, payment_days, is_active], in this frozen order (AC-040)', function () {
    $actor = paymentMethodUserWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/meta/payment-methods')
        ->assertOk()
        ->assertJsonPath('success', true);

    $keys = collect($response->json('data.fields'))->pluck('key')->all();
    expect($keys)->toBe(['name', 'code', 'description', 'payment_instructions', 'payment_days', 'is_active']);

    $fields = collect($response->json('data.fields'))->keyBy('key');
    expect($fields['name']['mandatory'])->toBeTrue()
        ->and($fields['name']['type'])->toBe('text')
        ->and($fields['code']['mandatory'])->toBeTrue()
        ->and($fields['code']['type'])->toBe('text')
        ->and($fields['description']['mandatory'])->toBeFalse()
        ->and($fields['description']['type'])->toBe('textarea')
        ->and($fields['payment_instructions']['mandatory'])->toBeFalse()
        ->and($fields['payment_instructions']['type'])->toBe('textarea')
        ->and($fields['payment_days']['mandatory'])->toBeFalse()
        ->and($fields['payment_days']['type'])->toBe('number')
        ->and($fields['is_active']['mandatory'])->toBeFalse()
        ->and($fields['is_active']['type'])->toBe('boolean');

    foreach ($response->json('permissions.fields') as $field) {
        expect($field)->toHaveKeys(['visible', 'hidden', 'editable', 'readonly', 'required', 'disabled']);
    }
});

it('200: permissions.fields.code.editable is true only in create context, false/readonly on a saved record (AC-041)', function () {
    $actor = paymentMethodUserWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/payment-methods')
        ->assertOk()
        ->assertJsonPath('permissions.fields.code.editable', true)
        ->assertJsonPath('permissions.fields.code.required', true);

    // GET /meta always resolves model=null (create-context skeleton); the
    // readonly-on-existing-record branch is exercised by GET show and PATCH
    // (PaymentMethodCrudTest AC-023/024/025).
});

it('200: permissions.fields does not contain sort_order (AC-042)', function () {
    $actor = paymentMethodUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/meta/payment-methods')->assertOk();

    expect(collect($response->json('data.fields'))->pluck('key')->all())->not->toContain('sort_order')
        ->and(array_keys($response->json('permissions.fields')))->not->toContain('sort_order');
});

it('permissions.actions maps delete/export/import/view_activity to the resource permissions', function () {
    $actor = paymentMethodUserWith(['viewAny', 'export']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/payment-methods')
        ->assertOk()
        ->assertJsonPath('permissions.actions.delete', false)
        ->assertJsonPath('permissions.actions.export', true)
        ->assertJsonPath('permissions.actions.import', false)
        ->assertJsonPath('permissions.actions.view_activity', false);
});
