<?php

use App\Models\Role;
use Database\Seeders\QualificaCatalog\LegacyRoleCatalogue as Legacy;
use Database\Seeders\QualificaCatalog\OperatorRoleCatalogue;
use Database\Seeders\QualificaLegacyRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Spec 0340 rev.2/rev.3, user directives 2026-11-02. QualificaLegacyRoleSeeder
// runs QualificaRoleSeeder first, so this covers every role the production and
// role-reset seeds write (both go through SyncsRoleMatrix::syncRole()).

uses(RefreshDatabase::class);

it('grants viewCompetence to every seeded role holding viewAll on the four modules', function (): void {
    $this->seed(QualificaLegacyRoleSeeder::class);

    $granted = 0;

    foreach (Role::query()->where('name', '!=', 'super-admin')->with('permissions')->get() as $role) {
        $names = $role->permissions->pluck('name');

        foreach (['opportunities', 'quotes', 'contracts', 'work-orders'] as $resource) {
            if ($names->contains("{$resource}.viewAll")) {
                expect($names->contains("{$resource}.viewCompetence"))->toBeTrue("{$role->name} {$resource}");
                $granted++;
            }
        }
    }

    expect($granted)->toBeGreaterThan(0);
});

it('scopes the operations role to its competence instead of every record (rev.3)', function (): void {
    $this->seed(QualificaLegacyRoleSeeder::class);

    $names = Role::findByName(Legacy::OPERATIONS_ROLE)->permissions->pluck('name');

    foreach ([...['opportunities', 'quotes', 'contracts', 'work-orders'], 'request-management', 'enrollee-management'] as $resource) {
        expect($names->contains("{$resource}.viewAll"))->toBeFalse($resource)
            ->and($names->contains("{$resource}.viewCompetence"))->toBeTrue($resource);
    }

    // Still writes what it wrote before: only the visibility changed.
    expect($names)->toContain('opportunities.update', 'quotes.update', 'work-orders.update', 'contracts.update');
});

it('never gives the competence tier to a role that sees only its own records or its Sede\'s', function (): void {
    $this->seed(QualificaLegacyRoleSeeder::class);

    foreach ([OperatorRoleCatalogue::COMMERCIAL_ROLE, OperatorRoleCatalogue::TEACHING_SUPERVISOR_ROLE] as $name) {
        $names = Role::findByName($name)->permissions->pluck('name');

        foreach ([...['opportunities', 'quotes', 'contracts', 'work-orders'], 'request-management', 'enrollee-management'] as $resource) {
            expect($names->contains("{$resource}.viewCompetence"))->toBeFalse("{$name} {$resource}");
        }
    }
});
