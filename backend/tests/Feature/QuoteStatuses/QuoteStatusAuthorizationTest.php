<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * The `quote-statuses` half of the authorization surface (spec 0065):
 * permissions:sync (AC-060) and the navigation gate (AC-065). The CRUD
 * 403/field-permission coverage already lives in QuoteStatusCrudTest.php.
 */
uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-060 — permissions:sync creates all 8 quote-statuses.* permissions
// ---------------------------------------------------------------------------

it('AC-060: permissions:sync creates all 8 quote-statuses.* permissions', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
        expect(Permission::where('name', "quote-statuses.{$ability}")->exists())->toBeTrue();
    }
});

// ---------------------------------------------------------------------------
// AC-065 — navigation gate
// ---------------------------------------------------------------------------

it('AC-065: the quote-statuses navigation node only shows with quote-statuses.view', function () {
    Permission::findOrCreate('quote-statuses.view');

    $withoutView = User::factory()->create();
    Sanctum::actingAs($withoutView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->not->toContain('quote-statuses');

    $withView = User::factory()->create();
    $withView->givePermissionTo('quote-statuses.view');
    Sanctum::actingAs($withView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->toContain('quote-statuses');
});
