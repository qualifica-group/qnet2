<?php

use App\Authorization\AssignablePermissionCatalogue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

// Spec 0430, AC-001 — the permissions and the menu entry.
uses(RefreshDatabase::class);

it('AC-001: permissions:sync creates exactly payroll-costs.view and payroll-costs.import, assignable from the roles', function () {
    Artisan::call('permissions:sync');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(Permission::query()->where('name', 'like', 'payroll-costs.%')->orderBy('name')->pluck('name')->all())
        ->toBe(['payroll-costs.import', 'payroll-costs.view'])
        ->and(app(AssignablePermissionCatalogue::class)->names())->toContain('payroll-costs.view', 'payroll-costs.import');
});

it('AC-001: the menu entry shows only with payroll-costs.view', function () {
    Artisan::call('permissions:sync');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Sanctum::actingAs(User::factory()->create());
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))->not->toContain('management-control-payroll-costs');

    Sanctum::actingAs(User::factory()->create()->givePermissionTo('payroll-costs.view'));
    $keys = navigationNodeKeys($this->getJson('/api/navigation')->json('data'));

    expect($keys)->toContain('management-control-payroll-costs');
});
