<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

it('AC-018: permissions:sync creates the profile and accrual abilities of the contract and nothing else for the ledger', function () {
    Artisan::call('permissions:sync');

    $names = Permission::query()->pluck('name');
    $accruals = $names->filter(fn (string $name): bool => str_starts_with($name, 'commission-accruals.'))->sort()->values()->all();
    $profiles = $names->filter(fn (string $name): bool => str_starts_with($name, 'agent-fiscal-profiles.'))->sort()->values()->all();

    expect($accruals)->toBe(['commission-accruals.export', 'commission-accruals.view', 'commission-accruals.viewAny'])
        ->and($profiles)->toBe([
            'agent-fiscal-profiles.create', 'agent-fiscal-profiles.delete', 'agent-fiscal-profiles.export',
            'agent-fiscal-profiles.update', 'agent-fiscal-profiles.view', 'agent-fiscal-profiles.viewActivity',
            'agent-fiscal-profiles.viewAny',
        ]);
});

it('AC-018: the Provvigioni agenti group shows only the children the user can view', function () {
    $navigation = function (array $permissions): string {
        $user = User::factory()->create();
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Sanctum::actingAs($user);

        return json_encode(test()->getJson('/api/navigation')->assertOk()->json());
    };

    expect($navigation(['commission-accruals.viewAny', 'agent-fiscal-profiles.create']))->not->toContain('accounting-commissions');

    $onlyAccruals = $navigation(['commission-accruals.view']);
    expect($onlyAccruals)->toContain('accounting-commissions')->toContain('commission-accruals')->toContain('book-open')
        ->not->toContain('agent-fiscal-profiles');

    $onlyProfiles = $navigation(['agent-fiscal-profiles.view']);
    expect($onlyProfiles)->toContain('accounting-commissions')->toContain('agent-fiscal-profiles')->toContain('id-card')
        ->not->toContain('commission-accruals');
});

it('AC-018: the field catalogue exposes the commission-accruals fields to a role editor', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo(Permission::findOrCreate('roles.create', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Sanctum::actingAs($actor);

    $resources = collect($this->getJson('/api/authorization/fields')->assertOk()->json('data.resources'))->keyBy('resource');

    expect($resources)->toHaveKey('commission-accruals')
        ->and(collect($resources['commission-accruals']['fields'])->pluck('key')->all())->toBe(['base_amount', 'amount']);
});
