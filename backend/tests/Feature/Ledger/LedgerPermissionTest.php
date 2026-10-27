<?php

use App\Authorization\AssignablePermissionCatalogue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

// Spec 0266, AC-014 — `ledger.view` comes from permissions:sync, is assignable
// from the Role form (once) and drives the "Partitario" menu entry.
uses(RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('permissions:sync');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('AC-014: permissions:sync creates exactly ledger.view and it is assignable', function () {
    expect(Permission::query()->where('name', 'like', 'ledger.%')->pluck('name')->all())->toBe(['ledger.view'])
        ->and(app(AssignablePermissionCatalogue::class)->names())->toContain('ledger.view');
});

it('AC-014: the Role form offers ledger.view once, in the accounting area', function () {
    Sanctum::actingAs(User::factory()->create()->givePermissionTo('roles.viewAny'));

    $areas = collect($this->getJson('/api/authorization/permission-catalogue')->assertOk()->json('data.areas'));
    $holders = $areas->filter(fn (array $area): bool => collect($area['resources'])->pluck('resource')->contains('ledger'));

    expect($holders->pluck('key')->all())->toBe(['accounting'])
        ->and($holders->first()['resources'])->toContain(['resource' => 'ledger', 'label_key' => 'navigation.ledger', 'permissions' => [['name' => 'ledger.view', 'ability' => 'view']], 'fields' => []]);
});

it('AC-014: the Partitario menu entry shows only to who holds ledger.view', function () {
    Sanctum::actingAs(User::factory()->create());
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))->not->toContain('ledger');

    Sanctum::actingAs(User::factory()->create()->givePermissionTo('ledger.view'));
    $navigation = $this->getJson('/api/navigation')->json('data');

    // REQUIREMENT CHANGED (spec 0318): Partitario sits in Contabilita' > Finanziario.
    expect(navigationNodeKeys($navigation))->toContain('ledger')
        ->and(navigationNode($navigation, 'accounting-finance')['children'][0])->toMatchArray(['key' => 'ledger', 'route' => '/ledger', 'icon' => 'book-open']);
});
