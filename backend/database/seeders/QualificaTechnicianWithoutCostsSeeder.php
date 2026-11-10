<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\RoleFieldPermission;
use App\Models\User;
use Database\Seeders\QualificaCatalog\LegacyRoleCatalogue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Spec 0380 (user directives 2026-11-10): the listed technicians must not see
 * the costs nor the active/passive proforma of a commessa, but must edit its
 * payment status. Their permissions come from the `tecnico-consulenza` role,
 * shared with technicians who keep them, and spatie has no per-user deny: so
 * this seeder clones that role AS IT IS NOW (its permissions and field matrix)
 * into `tecnico-consulenza-senza-costi` without WITHHELD_PERMISSIONS and with
 * GRANTED_PERMISSIONS, and moves the listed people from one role to the other.
 *
 * `php artisan db:seed --class=QualificaTechnicianWithoutCostsSeeder`. Every
 * other role of those people is left alone; a re-run converges. One
 * transaction.
 */
class QualificaTechnicianWithoutCostsSeeder extends Seeder
{
    public const string ROLE = 'tecnico-consulenza-senza-costi';

    public const string DESCRIPTION = 'Tecnico consulenza (senza costi commessa)';

    /** @var array<int, string> */
    public const array WITHHELD_PERMISSIONS = ['work-orders.viewCosts', 'work-orders.viewInvoices'];

    /** @var array<int, string> */
    public const array GRANTED_PERMISSIONS = ['work-orders.managePayments'];

    /** @var array<int, string> */
    public const array USERS = [
        'maria.savarese@qualificagroup.com',
        'francesco.ienco@qualificagroup.com',
        'paola.sena@qualificagroup.com',
        'ilaria.vitolo@qualificagroup.com',
        'ivana.mango@qualificagroup.com',
        'noemi.nigliato@qualificagroup.it',
        'antoniosalvatore.peluso@qualificagroup.com',
        'antonio.demartino@qualificagroup.com',
    ];

    public function run(): void
    {
        $source = Role::query()->where('name', LegacyRoleCatalogue::TECHNICIAN_ROLE)->first();

        if ($source === null) {
            $this->command?->warn(sprintf('Role %s missing: nothing to do.', LegacyRoleCatalogue::TECHNICIAN_ROLE));

            return;
        }

        DB::transaction(function () use ($source): void {
            // Step 1: the twin role, the source's current permissions minus the withheld, plus the granted.
            $target = $this->syncTwinRole($source);

            // Step 2: the listed people move from the source role to the twin.
            $this->moveUsers($source, $target);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function syncTwinRole(Role $source): Role
    {
        $target = Role::query()->firstOrCreate(
            ['name' => self::ROLE, 'guard_name' => $source->guard_name],
            ['description' => self::DESCRIPTION],
        );

        $granted = collect(self::GRANTED_PERMISSIONS)->map(fn (string $name): Permission => Permission::findOrCreate($name, $source->guard_name));
        $target->syncPermissions($source->permissions
            ->reject(fn (Permission $permission): bool => in_array($permission->name, self::WITHHELD_PERMISSIONS, true))
            ->merge($granted));

        $target->fieldPermissions()->delete();
        $source->fieldPermissions->each(fn (RoleFieldPermission $row) => $target->fieldPermissions()->create(
            $row->only(['resource', 'field', 'visible', 'editable', 'required']),
        ));

        return $target;
    }

    private function moveUsers(Role $source, Role $target): void
    {
        foreach (self::USERS as $email) {
            $user = User::query()->where('email', $email)->first();

            if ($user === null) {
                $this->command?->warn(sprintf('%s: no account, skipped.', $email));

                continue;
            }

            $user->removeRole($source);
            $user->assignRole($target);
        }
    }
}
