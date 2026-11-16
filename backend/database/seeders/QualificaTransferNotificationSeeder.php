<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Services\RequestManagement\RequestTransferService;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Who receives the supervisory copy of a contact transfer (spec 0081 rev.
 * 2026-11-12, task 427848): the role RequestTransferService notifies, given to
 * the two commercial supervisors chosen by the client ON TOP of their own
 * roles. Everyone else keeps `{module}.viewAll` (it still decides what they
 * SEE) and simply stops receiving the copy, so no other permission changes.
 * The role carries the view permissions of both modules, so the notification
 * link opens. Adding or removing a recipient is then a role change on the
 * user record.
 *
 * `php artisan db:seed --class=QualificaTransferNotificationSeeder`. A re-run
 * converges (spatie never assigns a role or a permission twice).
 */
class QualificaTransferNotificationSeeder extends Seeder
{
    public const string DESCRIPTION = 'Notifiche trasferimenti contatti';

    /** @var array<int, string> */
    public const array PERMISSIONS = [
        'request-management.viewAny', 'request-management.view',
        'enrollee-management.viewAny', 'enrollee-management.view',
    ];

    /** @var array<int, string> */
    public const array RECIPIENT_EMAILS = [
        'rosa.falzarano@qualificagroup.com',
        'fabrizio.aliberti@qualificagroup.com',
    ];

    public function run(): void
    {
        // Step 1: the role, with the permissions the notification link needs.
        $role = Role::query()->firstOrCreate(
            ['name' => RequestTransferService::NOTIFIED_ROLE, 'guard_name' => 'web'],
            ['description' => self::DESCRIPTION],
        );
        $role->givePermissionTo(self::PERMISSIONS);

        // Step 2: added to each recipient that has an account.
        foreach (self::RECIPIENT_EMAILS as $email) {
            $user = User::query()->where('email', $email)->first();

            if ($user === null) {
                $this->command?->warn(sprintf('%s: no account, transfer notification role skipped.', $email));

                continue;
            }

            $user->assignRole($role);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
