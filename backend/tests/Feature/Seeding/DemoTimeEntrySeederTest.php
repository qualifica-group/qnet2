<?php

use App\Models\TimeEntry;
use App\Models\TimeEntryDayNote;
use App\Models\User;
use Database\Seeders\DemoTimeEntrySeeder;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\ProductTypologySeeder;
use Database\Seeders\QualificaTaskTaxonomySeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\UnitOfMeasureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| DemoTimeEntrySeeder / DatabaseSeeder boundary (spec 0122, AC-028)
|--------------------------------------------------------------------------
*/

/**
 * The demo account plus a handful of seeded (@example.test) users the seeder
 * picks up, and the task_types vocabulary it classifies against.
 */
function seedTimeEntryDependencies(): void
{
    test()->seed(RolePermissionSeeder::class);
    test()->seed(QualificaTaskTaxonomySeeder::class);

    User::factory()->create(['email' => 'demo@app.com']);
    User::factory()->count(4)->sequence(
        fn ($sequence) => ['email' => "seeded.user.{$sequence->index}@example.test"],
    )->create();
}

it('AC-028: DemoTimeEntrySeeder creates segnatempo for the demo and seeded users', function (): void {
    seedTimeEntryDependencies();

    test()->seed(DemoTimeEntrySeeder::class);

    expect(TimeEntry::count())->toBeGreaterThan(0);

    $demoUser = User::query()->where('email', 'demo@app.com')->firstOrFail();
    expect(TimeEntry::query()->where('user_id', $demoUser->id)->exists())->toBeTrue();

    $userIds = TimeEntry::query()->distinct()->pluck('user_id');
    expect($userIds->count())->toBeGreaterThan(1);
});

it('AC-028: re-running DemoTimeEntrySeeder does not duplicate rows', function (): void {
    seedTimeEntryDependencies();

    test()->seed(DemoTimeEntrySeeder::class);
    $firstRunCount = TimeEntry::count();
    $firstRunNotes = TimeEntryDayNote::count();

    test()->seed(DemoTimeEntrySeeder::class);

    expect(TimeEntry::count())->toBe($firstRunCount)
        ->and(TimeEntryDayNote::count())->toBe($firstRunNotes);
});

it('AC-028: DatabaseSeeder alone creates no time entries', function (): void {
    // Mirrors DatabaseSeeder::run() minus `locations:add` (an unrelated geo
    // import, not exercisable against the in-memory sqlite test connection):
    // the clean seed path — role/permission catalogue, reference data, the
    // single demo account — never calls DemoTimeEntrySeeder.
    test()->seed(RolePermissionSeeder::class);
    test()->seed(UnitOfMeasureSeeder::class);
    test()->seed(ProductTypologySeeder::class);
    test()->seed(DemoUserSeeder::class);

    expect(TimeEntry::count())->toBe(0)
        ->and(TimeEntryDayNote::count())->toBe(0);
});
