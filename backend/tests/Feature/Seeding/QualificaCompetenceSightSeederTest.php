<?php

use App\Models\Role;
use Database\Seeders\QualificaLegacyRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Spec 0340, user directive 2026-11-02: every seeded role that sees all the
// records of opportunities, quotes, contracts or work orders also gets that
// module's viewCompetence. QualificaLegacyRoleSeeder runs QualificaRoleSeeder
// first, so this covers every role the production and role-reset seeds write
// (both go through SyncsRoleMatrix::syncRole()).

uses(RefreshDatabase::class);

it('grants viewCompetence to every seeded role holding viewAll on the four modules, and never to a role without viewAll', function (): void {
    $this->seed(QualificaLegacyRoleSeeder::class);

    $granted = 0;

    foreach (Role::query()->where('name', '!=', 'super-admin')->with('permissions')->get() as $role) {
        $names = $role->permissions->pluck('name');

        foreach (['opportunities', 'quotes', 'contracts', 'work-orders'] as $resource) {
            $seesAll = $names->contains("{$resource}.viewAll");

            expect($names->contains("{$resource}.viewCompetence"))->toBe($seesAll, "{$role->name} {$resource}");
            $granted += $seesAll ? 1 : 0;
        }

        // User directive 2026-11-02: a role that sees only its own records (or its Sede's) never
        // gains the competence tier, on any of the six modules.
        foreach (['request-management', 'enrollee-management'] as $resource) {
            if ($names->contains("{$resource}.viewCompetence")) {
                expect($names->contains("{$resource}.viewAll"))->toBeTrue("{$role->name} {$resource}");
            }
        }
    }

    expect($granted)->toBeGreaterThan(0);
});
