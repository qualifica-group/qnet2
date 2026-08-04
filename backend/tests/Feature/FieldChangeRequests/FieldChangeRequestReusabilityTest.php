<?php

use App\FieldChangeRequests\ProtectedFieldRegistry;
use App\Models\FieldChangeRequest;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/*
|--------------------------------------------------------------------------
| AC-054 — executable proof of the reusability requirement (spec 0078)
|--------------------------------------------------------------------------
|
| Every other file in this directory exercises the ONE cabled use case
| (request-management.source_id). This file protects a SECOND field on a
| DIFFERENT, already-registered resource — `payment-methods.is_active`
| (spec 0068), a real fillable boolean column, inline-editable in
| PaymentMethodColumnCatalog and declared in PaymentMethodsAuthorization::
| fields() — purely through a runtime config() override (a test fixture,
| never touching backend/config/field-change-requests.php). Every assertion
| below goes through REAL HTTP endpoints (permissions:sync, GET /api/meta,
| POST /api/field-change-requests, POST .../approve), not services called
| directly.
|
| What this file did NOT need to create, subclass or edit, to prove D-1/F-5/
| F-6's genericity claim:
|   - no new/modified App\Authorization\PaymentMethodsAuthorization
|   - no new "PaymentMethodChangeApplier" or any other per-resource applier
|     (the approval path below reuses TableCellUpdateService::update(), the
|     SAME generic write path source_id itself is applied through)
|   - no change to App\Tables\PaymentMethodsTableDefinition or
|     App\Tables\PaymentMethods\PaymentMethodColumnCatalog
|   - no change to App\FieldChangeRequests\ProtectedFieldRegistry,
|     App\Authorization\ProtectedFieldAwareAuthorization, or any class under
|     App\Services\FieldChangeRequests
| The only "new code" in this whole scenario is the config array literal in
| fcrRegisterFixtureField() below — exactly what D-1 promises: "protecting
| another field of another module = a config entry, zero code".
*/

uses(RefreshDatabase::class);

if (! function_exists('fcrRegisterFixtureField')) {
    /**
     * Adds a SECOND protected field, `payment-methods.is_active`
     * (fixture-only ability `updateActive`), alongside the production entry
     * for `request-management.source_id` — proving the addition is additive,
     * not a replacement.
     */
    function fcrRegisterFixtureField(): void
    {
        config()->set('field-change-requests.resources', array_merge(
            config('field-change-requests.resources'),
            [
                'payment-methods' => [
                    'record_path' => '/payment-methods',
                    'label' => 'navigation.paymentMethods',
                    'fields' => [
                        'is_active' => [
                            'ability' => 'updateActive',
                            'column' => 'is_active',
                            'label' => 'paymentMethods.columns.is_active',
                        ],
                    ],
                ],
            ],
        ));

        // ProtectedFieldRegistry (AppServiceProvider::boot) is a container
        // SINGLETON that memoizes config() into an internal map the first
        // time it is resolved (ProtectedFieldRegistry::$map). Force a
        // rebuild against the override above, otherwise every consumer
        // resolved later in this test (SyncPermissions,
        // AuthorizationRegistry::decorateWithProtectedFields,
        // ResourcePermissionsBuilder, FieldChangeRequestCreator/Approver)
        // would keep serving the pre-override map. If this call were removed
        // the assertions below would fail against the REAL registry
        // behaviour, not against a weakened test — a genuine testability
        // finding, not a workaround.
        app()->forgetInstance(ProtectedFieldRegistry::class);
    }
}

if (! function_exists('fcrEnsureBasePermissions')) {
    function fcrEnsureBasePermissions(): void
    {
        foreach (['viewAny', 'view', 'create', 'manage', 'export', 'viewActivity'] as $ability) {
            Permission::findOrCreate("field-change-requests.{$ability}");
        }
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("payment-methods.{$ability}");
        }
    }
}

if (! function_exists('fcrPaymentMethodActor')) {
    /**
     * @param  array<int, string>  $fieldChangeAbilities
     * @param  array<int, string>  $paymentMethodAbilities
     */
    function fcrPaymentMethodActor(array $fieldChangeAbilities, array $paymentMethodAbilities): User
    {
        $user = User::factory()->create();

        foreach ($fieldChangeAbilities as $ability) {
            $user->givePermissionTo("field-change-requests.{$ability}");
        }

        foreach ($paymentMethodAbilities as $ability) {
            $user->givePermissionTo("payment-methods.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-054 — permissions:sync derives the permission from the config fixture
// ---------------------------------------------------------------------------

it('AC-054: permissions:sync generates the fixture permission for a second protected field on a different resource', function () {
    fcrRegisterFixtureField();

    expect(Permission::where('name', 'payment-methods.updateActive')->exists())->toBeFalse();

    $exitCode = Artisan::call('permissions:sync');

    expect($exitCode)->toBe(0);
    expect(Permission::where('name', 'payment-methods.updateActive')->exists())->toBeTrue();
    // The pre-existing protected field (request-management.source_id, this
    // spec's ONE cabled use case) is untouched by adding the fixture.
    expect(Permission::where('name', 'request-management.updateSource')->exists())->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-054 — the resource's ceiling narrows is_active to readonly for whoever
// lacks the fixture permission, and stays editable for whoever holds it
// ---------------------------------------------------------------------------

it('AC-054: the payment-methods ceiling narrows is_active to readonly without the fixture permission, editable with it', function () {
    fcrRegisterFixtureField();
    fcrEnsureBasePermissions();
    Artisan::call('permissions:sync');

    // `create` (not `update`) because GET /api/meta/{resource} builds
    // permissions with model = null — the same create-context convention
    // ProtectedFieldCeilingTest (AC-002/003) uses for source_id, so the
    // base ceiling starts EDITABLE and the assertion below proves the
    // decorator, not an already-readonly base.
    $withoutFixturePermission = fcrPaymentMethodActor([], ['viewAny', 'create']);
    Sanctum::actingAs($withoutFixturePermission);

    $this->getJson('/api/meta/payment-methods')
        ->assertOk()
        ->assertJsonPath('permissions.fields.is_active.readonly', true)
        ->assertJsonPath('permissions.fields.is_active.editable', false)
        ->assertJsonPath('permissions.fields.is_active.visible', true)
        ->assertJsonPath('permissions.change_requestable_fields', ['is_active']);

    $withFixturePermission = fcrPaymentMethodActor([], ['viewAny', 'create', 'updateActive']);
    Sanctum::actingAs($withFixturePermission);

    $response = $this->getJson('/api/meta/payment-methods')->assertOk();

    $response->assertJsonPath('permissions.fields.is_active.editable', true);
    expect($response->json('permissions.change_requestable_fields'))->not->toContain('is_active');
});

// ---------------------------------------------------------------------------
// AC-054 — the full lifecycle through the REAL endpoints: create -> approve
// -> the fixture field is actually applied to the payment_methods row
// ---------------------------------------------------------------------------

it('AC-054: create + approve through the real endpoints applies the fixture field end to end', function () {
    fcrRegisterFixtureField();
    fcrEnsureBasePermissions();
    Artisan::call('permissions:sync');

    $paymentMethod = PaymentMethod::factory()->create(['is_active' => true]);

    $requester = fcrPaymentMethodActor(['create'], ['view']);
    Sanctum::actingAs($requester);

    $createResponse = $this->postJson('/api/field-change-requests', [
        'resource' => 'payment-methods',
        'subject_id' => $paymentMethod->id,
        'field' => 'is_active',
        'requested_value' => false,
        'reason' => 'Fixture proof for AC-054.',
    ])->assertCreated();

    $createResponse->assertJsonPath('data.resource', 'payment-methods')
        ->assertJsonPath('data.field', 'is_active')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.current_value', true)
        ->assertJsonPath('data.requested_value', false);

    $fieldChangeRequest = FieldChangeRequest::sole();
    expect($fieldChangeRequest->resource)->toBe('payment-methods')
        ->and($fieldChangeRequest->subject_type)->toBe('payment_method')
        ->and($fieldChangeRequest->pending_key)->toBe("payment_method:{$paymentMethod->id}:is_active");

    // The requester holds no `payment-methods.updateActive`: proposing,
    // rather than writing directly, is the only path open to them (mirrors
    // AC-013's guard chain, unchanged for this resource).
    expect($requester->can('payment-methods.updateActive'))->toBeFalse();

    $manager = fcrPaymentMethodActor(['manage'], ['view', 'update', 'updateActive']);
    Sanctum::actingAs($manager);

    $this->postJson("/api/field-change-requests/{$fieldChangeRequest->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    expect($paymentMethod->fresh()->is_active)->toBeFalse();

    $fieldChangeRequest->refresh();
    expect($fieldChangeRequest->status->value)->toBe('approved')
        ->and($fieldChangeRequest->handled_by_id)->toBe($manager->id)
        ->and($fieldChangeRequest->handled_at)->not->toBeNull()
        ->and($fieldChangeRequest->pending_key)->toBeNull();
});
