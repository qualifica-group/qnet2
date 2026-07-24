<?php

use App\Enums\MigrationStatus;
use App\Jobs\RunMigrationJob;
use App\Models\Attribute;
use App\Models\MigrationRun;
use App\Models\Role;
use App\Models\User;
use App\Services\MigrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// The shared helpers (fakeMigrationsBaseUrl/seedMigrationsConfig/
// migrationsSuperAdminActor/runMigrationJobFor) are defined once, guarded by
// function_exists, across the Migration feature suite (see CompaniesSourceImportTest).
if (! function_exists('fakeMigrationsBaseUrl')) {
    function fakeMigrationsBaseUrl(): string
    {
        return 'https://external-crm.test';
    }
}

if (! function_exists('seedMigrationsConfig')) {
    function seedMigrationsConfig(): void
    {
        config([
            'migrations.base_url' => fakeMigrationsBaseUrl(),
            'migrations.token' => null,
            'migrations.timeout' => 5,
            'migrations.retry_times' => 1,
            'migrations.retry_sleep_ms' => 1,
            'migrations.import_batch_size' => 100,
        ]);
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

// ---------------------------------------------------------------------------
// AttributesSource — catalogue entry: create + old_id (+ ENUM options)
// ---------------------------------------------------------------------------

it('creates attributes with their old_id', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/attributes*' => Http::response([
            'items' => [
                ['id' => 5, 'code' => 'weight', 'name' => 'Weight', 'type' => 'decimal'],
                ['id' => 6, 'code' => 'active', 'name' => 'Active', 'type' => 'boolean'],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'attributes']);

    runMigrationJobFor($run);

    $weight = Attribute::query()->where('old_id', 5)->first();
    expect($weight->code)->toBe('weight')
        ->and($weight->name)->toBe('Weight')
        ->and($weight->type)->toBe('decimal')
        ->and(Attribute::query()->where('old_id', 6)->value('code'))->toBe('active');

    $fresh = $run->fresh();
    expect($fresh->status)->toBe(MigrationStatus::Completed)
        ->and($fresh->created_rows)->toBe(2)
        ->and($fresh->report)->toBeNull();
});

it('imports an ENUM attribute with its options', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/attributes*' => Http::response([
            'items' => [
                [
                    'id' => 20,
                    'code' => 'size',
                    'name' => 'Size',
                    'type' => 'enum',
                    'options' => [
                        ['value' => 'S', 'label' => 'Small', 'sort_order' => 0],
                        ['value' => 'L', 'label' => 'Large', 'sort_order' => 1],
                    ],
                ],
            ],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'attributes']);

    runMigrationJobFor($run);

    $size = Attribute::query()->where('old_id', 20)->first();
    expect($size->type)->toBe('enum')
        ->and($size->options()->pluck('value')->all())->toBe(['S', 'L']);

    expect($run->fresh()->created_rows)->toBe(1);
});

it('carries color, icon and is_default on ENUM options', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/attributes*' => Http::response([
            'items' => [
                [
                    'id' => 21,
                    'code' => 'status_color',
                    'name' => 'Status color',
                    'type' => 'enum',
                    'options' => [
                        ['value' => 'open', 'label' => 'Open', 'color' => '#22c55e', 'icon' => 'circle', 'is_default' => true],
                        ['value' => 'closed', 'label' => 'Closed'],
                    ],
                ],
            ],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    runMigrationJobFor(MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'attributes']));

    $open = Attribute::query()->where('old_id', 21)->first()->options()->where('value', 'open')->first();
    expect($open->color)->toBe('#22c55e')
        ->and($open->icon)->toBe('circle')
        ->and((bool) $open->is_default)->toBeTrue();
});

it('isolates an ENUM row with duplicate option values', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/attributes*' => Http::response([
            'items' => [
                [
                    'id' => 22, 'code' => 'dup', 'name' => 'Dup', 'type' => 'enum',
                    'options' => [
                        ['value' => 'x', 'label' => 'X one'],
                        ['value' => 'x', 'label' => 'X two'],
                    ],
                ],
                ['id' => 23, 'code' => 'weight2', 'name' => 'Weight', 'type' => 'decimal'],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'attributes']);
    runMigrationJobFor($run);

    expect(Attribute::query()->where('code', 'dup')->exists())->toBeFalse()
        ->and(Attribute::query()->where('code', 'weight2')->exists())->toBeTrue()
        ->and($run->fresh()->failed_rows)->toBe(1);
});

it('forwards the per-type config blob', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/attributes*' => Http::response([
            'items' => [
                ['id' => 24, 'code' => 'ratio', 'name' => 'Ratio', 'type' => 'decimal', 'config' => ['min' => 0, 'max' => 100, 'decimals' => 2]],
            ],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    runMigrationJobFor(MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'attributes']));

    expect(Attribute::query()->where('old_id', 24)->value('config'))->toBe(['min' => 0, 'max' => 100, 'decimals' => 2]);
});

it('imports a RELATION attribute with its relation_target', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/attributes*' => Http::response([
            'items' => [
                [
                    'id' => 25, 'code' => 'supplier', 'name' => 'Supplier', 'type' => 'relation',
                    'relation_target' => ['entity_type' => 'referents', 'cardinality' => 'one', 'for_select_resource' => 'referents'],
                ],
            ],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    runMigrationJobFor(MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'attributes']));

    $supplier = Attribute::query()->where('old_id', 25)->first();
    expect($supplier->type)->toBe('relation')
        ->and($supplier->relation_target)->toBe(['entity_type' => 'referents', 'cardinality' => 'one', 'for_select_resource' => 'referents']);
});

it('isolates a RELATION row with an invalid entity_type', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/attributes*' => Http::response([
            'items' => [
                [
                    'id' => 26, 'code' => 'bad_rel', 'name' => 'Bad relation', 'type' => 'relation',
                    'relation_target' => ['entity_type' => 'not_an_entity', 'cardinality' => 'one', 'for_select_resource' => 'x'],
                ],
                ['id' => 27, 'code' => 'ok_text', 'name' => 'Ok text', 'type' => 'text'],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'attributes']);
    runMigrationJobFor($run);

    expect(Attribute::query()->where('code', 'bad_rel')->exists())->toBeFalse()
        ->and(Attribute::query()->where('code', 'ok_text')->exists())->toBeTrue()
        ->and($run->fresh()->failed_rows)->toBe(1);
});

it('exposes a sample response covering every attribute type with its extras', function () {
    seedMigrationsConfig();

    $sample = app(App\Migrations\Sources\AttributesSource::class)->sampleResponse();
    $types = array_column($sample['items'], 'type');

    expect($types)->toEqualCanonicalizing(app(App\CustomFields\FieldTypeRegistry::class)->all());

    $enum = collect($sample['items'])->firstWhere('type', 'enum');
    expect($enum['options'][0])->toHaveKeys(['value', 'label', 'color', 'icon', 'sort_order', 'is_default']);

    $relation = collect($sample['items'])->firstWhere('type', 'relation');
    expect($relation['relation_target'])->toBe(['entity_type' => 'referents', 'cardinality' => 'one', 'for_select_resource' => 'referents']);
});

it('re-importing the same attributes is idempotent (skip, no duplicate)', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/attributes*' => Http::response([
            'items' => [['id' => 9, 'code' => 'color', 'name' => 'Color', 'type' => 'text']],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    runMigrationJobFor(MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'attributes']));

    $secondRun = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'attributes']);
    runMigrationJobFor($secondRun);

    expect(Attribute::query()->where('code', 'color')->count())->toBe(1)
        ->and($secondRun->fresh()->skipped_rows)->toBe(1)
        ->and($secondRun->fresh()->created_rows)->toBe(0);
});

it('isolates a failed attribute row (missing code) without blocking the valid one', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/attributes*' => Http::response([
            'items' => [
                ['id' => 10, 'code' => '', 'name' => 'Broken', 'type' => 'text'],
                ['id' => 11, 'code' => 'material', 'name' => 'Material', 'type' => 'text'],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'attributes']);

    runMigrationJobFor($run);

    expect(Attribute::query()->where('code', 'material')->exists())->toBeTrue();

    $fresh = $run->fresh();
    expect($fresh->created_rows)->toBe(1)
        ->and($fresh->failed_rows)->toBe(1)
        ->and(collect($fresh->report)->firstWhere('level', 'error'))->not->toBeNull();
});

it('isolates a failed attribute row (unregistered type) without blocking the valid one', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/attributes*' => Http::response([
            'items' => [
                ['id' => 12, 'code' => 'legacy_flag', 'name' => 'Legacy flag', 'type' => 'NOT_A_TYPE'],
                ['id' => 13, 'code' => 'weight', 'name' => 'Weight', 'type' => 'decimal'],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'attributes']);

    runMigrationJobFor($run);

    expect(Attribute::query()->where('code', 'weight')->exists())->toBeTrue()
        ->and(Attribute::query()->where('code', 'legacy_flag')->exists())->toBeFalse();

    $fresh = $run->fresh();
    expect($fresh->created_rows)->toBe(1)
        ->and($fresh->failed_rows)->toBe(1);
});
