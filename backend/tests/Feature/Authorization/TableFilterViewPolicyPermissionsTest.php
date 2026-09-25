<?php

use App\Authorization\AssignablePermissionCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

// Spec 0158: TableFilterViewPolicy::abilities() is REDUCED to ['publish'] —
// BasePolicy::permissions() is late-static-bound (static::abilities()), so
// SyncPermissions derives ONLY `table-filter-views.publish`, never the 8
// standard viewAny/view/create/update/delete/export/import/viewActivity
// (list/create are gated by the table definition's own viewAny; update/delete
// stay ownership rules — see TableFilterViewPolicy).

uses(RefreshDatabase::class);

it('permissions:sync creates ONLY table-filter-views.publish, none of the 8 standard abilities', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Permission::where('name', 'table-filter-views.publish')->exists())->toBeTrue();

    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
        expect(Permission::where('name', "table-filter-views.{$ability}")->exists())->toBeFalse();
    }
});

it('offers table-filter-views.publish to the Role form, so a role can be granted it from the UI', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    $catalogue = app(AssignablePermissionCatalogue::class);

    expect($catalogue->isAssignable('table-filter-views.publish'))->toBeTrue()
        ->and($catalogue->names())->toContain('table-filter-views.publish');
});
