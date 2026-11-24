<?php

use App\Policies\DocumentRequestPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

/*
| Permission catalogue of the document requests (spec 0428, D-2): AC-005.
*/

uses(RefreshDatabase::class);

it('AC-005: permissions:sync creates exactly the four document-requests permissions', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
        expect(Permission::where('name', "document-requests.{$ability}")->exists())->toBeTrue();
    }

    expect(Permission::where('name', 'like', 'document-requests.%')->count())->toBe(4)
        ->and(DocumentRequestPolicy::abilities())->toBe(['viewAny', 'view', 'create', 'update']);
});

it('AC-005: the per-record abilities also need view on the work order', function () {
    $request = documentRequestFor(documentRequestWorkOrder());
    $policy = new DocumentRequestPolicy;

    $blind = documentRequestUserWith(['view', 'update'], canViewWorkOrders: false);
    $sighted = documentRequestUserWith(['view', 'update']);

    expect($policy->view($blind, $request))->toBeFalse()
        ->and($policy->update($blind, $request))->toBeFalse()
        ->and($policy->view($sighted, $request))->toBeTrue()
        ->and($policy->update($sighted, $request))->toBeTrue();
});
