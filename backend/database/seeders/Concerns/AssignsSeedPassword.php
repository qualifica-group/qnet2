<?php

namespace Database\Seeders\Concerns;

use App\Models\User;

/**
 * Writes the shared seed credential onto an account (spec 0177, user
 * directive 2026-09-29): wherever that known password is written, the account
 * is also flagged `must_set_password` so its owner must replace it at first
 * access, unless `seeding.force_password_change` is off (staging).
 * `must_set_password` is guarded: set as an attribute, never mass-assigned.
 */
trait AssignsSeedPassword
{
    protected function assignSeedPassword(User $user): void
    {
        $user->password = config('seeding.password');
        $user->must_set_password = (bool) config('seeding.force_password_change');
    }
}
