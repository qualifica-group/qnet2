<?php

namespace Database\Seeders;

use App\Enums\LocaleEnum;
use App\Enums\PersonalDataTypeEnum;
use App\Models\User;
use App\Services\RoleAssignmentGuard;
use Database\Seeders\Concerns\SeedsDevelopmentUsers;
use Illuminate\Database\Seeder;

/**
 * Seed the single privileged demo account. This is the only user created by the
 * default database seed; every other record belongs to DemoDataSeeder.
 * Idempotent: the account is upserted by email.
 *
 * The user form requires an identity card (first and last name), so the
 * account gets its own here; DemoPersonalDataSeeder later enriches the same
 * card instead of creating a second one.
 */
class DemoUserSeeder extends Seeder
{
    use SeedsDevelopmentUsers;

    public function run(): void
    {
        $user = User::firstOrNew(['email' => self::DEMO_EMAIL]);
        $user->name = 'Demo User';
        $user->locale = LocaleEnum::It->value;
        $user->email_verified_at ??= now();

        if (! $user->exists) {
            $user->password = config('seeding.password');
        }

        $user->save();
        $user->personalData()->firstOrCreate([], [
            'type' => PersonalDataTypeEnum::Individual->value,
            'first_name' => 'Demo',
            'last_name' => 'User',
        ]);
        $user->syncRoles([RoleAssignmentGuard::PRIVILEGED_ROLE]);
    }
}
