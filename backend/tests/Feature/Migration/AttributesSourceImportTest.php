<?php

use App\CustomFields\FieldTypeRegistry;
use App\Enums\MigrationStatus;
use App\Jobs\RunMigrationJob;
use App\Migrations\Sources\AttributesSource;
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

it('imports a legacy `multiple` ENUM as a multiselect', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/attributes*' => Http::response([
            'items' => [
                ['id' => 25, 'code' => 'categorie_mepa', 'name' => 'Categorie ME.PA.', 'type' => 'enum',
                    'config' => ['display' => 'select', 'multiple' => true],
                    'options' => [['value' => '1', 'label' => 'Carta', 'sort_order' => 0]]],
            ],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    runMigrationJobFor(MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'attributes']));

    expect(Attribute::query()->where('old_id', 25)->value('config'))->toBe(['display' => 'multiselect', 'multiple' => true]);
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

    $sample = app(AttributesSource::class)->sampleResponse();
    $types = array_column($sample['items'], 'type');

    expect($types)->toEqualCanonicalizing(app(FieldTypeRegistry::class)->all());

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

// ---------------------------------------------------------------------------
// Spec 0181 — table config validation, adoption by code, entity_type normalization
// ---------------------------------------------------------------------------

it('AC-004: imports a table attribute with its config and rejects an invalid one', function () {
    seedMigrationsConfig();
    $config = [
        'columns' => [
            ['key' => 'data_verifica', 'label' => 'Data Verifica I.', 'type' => 'date', 'required' => true],
            ['key' => 'invio_alert', 'label' => 'Avviso', 'type' => 'boolean'],
        ],
        'selectable' => ['key' => 'attivo', 'label' => 'Attivo'],
        'summary' => ['column' => 'data_verifica', 'strategy' => 'selected'],
    ];
    Http::fake([
        fakeMigrationsBaseUrl().'/attributes*' => Http::response([
            'items' => [
                ['id' => 30, 'code' => 'orderiso_verificas', 'name' => 'Verifiche Ispettive', 'type' => 'table', 'config' => $config],
                ['id' => 31, 'code' => 'bad_table', 'name' => 'Bad table', 'type' => 'table', 'config' => ['columns' => []]],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $run = MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'attributes']);
    runMigrationJobFor($run);

    expect(Attribute::query()->where('old_id', 30)->first()->config)->toBe($config)
        ->and(Attribute::query()->where('code', 'bad_table')->exists())->toBeFalse()
        ->and($run->fresh()->created_rows)->toBe(1)
        ->and($run->fresh()->failed_rows)->toBe(1)
        ->and(json_encode($run->fresh()->report))->toContain('Invalid table config');
});

it('AC-005: adopts an existing attribute by code without touching its name or options', function () {
    seedMigrationsConfig();
    $existing = Attribute::factory()->create(['old_id' => null, 'code' => 'stato_pagamento', 'name' => 'Nome locale', 'type' => 'text']);
    Http::fake([
        fakeMigrationsBaseUrl().'/attributes*' => Http::response([
            'items' => [['id' => 40, 'code' => 'stato_pagamento', 'name' => 'Nome legacy', 'type' => 'text']],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $run = MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'attributes']);
    runMigrationJobFor($run);

    $existing->refresh();
    expect($existing->old_id)->toEqual(40)
        ->and($existing->name)->toBe('Nome locale')
        ->and(Attribute::query()->where('code', 'stato_pagamento')->count())->toBe(1)
        ->and($run->fresh()->failed_rows)->toBe(0)
        ->and($run->fresh()->created_rows)->toBe(1);
});

it('AC-006: normalizes a legacy underscore relation entity_type to hyphens', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/attributes*' => Http::response([
            'items' => [[
                'id' => 50, 'code' => 'site', 'name' => 'Site', 'type' => 'relation',
                'relation_target' => ['entity_type' => 'operational_sites', 'cardinality' => 'one', 'for_select_resource' => 'operational-sites'],
            ]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $run = MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'attributes']);
    runMigrationJobFor($run);

    expect(Attribute::query()->where('old_id', 50)->first()->relation_target['entity_type'])->toBe('operational-sites')
        ->and($run->fresh()->failed_rows)->toBe(0);
});

it('spec 0182 AC-004: imports registries/users relations, scalar types, 0/1 enums and PAL-like tables', function () {
    seedMigrationsConfig();
    $palConfig = [
        'columns' => [
            ['key' => 'cognome', 'label' => 'Cognome', 'type' => 'text', 'required' => true],
            ['key' => 'data_avv_procedure', 'label' => 'Data Avv. Procedure', 'type' => 'date'],
            ['key' => 'orientamento', 'label' => 'Orientamento', 'type' => 'boolean'],
            ['key' => 'numero_ore', 'label' => 'N. Ore', 'type' => 'integer'],
            ['key' => 'stato_lavorazione', 'label' => 'Stato', 'type' => 'enum', 'options' => [
                ['value' => 'In avvio', 'label' => 'In avvio'],
                ['value' => 'Terminata', 'label' => 'Terminata'],
            ]],
            ['key' => 'note', 'label' => 'Note', 'type' => 'textarea'],
        ],
    ];
    $yesNo = [['value' => '1', 'label' => 'Si'], ['value' => '0', 'label' => 'No']];
    Http::fake([
        fakeMigrationsBaseUrl().'/attributes*' => Http::response([
            'items' => [
                ['id' => 100, 'code' => 'avv_company_id', 'name' => 'Azienda', 'type' => 'relation',
                    'relation_target' => ['entity_type' => 'registries', 'cardinality' => 'one', 'for_select_resource' => 'registries']],
                ['id' => 101, 'code' => 'avv_commerciale', 'name' => 'Commerciale', 'type' => 'relation',
                    'relation_target' => ['entity_type' => 'users', 'cardinality' => 'one', 'for_select_resource' => 'users']],
                ['id' => 102, 'code' => 'avv_ora_scadenza', 'name' => 'Ora Scadenza', 'type' => 'time'],
                ['id' => 103, 'code' => 'avv_importo_gara', 'name' => 'Gara', 'type' => 'decimal'],
                ['id' => 104, 'code' => 'gdpr_accessi_onsite', 'name' => 'Accessi', 'type' => 'integer'],
                ['id' => 105, 'code' => 'soa_invio_contratto', 'name' => 'Invio', 'type' => 'boolean'],
                ['id' => 106, 'code' => 'soa_appartenenza_consorzio', 'name' => 'Consorzio', 'type' => 'enum',
                    'config' => ['display' => 'radio'], 'options' => $yesNo],
                ['id' => 107, 'code' => 'orderiso_pals', 'name' => 'Info Lavorazione', 'type' => 'table', 'config' => $palConfig],
            ],
            'pagination' => ['total' => 8],
        ]),
    ]);

    $run = MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'attributes']);
    runMigrationJobFor($run);

    expect($run->fresh()->failed_rows)->toBe(0)
        ->and($run->fresh()->created_rows)->toBe(8)
        ->and(Attribute::query()->where('old_id', 100)->first()->relation_target['entity_type'])->toBe('registries')
        ->and(Attribute::query()->where('old_id', 101)->first()->relation_target['for_select_resource'])->toBe('users')
        ->and(Attribute::query()->where('old_id', 106)->first()->options->pluck('value')->all())->toBe(['1', '0']);
});

it('spec 0182 AC-004: imports an enum with 79 options and duplicated labels', function () {
    seedMigrationsConfig();
    $options = [];
    foreach (range(1, 79) as $id) {
        $options[] = ['value' => (string) $id, 'label' => in_array($id, [10, 11], true) ? 'Stessa descrizione' : "Stato {$id}", 'sort_order' => $id];
    }
    Http::fake([
        fakeMigrationsBaseUrl().'/attributes*' => Http::response([
            'items' => [['id' => 110, 'code' => 'stato_consulenza_68', 'name' => 'Stato Lavorazione', 'type' => 'enum',
                'config' => ['display' => 'select'], 'options' => $options]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $run = MigrationRun::factory()->create(['user_id' => migrationsSuperAdminActor()->id, 'source' => 'attributes']);
    runMigrationJobFor($run);

    expect($run->fresh()->failed_rows)->toBe(0)
        ->and(Attribute::query()->where('old_id', 110)->first()->options)->toHaveCount(79);
});
