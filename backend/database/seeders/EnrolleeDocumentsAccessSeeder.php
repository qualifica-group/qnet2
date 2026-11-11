<?php

namespace Database\Seeders;

use Database\Seeders\QualificaCatalog\LegacyRoleCatalogue;
use Database\Seeders\QualificaCatalog\OperatorRoleCatalogue;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * One-shot for an installation seeded before task 427522:
 * `php artisan db:seed --class=EnrolleeDocumentsAccessSeeder --force`.
 * A fresh installation gets the same through the role catalogues.
 *
 * Gives "Gestione Iscritti -> Documenti" to the roles that read the enrollees
 * without it, so a request's documents stay visible once it reaches
 * "Associato". Only ADDS the permission; a role missing on this installation
 * is skipped, and re-running it changes nothing.
 */
class EnrolleeDocumentsAccessSeeder extends Seeder
{
    private const string PERMISSION = 'enrollee-management.viewDocuments';

    private const array ROLES = [
        OperatorRoleCatalogue::COMMERCIAL_ROLE,
        OperatorRoleCatalogue::TEACHING_SUPERVISOR_ROLE,
        LegacyRoleCatalogue::TRAINING_OFFICE_ROLE,
    ];

    public function run(): void
    {
        $permission = Permission::findOrCreate(self::PERMISSION, 'web');

        foreach (Role::query()->whereIn('name', self::ROLES)->get() as $role) {
            $role->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
