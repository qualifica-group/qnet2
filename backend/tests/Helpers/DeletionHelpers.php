<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use App\Services\RoleAssignmentGuard;

if (! function_exists('deletionSuperAdmin')) {
    /** A super-admin (Gate::before bypass) so the delete tests exercise the in-use guard, not the Policy. */
    function deletionSuperAdmin(): User
    {
        Role::findOrCreate(RoleAssignmentGuard::PRIVILEGED_ROLE);
        $user = User::factory()->create();
        $user->assignRole(RoleAssignmentGuard::PRIVILEGED_ROLE);

        return $user;
    }
}
