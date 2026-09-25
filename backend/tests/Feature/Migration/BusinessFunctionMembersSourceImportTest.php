<?php

use App\Enums\MigrationStatus;
use App\Jobs\RunMigrationJob;
use App\Models\BusinessFunction;
use App\Models\MigrationRun;
use App\Models\Role;
use App\Models\User;
use App\Services\MigrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

if (! function_exists('fakeMigrationsBaseUrl')) {
    function fakeMigrationsBaseUrl(): string
    {
        return 'https://external-crm.test';
    }
}

if (! function_exists('migrationsSuperAdminActor')) {
    function migrationsSuperAdminActor(): User
    {
        Role::query()->firstOrCreate(['name' => 'super-admin']);

        $actor = User::factory()->create();
        $actor->assignRole('super-admin');

        return $actor;
    }
}

if (! function_exists('runMigrationJobFor')) {
    function runMigrationJobFor(MigrationRun $run): void
    {
        (new RunMigrationJob($run->id))->handle(app(MigrationService::class));
    }
}

if (! function_exists('fakeBusinessFunctionMembers')) {
    /**
     * The association source re-reads the external `business-functions`
     * endpoint (operators via `user_ids`, responsible via `manager_id`).
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    function fakeBusinessFunctionMembers(array $items): void
    {
        Http::fake([
            fakeMigrationsBaseUrl().'/business-functions*' => Http::response([
                'items' => $items,
                'pagination' => ['total' => count($items)],
            ]),
        ]);
    }
}

if (! function_exists('runMembersFor')) {
    function runMembersFor(User $actor): MigrationRun
    {
        $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'business-function-members']);
        runMigrationJobFor($run);

        return $run->fresh();
    }
}

// ---------------------------------------------------------------------------
// BusinessFunctionMembersSource: from the FUNCTION side, attach operators
// (pivot) and set the responsible (manager_id), both remapped via old_id.
// ---------------------------------------------------------------------------

it('attaches operators and sets the responsible resolving both via old_id', function () {
    seedMigrationsConfig();
    $operatorOne = User::factory()->create(['old_id' => 501]);
    $operatorTwo = User::factory()->create(['old_id' => 502]);
    $manager = User::factory()->create(['old_id' => 900]);
    $function = BusinessFunction::factory()->create(['old_id' => 1, 'manager_id' => null]);

    fakeBusinessFunctionMembers([
        ['id' => 1, 'user_ids' => [501, 502], 'manager_id' => 900],
    ]);

    $fresh = runMembersFor(migrationsSuperAdminActor());

    $function->refresh();
    expect($function->users()->pluck('users.id')->sort()->values()->all())
        ->toBe(collect([$operatorOne->id, $operatorTwo->id])->sort()->values()->all())
        ->and($function->manager_id)->toBe($manager->id);

    expect($fresh->status)->toBe(MigrationStatus::Completed)
        ->and($fresh->created_rows)->toBe(1)
        ->and($fresh->skipped_rows)->toBe(0);
});

it('applies the resolved links and warns on each unresolved reference', function () {
    seedMigrationsConfig();
    $operator = User::factory()->create(['old_id' => 501]);
    $function = BusinessFunction::factory()->create(['old_id' => 1, 'manager_id' => null]);

    fakeBusinessFunctionMembers([
        ['id' => 1, 'user_ids' => [501, 999], 'manager_id' => 404],
    ]);

    $fresh = runMembersFor(migrationsSuperAdminActor());

    $function->refresh();
    expect($function->users()->pluck('users.id')->all())->toBe([$operator->id])
        ->and($function->manager_id)->toBeNull();

    $messages = collect($fresh->report)->pluck('message')->implode(' | ');
    expect($fresh->created_rows)->toBe(1)
        ->and($messages)->toContain('999')
        ->and($messages)->toContain('404');
});

it('re-importing the same members is idempotent (skip, no duplicate, no change)', function () {
    seedMigrationsConfig();
    $operator = User::factory()->create(['old_id' => 501]);
    $manager = User::factory()->create(['old_id' => 900]);
    $function = BusinessFunction::factory()->create(['old_id' => 1, 'manager_id' => null]);

    fakeBusinessFunctionMembers([
        ['id' => 1, 'user_ids' => [501], 'manager_id' => 900],
    ]);

    $actor = migrationsSuperAdminActor();
    runMembersFor($actor);
    $second = runMembersFor($actor);

    $function->refresh();
    expect($function->users()->count())->toBe(1)
        ->and($function->manager_id)->toBe($manager->id);

    expect($second->skipped_rows)->toBe(1)
        ->and($second->created_rows)->toBe(0);
});

it('skips with a warning when the business function itself is not migrated', function () {
    seedMigrationsConfig();
    User::factory()->create(['old_id' => 501]);

    fakeBusinessFunctionMembers([
        ['id' => 77, 'user_ids' => [501]],
    ]);

    $fresh = runMembersFor(migrationsSuperAdminActor());

    expect($fresh->created_rows)->toBe(0)
        ->and($fresh->skipped_rows)->toBe(1)
        ->and($fresh->report[0]['level'])->toBe('warning')
        ->and($fresh->report[0]['message'])->toContain('77');
});
