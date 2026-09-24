<?php

use App\Enums\TaskStatusSystemKey;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Support\BadgeTokens;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Database\Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Schema of the five Task configurators (spec 0101, D-4/D-5)
|--------------------------------------------------------------------------
|
| The four non-status lookups are IDENTICAL by decision, so the shape
| assertions are written once and datasetted over the four tables rather
| than copy-pasted four times. `task_statuses` gets its own block: it is the
| same shape PLUS `system_key` and `completion_percentage`, and it is the
| only one carrying rows created by the migration itself.
*/

/**
 * The four pure lookups (D-4): table name => migration file.
 *
 * @return array<string, array{0: string, 1: string}>
 */
dataset('pureTaskLookups', [
    'task_types' => ['task_types', '2026_09_04_100000_create_task_types_table.php'],
    'task_categories' => ['task_categories', '2026_09_04_100100_create_task_categories_table.php'],
    'task_priorities' => ['task_priorities', '2026_09_04_100200_create_task_priorities_table.php'],
    'task_importances' => ['task_importances', '2026_09_04_100300_create_task_importances_table.php'],
]);

// ---------------------------------------------------------------------------
// AC-001 — the five configuration tables and their shape
// ---------------------------------------------------------------------------

it('AC-001: each pure lookup has the declared columns, name unique, color NOT NULL, icon nullable', function (string $table) {
    foreach (['id', 'name', 'description', 'color', 'icon', 'sort_order', 'is_active', 'created_at', 'updated_at'] as $column) {
        expect(Schema::hasColumn($table, $column))->toBeTrue("{$table} is missing column {$column}");
    }

    // D-4: no system_key, no numeric weight on the four pure lookups.
    expect(Schema::hasColumn($table, 'system_key'))->toBeFalse("{$table} must not carry system_key")
        ->and(Schema::hasColumn($table, 'completion_percentage'))->toBeFalse("{$table} must not carry completion_percentage")
        ->and(Schema::hasColumn($table, 'weight'))->toBeFalse("{$table} must not carry a numeric weight");

    DB::table($table)->insert(['name' => 'Primo', 'color' => 'blue', 'created_at' => now(), 'updated_at' => now()]);

    // name is UNIQUE (task_categories excepted — spec 0154, D-1 turns this
    // into a composite (parent_id, name) index, asserted separately below,
    // since a plain `name` duplicate with the SAME NULL parent_id is not
    // rejected by this bare unique-on-name assertion)...
    if ($table !== 'task_categories') {
        expect(fn () => DB::table($table)->insert(['name' => 'Primo', 'color' => 'red', 'created_at' => now(), 'updated_at' => now()]))
            ->toThrow(QueryException::class);
    }

    // ...color is NOT NULL...
    expect(fn () => DB::table($table)->insert(['name' => 'Secondo', 'color' => null, 'created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class);

    // ...and icon/description are nullable, with sort_order/is_active defaulted.
    $row = DB::table($table)->where('name', 'Primo')->first();
    expect($row->icon)->toBeNull()
        ->and($row->description)->toBeNull()
        ->and((int) $row->sort_order)->toBe(0)
        ->and((bool) $row->is_active)->toBeTrue();
})->with('pureTaskLookups');

// ---------------------------------------------------------------------------
// AC-001 (D-1) — task_categories' own `parent_id` + composite unique index
// ---------------------------------------------------------------------------

it('AC-001 (D-1): task_categories has parent_id, restrictOnDelete, and name unique PER PARENT rather than globally', function (): void {
    expect(Schema::hasColumn('task_categories', 'parent_id'))->toBeTrue();

    $parent = DB::table('task_categories')->insertGetId(['name' => 'Padre', 'color' => 'blue', 'created_at' => now(), 'updated_at' => now()]);

    DB::table('task_categories')->insert(['name' => 'Figlia', 'parent_id' => $parent, 'color' => 'blue', 'created_at' => now(), 'updated_at' => now()]);

    // Two rows with the SAME non-null parent_id and the same name collide on
    // the composite (parent_id, name) index...
    expect(fn () => DB::table('task_categories')->insert(['name' => 'Figlia', 'parent_id' => $parent, 'color' => 'red', 'created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class);

    // ...but a unique index treats every NULL parent_id as DISTINCT (ANSI
    // SQL, not a SQLite/MySQL quirk): two ROOT rows sharing a name are NOT
    // rejected at the schema layer — root-level uniqueness is enforced by
    // StoreTaskCategoryRequest/UpdateTaskCategoryRequest instead (D-1), which
    // is exactly why the migration's own docblock calls this out.
    DB::table('task_categories')->insert(['name' => 'Radice', 'color' => 'blue', 'created_at' => now(), 'updated_at' => now()]);

    expect(fn () => DB::table('task_categories')->insert(['name' => 'Radice', 'color' => 'red', 'created_at' => now(), 'updated_at' => now()]))
        ->not->toThrow(QueryException::class);

    // A parent still parenting a row cannot be deleted at the schema layer
    // either (restrictOnDelete), defense in depth alongside the Service guard.
    expect(fn () => DB::table('task_categories')->where('id', $parent)->delete())
        ->toThrow(QueryException::class);
});

it('AC-001: down() drops the pure lookup, up() recreates it empty', function (string $table, string $migrationFile) {
    $migration = require database_path("migrations/{$migrationFile}");

    $migration->down();
    expect(Schema::hasTable($table))->toBeFalse();

    $migration->up();
    expect(Schema::hasTable($table))->toBeTrue()
        ->and(DB::table($table)->count())->toBe(0);
})->with('pureTaskLookups');

it('AC-001: task_statuses has the same shape plus system_key, group and completion_percentage', function () {
    foreach (['id', 'name', 'description', 'color', 'icon', 'sort_order', 'is_active', 'system_key', 'group', 'completion_percentage', 'created_at', 'updated_at'] as $column) {
        expect(Schema::hasColumn('task_statuses', $column))->toBeTrue("task_statuses is missing column {$column}");
    }

    // D-5 as rectified 2026-09-04: `system_key` marks the protected rows and
    // `group` carries the PHASE, the same two-column shape contract_statuses
    // has. `system_key` is UNIQUE and could not express a many-to-one phase.
    expect(Schema::hasColumn('task_statuses', 'group'))->toBeTrue('task_statuses must carry a group column');

    expect(fn () => DB::table('task_statuses')->insert(['name' => 'Aperto', 'color' => 'blue', 'created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class);

    expect(fn () => DB::table('task_statuses')->insert(['name' => 'Nuovo custom', 'color' => null, 'created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class);
});

it('AC-001: task_statuses.system_key is UNIQUE, so a second row cannot claim a system phase', function () {
    expect(fn () => DB::table('task_statuses')->insert([
        'name' => 'Secondo aperto', 'color' => 'blue', 'sort_order' => 99, 'is_active' => true,
        'system_key' => TaskStatusSystemKey::Open->value, 'completion_percentage' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('AC-001: down() drops task_statuses, up() recreates it with its six bootstrap rows', function () {
    $migration = require database_path('migrations/2026_09_04_100400_create_task_statuses_table.php');

    $migration->down();
    expect(Schema::hasTable('task_statuses'))->toBeFalse();

    $migration->up();
    expect(Schema::hasTable('task_statuses'))->toBeTrue()
        // up() is not "empty" here: its six bootstrap rows are part of it.
        // Three of them are retired by 2026_09_04_110000, which is NOT
        // re-run by this test — hence six and not three.
        ->and(DB::table('task_statuses')->count())->toBe(6);
});

// ---------------------------------------------------------------------------
// AC-002 — the protected rows, created by the migrations
//
// Six were created by 2026_09_04_100400 and three of them retired by
// 2026_09_04_110000: `pending`/`in_validation` were PHASES mislabelled as
// system keys and now live on as App\Enums\TaskStatusGroup values (user
// directive 2026-09-04). `in_progress` was retired alongside them for that
// same reason, then REINSTATED by 2026_09_11_100000 for a different one
// (spec 0116, D-4): it now designates the single row the Task reopening
// actions land on, not a phase — see TaskStatusSystemKey's docblock. What
// survives is the minimum the module needs: somewhere to open a Task,
// somewhere to resume it, and somewhere to close it on each outcome.
// ---------------------------------------------------------------------------

it('AC-002: the migrations left the four protected rows with the declared keys and percentages', function () {
    $expected = [
        'open' => 0,
        // Spec 0118 D-5 promoted "Assegnato" to a protected row: it is the
        // destination of the derived initial status (D-4) when the Task is
        // assigned to someone other than its creator/requester, so it must be
        // reachable by `system_key` and never by label.
        'assigned' => 10,
        'in_progress' => 50,
        'closed_positive' => 100,
        'closed_negative' => 0,
    ];

    $rows = TaskStatus::query()->whereNotNull('system_key')->get()->keyBy(fn (TaskStatus $status): string => $status->system_key->value);

    expect($rows->keys()->all())->toEqualCanonicalizing(array_keys($expected));

    foreach ($expected as $key => $percentage) {
        expect($rows[$key]->completion_percentage)->toBe($percentage, "wrong percentage for {$key}")
            ->and($rows[$key]->name)->not->toBeEmpty()
            ->and($rows[$key]->color)->toBeIn(BadgeTokens::colors())
            ->and($rows[$key]->is_active)->toBeTrue();
    }
});

it('AC-002: the persisted system keys are exactly the TaskStatusSystemKey cases, no more and no fewer', function () {
    $persisted = TaskStatus::query()->whereNotNull('system_key')->pluck('system_key')
        ->map(fn (TaskStatusSystemKey $key): string => $key->value)->all();

    expect($persisted)->toEqualCanonicalizing(array_column(TaskStatusSystemKey::cases(), 'value'))
        ->and($persisted)->toHaveCount(5);
});

it('AC-002: only closed_positive and closed_negative are closing phases', function () {
    $closing = TaskStatus::query()->whereNotNull('system_key')->get()
        ->filter(fn (TaskStatus $status): bool => $status->isClosing())
        ->map(fn (TaskStatus $status): string => $status->system_key->value)
        ->values()->all();

    expect($closing)->toEqualCanonicalizing(['closed_positive', 'closed_negative']);
});

// ---------------------------------------------------------------------------
// AC-005 — the clean seed is idempotent and never overwrites a module rename
// ---------------------------------------------------------------------------

/**
 * The clean seed (`DatabaseSeeder`), MINUS its `Artisan::call('locations:add')`
 * first line — that command replays a MySQL dump that does not parse on the
 * sqlite test connection, which is why every existing suite calls the clean
 * seeders one by one instead of `DatabaseSeeder` itself (see
 * tests/Feature/SeederFlowTest.php). Nothing here depends on geo data, so the
 * omission costs the assertion nothing.
 */
function seedCleanReferenceData(): void
{
    foreach (cleanSeedSeederClasses() as $seeder) {
        test()->seed($seeder);
    }
}

/**
 * The seeder classes `DatabaseSeeder::run()` actually calls, read off its own
 * source. Enumerating them by hand would quietly under-cover the criterion
 * the day a Task seeder joins the clean seed: read this way, a new
 * `$this->call()` is picked up automatically.
 *
 * @return array<int, class-string<Seeder>>
 */
function cleanSeedSeederClasses(): array
{
    $source = (string) file_get_contents((string) (new ReflectionClass(DatabaseSeeder::class))->getFileName());

    preg_match_all('/\$this->call\(([A-Za-z0-9_]+)::class\)/', $source, $matches);

    /** @var array<int, class-string<Seeder>> $classes */
    $classes = array_map(static fn (string $name): string => 'Database\\Seeders\\'.$name, $matches[1]);

    return $classes;
}

it('AC-005: the clean-seed scan resolves the real seeder classes, so the criterion is not vacuous', function () {
    $classes = cleanSeedSeederClasses();

    expect($classes)->not->toBeEmpty();

    foreach ($classes as $class) {
        expect(class_exists($class))->toBeTrue("DatabaseSeeder calls {$class}, which does not resolve");
    }
});

it('AC-005: re-running the clean seed neither duplicates the system rows nor undoes a rename', function () {
    $renamed = TaskStatus::query()->where('system_key', TaskStatusSystemKey::ClosedPositive->value)->firstOrFail();
    $renamed->update(['name' => 'Terminato']);

    seedCleanReferenceData();
    seedCleanReferenceData();

    expect(TaskStatus::query()->whereNotNull('system_key')->count())->toBe(5)
        ->and(TaskStatus::query()->whereKey($renamed->id)->value('name'))->toBe('Terminato');
});

it('AC-005: the clean seed leaves the four pure lookups untouched (they carry no reference rows)', function (string $table) {
    $before = DB::table($table)->count();

    seedCleanReferenceData();
    seedCleanReferenceData();

    expect(DB::table($table)->count())->toBe($before);
})->with('pureTaskLookups');

// ---------------------------------------------------------------------------
// AC-041 (schema half) — the FK itself refuses to drop a row still in use,
// independently of the Service guard: defense in depth (D-8).
// ---------------------------------------------------------------------------

it('AC-041: the restrictOnDelete FK blocks deleting a status still referenced by a task', function () {
    $status = TaskStatus::factory()->create();
    Task::factory()->inStatus($status)->create();

    expect(fn () => DB::table('task_statuses')->where('id', $status->id)->delete())
        ->toThrow(QueryException::class);
});
