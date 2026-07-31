<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * The `contracts` authorization surface (spec 0072, MT-02, BR-8): the
 * permissions:sync catalogue for the 5 domain-action abilities MINUS the
 * absent create/delete/import (AC-037, D-6), and the GET /api/meta/contracts
 * field/action catalogue (AC-038).
 */
uses(RefreshDatabase::class);

if (! function_exists('contractAuthUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function contractAuthUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'update', 'export', 'viewActivity', 'validate', 'terminate', 'schedule', 'changeStatus', 'reactivate'] as $ability) {
            Permission::findOrCreate("contracts.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("contracts.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-037 — permissions:sync creates the 5 domain abilities, never create/delete
// ---------------------------------------------------------------------------

it('AC-037: permissions:sync creates the 5 contracts domain permissions and never create/delete', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['viewAny', 'view', 'update', 'export', 'viewActivity', 'validate', 'terminate', 'schedule', 'changeStatus', 'reactivate'] as $ability) {
        expect(Permission::where('name', "contracts.{$ability}")->exists())->toBeTrue();
    }

    expect(Permission::where('name', 'contracts.create')->exists())->toBeFalse()
        ->and(Permission::where('name', 'contracts.delete')->exists())->toBeFalse()
        ->and(Permission::where('name', 'contracts.import')->exists())->toBeFalse();
});

// ---------------------------------------------------------------------------
// AC-038 — GET /api/meta/contracts field/action catalogue
// ---------------------------------------------------------------------------

it('AC-038: GET /api/meta/contracts lists the 9 fields and 7 actions', function () {
    $actor = contractAuthUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/meta/contracts')->assertOk();

    $fieldKeys = collect($response->json('data.fields'))->pluck('key')->all();

    expect($fieldKeys)->toEqual([
        'contract_status_id', 'accepted_at', 'validated_at', 'renewal_date',
        'expiry_date', 'terminated_at', 'termination_reason', 'payment_notes', 'comments',
    ]);

    expect(array_keys($response->json('permissions.actions')))->toEqual([
        'validate', 'terminate', 'schedule', 'change_status', 'reactivate', 'export', 'view_activity',
    ]);
});

it('GET /api/meta/contracts is 403 without contracts.viewAny', function () {
    $actor = contractAuthUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/contracts')->assertForbidden();
});
