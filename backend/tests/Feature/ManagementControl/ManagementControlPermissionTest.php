<?php

use App\Authorization\AssignablePermissionCatalogue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

// Spec 0268, AC-001 / AC-002 — the permission, the menu entry and the access rules.
uses(RefreshDatabase::class);

it('AC-001: permissions:sync creates exactly management-control.view, assignable once, and it drives the menu entry', function () {
    Artisan::call('permissions:sync');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(Permission::query()->where('name', 'like', 'management-control.%')->pluck('name')->all())->toBe(['management-control.view'])
        ->and(app(AssignablePermissionCatalogue::class)->names())->toContain('management-control.view');

    Sanctum::actingAs(User::factory()->create()->givePermissionTo('roles.viewAny'));
    $areas = collect($this->getJson('/api/authorization/permission-catalogue')->assertOk()->json('data.areas'));
    $holders = $areas->filter(fn (array $area): bool => collect($area['resources'])->pluck('resource')->contains('management-control'));
    expect($holders)->toHaveCount(1);

    Sanctum::actingAs(User::factory()->create());
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))->not->toContain('management-control');

    Sanctum::actingAs(User::factory()->create()->givePermissionTo('management-control.view'));
    $section = collect($this->getJson('/api/navigation')->json('data'))->firstWhere('key', 'management-control-section');

    expect($section['label'])->toBe('navigation.managementControl')
        ->and($section['children'][0])->toMatchArray(['key' => 'management-control', 'label' => 'navigation.incomeStatement', 'route' => '/management-control/income-statement', 'icon' => 'chart-column']);
});

it('AC-002: both endpoints answer 401 anonymous and 403 without the permission', function () {
    $this->getJson('/api/management-control/income-statement')->assertUnauthorized();
    $this->getJson('/api/management-control/structure-costs')->assertUnauthorized();

    Sanctum::actingAs(mcActor(false));
    mcGet('income-statement')->assertForbidden();
    mcGet('structure-costs')->assertForbidden();
});

it('AC-002: a holder sees a commessa they do not supervise nor participate in', function () {
    mcWorkOrder();
    Sanctum::actingAs(mcActor());

    expect(mcGet('income-statement')->assertOk()->json('data.totals.work_orders_count'))->toBe(1);
});
