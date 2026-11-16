<?php

use Database\Seeders\EnrolleeDocumentsAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Task 427522: the one-shot granting "Gestione Iscritti -> Documenti" to the
 * roles that read the enrollees.
 */
uses(RefreshDatabase::class);

it('grants the enrollee documents to the reading roles only, idempotently', function () {
    Permission::findOrCreate('enrollee-management.viewDocuments', 'web');
    $commercial = Role::findOrCreate('commerciale', 'web');
    $trainingOffice = Role::findOrCreate('segreteria-didattica', 'web');
    $other = Role::findOrCreate('tecnico-consulenza', 'web');

    $this->seed(EnrolleeDocumentsAccessSeeder::class);
    $this->seed(EnrolleeDocumentsAccessSeeder::class);

    expect($commercial->fresh()->hasPermissionTo('enrollee-management.viewDocuments'))->toBeTrue()
        ->and($trainingOffice->fresh()->hasPermissionTo('enrollee-management.viewDocuments'))->toBeTrue()
        ->and($other->fresh()->hasPermissionTo('enrollee-management.viewDocuments'))->toBeFalse()
        ->and($commercial->fresh()->permissions)->toHaveCount(1);
});
