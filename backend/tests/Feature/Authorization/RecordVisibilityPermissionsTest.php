<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

// Spec 0316, AC-041: the record-visibility levels exist after
// `permissions:sync` and the role editor's catalogue exposes them under their
// modules.
uses(RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('permissions:sync');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

const VISIBILITY_ABILITIES = [
    'opportunities' => ['viewAll'],
    'quotes' => ['viewSite', 'viewAll'],
    'work-orders' => ['viewSite'],
    'contracts' => ['viewSite', 'viewAll'],
];

it('AC-041: permissions:sync creates the new visibility permissions', function () {
    foreach (VISIBILITY_ABILITIES as $resource => $abilities) {
        foreach ($abilities as $ability) {
            expect(Permission::where('name', "{$resource}.{$ability}")->exists())
                ->toBeTrue("{$resource}.{$ability} missing");
        }
    }
});

it('AC-041: the permission catalogue exposes them under their modules', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('roles.viewAny');
    Sanctum::actingAs($actor);

    $areas = collect($this->getJson('/api/authorization/permission-catalogue')->assertOk()->json('data.areas'));
    $resources = $areas->flatMap(fn (array $area): array => $area['resources'])->keyBy('resource');

    foreach (VISIBILITY_ABILITIES as $resource => $abilities) {
        $exposed = collect($resources[$resource]['permissions'])->pluck('ability')->all();

        expect($exposed)->toContain(...$abilities);
    }
});
