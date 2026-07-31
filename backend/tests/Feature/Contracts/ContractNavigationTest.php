<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * The `contracts`/`contract-statuses` navigation nodes (spec 0072, MT-05):
 * both gated server-side by their own view permission, same pattern as
 * QuoteStatusAuthorizationTest's AC-065. Uses the REAL config/navigation.php
 * (not overridden), so this also guards against the entries being removed or
 * mis-keyed by a future edit.
 */
uses(RefreshDatabase::class);

it('the contract-statuses navigation node only shows with contract-statuses.view', function () {
    Permission::findOrCreate('contract-statuses.view');

    $withoutView = User::factory()->create();
    Sanctum::actingAs($withoutView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->not->toContain('contract-statuses');

    $withView = User::factory()->create();
    $withView->givePermissionTo('contract-statuses.view');
    Sanctum::actingAs($withView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->toContain('contract-statuses');
});

it('the contracts navigation node only shows with contracts.view', function () {
    Permission::findOrCreate('contracts.view');

    $withoutView = User::factory()->create();
    Sanctum::actingAs($withoutView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->not->toContain('contracts');

    $withView = User::factory()->create();
    $withView->givePermissionTo('contracts.view');
    Sanctum::actingAs($withView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->toContain('contracts');
});
