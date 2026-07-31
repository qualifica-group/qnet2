<?php

use App\Authorization\AssignablePermissionCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

// D-6: NotePolicy::abilities() is REDUCED to ['create'] — BasePolicy::
// permissions() is late-static-bound (static::abilities()), so overriding
// abilities() this way is enough for SyncPermissions to derive ONLY
// `notes.create`, never the 8 standard viewAny/view/update/delete/export/
// import/viewActivity (unused: reads are gated by the host entity via
// NoteEntityRegistry, writes by ownership — see NotePolicy::update/delete).

uses(RefreshDatabase::class);

it('permissions:sync creates ONLY notes.create, none of the 8 standard abilities (D-6)', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Permission::where('name', 'notes.create')->exists())->toBeTrue();

    foreach (['viewAny', 'view', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
        expect(Permission::where('name', "notes.{$ability}")->exists())->toBeFalse();
    }
});

// `notes` owns no form, so it has no ResourceAuthorization: without the
// permission-only list of config/authorization.php the permission above would
// exist while being un-grantable from the Role form — assignable to a role
// only by a seeder or through the super-admin bypass.
it('offers notes.create to the Role form, so a role can be granted it from the UI', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    $catalogue = app(AssignablePermissionCatalogue::class);

    expect($catalogue->isAssignable('notes.create'))->toBeTrue()
        ->and($catalogue->names())->toContain('notes.create')
        // The indirect sub-entity permissions stay out: they are governed by
        // the field-permission matrix of their parent form, not by a checkbox.
        ->and($catalogue->isAssignable('addresses.view'))->toBeFalse();
});
