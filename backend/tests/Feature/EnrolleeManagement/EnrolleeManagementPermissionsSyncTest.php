<?php

use App\RequestManagement\RequestModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// Spec 0130 D-4/D-8 — AC-001: permissions:sync mints the dedicated
// enrollee-management.* catalogue (RequestModule::Enrollees->abilities(),
// the same 16 RequestManagementPolicy exposes minus `create`) plus the FCR
// field-protection permission `enrollee-management.updateSource`
// (config/field-change-requests.php), and NEVER `enrollee-management.create`
// (D-8: no creation surface at all). request-management.* stays untouched.

it('permissions:sync creates the 16 enrollee-management abilities, no create (AC-001)', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    $abilities = RequestModule::Enrollees->abilities();

    expect($abilities)->toHaveCount(16)->not->toContain('create');

    foreach ($abilities as $ability) {
        expect(Permission::where('name', "enrollee-management.{$ability}")->exists())->toBeTrue();
    }

    expect(Permission::where('name', 'enrollee-management.create')->exists())->toBeFalse();
});

it('permissions:sync creates enrollee-management.updateSource from the FCR registry (AC-001)', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Permission::where('name', 'enrollee-management.updateSource')->exists())->toBeTrue();
});

it('permissions:sync leaves request-management.* invariant (AC-001)', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    $abilities = RequestModule::Requests->abilities();

    expect($abilities)->toContain('create');

    foreach ($abilities as $ability) {
        expect(Permission::where('name', "request-management.{$ability}")->exists())->toBeTrue();
    }

    expect(Permission::where('name', 'request-management.updateSource')->exists())->toBeTrue();
});
