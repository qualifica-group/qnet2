<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// AC-001: after `permissions:sync`, the protected-field permission exists.
it('creates the protected-field permission (AC-001)', function (): void {
    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Permission::where('name', 'request-management.updateSource')->exists())->toBeTrue();
});

// AC-001: pre-existing permissions are neither duplicated nor removed across
// repeated runs.
it('does not duplicate or remove permissions across repeated runs (AC-001)', function (): void {
    $this->artisan('permissions:sync')->assertSuccessful();

    $countAfterFirstRun = Permission::count();
    $sourcePermissionCountAfterFirstRun = Permission::where('name', 'request-management.updateSource')->count();

    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Permission::count())->toBe($countAfterFirstRun)
        ->and(Permission::where('name', 'request-management.updateSource')->count())
        ->toBe($sourcePermissionCountAfterFirstRun)
        ->toBe(1);
});

// AC-054 groundwork: a fixture-only second protected field also mints its
// permission — proof the third source is config-driven, not a one-off.
it('creates the permission for a test-only protected field added to config', function (): void {
    config(['field-change-requests.resources.widgets' => [
        'record_path' => '/widgets',
        'label' => 'navigation.widgets',
        'fields' => [
            'name' => [
                'ability' => 'updateName',
                'column' => 'name',
                'label' => 'widgets.columns.name',
            ],
        ],
    ]]);

    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Permission::where('name', 'widgets.updateName')->exists())->toBeTrue();
});
