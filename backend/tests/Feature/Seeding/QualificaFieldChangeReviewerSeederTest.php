<?php

use App\Models\Role;
use App\Models\User;
use App\Services\FieldChangeRequests\FieldChangeRequestCreator;
use Database\Seeders\QualificaFieldChangeReviewerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (QualificaFieldChangeReviewerSeeder::PERMISSIONS as $permission) {
        Permission::findOrCreate($permission);
    }
});

it('0078 D-8: gives the reviewer role to the chosen supervisors only, on top of their roles', function () {
    $supervisorRole = Role::findOrCreate('supervisore-commerciale', 'web');
    $reviewers = collect(QualificaFieldChangeReviewerSeeder::REVIEWER_EMAILS)
        ->map(fn (string $email): User => User::factory()->create(['email' => $email])->assignRole($supervisorRole));
    $other = User::factory()->create()->assignRole($supervisorRole);

    $this->seed(QualificaFieldChangeReviewerSeeder::class);

    $role = Role::findByName(FieldChangeRequestCreator::NOTIFIED_ROLE);
    expect($role->description)->toBe(QualificaFieldChangeReviewerSeeder::DESCRIPTION)
        ->and($role->permissions->pluck('name')->sort()->values()->all())
        ->toBe(collect(QualificaFieldChangeReviewerSeeder::PERMISSIONS)->sort()->values()->all())
        ->and(User::role(FieldChangeRequestCreator::NOTIFIED_ROLE)->pluck('id')->sort()->values()->all())
        ->toBe($reviewers->pluck('id')->sort()->values()->all())
        ->and($other->fresh()->hasRole(FieldChangeRequestCreator::NOTIFIED_ROLE))->toBeFalse();

    $reviewers->each(fn (User $user) => expect($user->fresh()->hasRole('supervisore-commerciale'))->toBeTrue());
});

it('0078 D-8: a re-run converges and a missing account is skipped', function () {
    User::factory()->create(['email' => QualificaFieldChangeReviewerSeeder::REVIEWER_EMAILS[0]]);

    $this->seed(QualificaFieldChangeReviewerSeeder::class);
    $this->seed(QualificaFieldChangeReviewerSeeder::class);

    expect(Role::query()->where('name', FieldChangeRequestCreator::NOTIFIED_ROLE)->count())->toBe(1)
        ->and(User::role(FieldChangeRequestCreator::NOTIFIED_ROLE)->count())->toBe(1);
});
