<?php

use App\Authorization\AssignablePermissionCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    // A representative slice of the catalogue: form-module resources registered
    // in config/authorization.php, a permission-only resource (`attachments`,
    // assignable with no form module of its own), plus indirect sub-entity
    // resources that are NOT registered (governed via field-permissions).
    foreach ([
        'users.view', 'roles.update', 'business-functions.create',
        'companies.export', 'operational-sites.delete',
        'addresses.view', 'contacts.create', 'personal_data.update', 'attachments.delete',
    ] as $name) {
        Permission::findOrCreate($name);
    }

    $this->catalogue = app(AssignablePermissionCatalogue::class);
});

it('marks form-module permissions assignable and indirect ones not', function () {
    expect($this->catalogue->isAssignable('users.view'))->toBeTrue()
        ->and($this->catalogue->isAssignable('roles.update'))->toBeTrue()
        ->and($this->catalogue->isAssignable('business-functions.create'))->toBeTrue()
        ->and($this->catalogue->isAssignable('companies.export'))->toBeTrue()
        ->and($this->catalogue->isAssignable('operational-sites.delete'))->toBeTrue()
        ->and($this->catalogue->isAssignable('addresses.view'))->toBeFalse()
        ->and($this->catalogue->isAssignable('contacts.create'))->toBeFalse()
        ->and($this->catalogue->isAssignable('personal_data.update'))->toBeFalse()
        // `attachments` is a permission-only resource (config/authorization.php):
        // no form module, but assignable from the role form — spec 0076 files it
        // under the "shared" area.
        ->and($this->catalogue->isAssignable('attachments.delete'))->toBeTrue();
});

it('names() returns only the assignable catalogue, ordered, indirect excluded', function () {
    $names = $this->catalogue->names();

    expect($names)->toContain('users.view', 'companies.export', 'operational-sites.delete', 'attachments.delete')
        ->and($names)->not->toContain('addresses.view', 'contacts.create', 'personal_data.update')
        ->and($names)->toEqual(collect($names)->sort()->values()->all());
});

it('names() honours a case-insensitive substring search and a cap', function () {
    expect($this->catalogue->names('COMPAN'))->toEqual(['companies.export'])
        ->and($this->catalogue->names(null, 2))->toHaveCount(2)
        // A search matching only an indirect resource yields nothing (filtered out).
        ->and($this->catalogue->names('addresses'))->toBeEmpty();
});
