<?php

use App\Models\User;
use App\Support\Notifications\AssignmentNotificationChannels;
use Spatie\Permission\Models\Permission;

if (! function_exists('grantAssignmentNotifications')) {
    /**
     * Spec 0420: gives the users every assignment-notification permission
     * (bell and email, every module), what a seeded role holds by default.
     */
    function grantAssignmentNotifications(User ...$users): void
    {
        $permissions = array_map(
            static fn (string $name): Permission => Permission::findOrCreate($name, 'web'),
            AssignmentNotificationChannels::permissions(),
        );

        foreach ($users as $user) {
            $user->givePermissionTo($permissions);
        }
    }
}
