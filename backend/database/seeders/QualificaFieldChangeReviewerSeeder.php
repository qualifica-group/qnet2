<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Services\FieldChangeRequests\FieldChangeRequestCreator;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Who hears about a new field change request (spec 0078, D-8, user directive
 * 2026-11-11): the role FieldChangeRequestCreator notifies, given to the two
 * commercial supervisors chosen by the client ON TOP of their own roles. The
 * role also lets its holder open the request list, so the notification link
 * always works. Adding or removing a reviewer is then a role change on the
 * user record.
 *
 * `php artisan db:seed --class=QualificaFieldChangeReviewerSeeder`. A re-run
 * converges (spatie never assigns a role or a permission twice).
 */
class QualificaFieldChangeReviewerSeeder extends Seeder
{
    public const string DESCRIPTION = 'Notifiche richieste di modifica';

    /** @var array<int, string> */
    public const array PERMISSIONS = ['field-change-requests.viewAny', 'field-change-requests.view'];

    /** @var array<int, string> */
    public const array REVIEWER_EMAILS = [
        'rosa.falzarano@qualificagroup.com',
        'fabrizio.aliberti@qualificagroup.com',
    ];

    public function run(): void
    {
        // Step 1: the role, with the permissions the notification link needs.
        $role = Role::query()->firstOrCreate(
            ['name' => FieldChangeRequestCreator::NOTIFIED_ROLE, 'guard_name' => 'web'],
            ['description' => self::DESCRIPTION],
        );
        $role->givePermissionTo(self::PERMISSIONS);

        // Step 2: added to each reviewer that has an account.
        foreach (self::REVIEWER_EMAILS as $email) {
            $user = User::query()->where('email', $email)->first();

            if ($user === null) {
                $this->command?->warn(sprintf('%s: no account, field change reviewer role skipped.', $email));

                continue;
            }

            $user->assignRole($role);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
