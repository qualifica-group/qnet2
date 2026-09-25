<?php

use App\Jobs\RunMigrationJob;
use App\Models\Company;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
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

// ---------------------------------------------------------------------------
// Spec 0021/0013 — a source generically persists an entity_type's active
// custom-field values onto the row it just created, straight off the
// external record, no per-source wiring (CompaniesSource / "companies").
// ---------------------------------------------------------------------------

it('writes the imported row\'s custom field values into custom_field_values', function () {
    seedMigrationsConfig();

    CustomFieldDefinition::factory()->forEntity('companies')->ofType('integer')->create([
        'key' => 'employee_count', 'label' => 'Employee count', 'sort_order' => 1,
    ]);
    CustomFieldDefinition::factory()->forEntity('companies')->ofType('text')->create([
        'key' => 'internal_code', 'label' => 'Internal code', 'sort_order' => 2,
    ]);

    Http::fake([
        fakeMigrationsBaseUrl().'/companies*' => Http::response([
            'items' => [[
                'id' => 30,
                'denomination' => 'Custom Fields Srl',
                'employee_count' => 42,
                'internal_code' => 'ACM-01',
            ]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'companies']);

    runMigrationJobFor($run);

    $company = Company::query()->where('old_id', 30)->firstOrFail();

    $stored = CustomFieldValue::query()
        ->where('entity_type', 'companies')
        ->where('entity_id', $company->id)
        ->first();

    expect($stored)->not->toBeNull()
        ->and($stored->values)->toBe(['employee_count' => 42, 'internal_code' => 'ACM-01'])
        ->and($company->custom_fields)->toBe(['employee_count' => 42, 'internal_code' => 'ACM-01']);
});

it('does not write a custom_field_values row when the entity_type has no active custom fields', function () {
    seedMigrationsConfig();

    Http::fake([
        fakeMigrationsBaseUrl().'/companies*' => Http::response([
            'items' => [['id' => 31, 'denomination' => 'No Custom Fields Ltd']],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'companies']);

    runMigrationJobFor($run);

    $company = Company::query()->where('old_id', 31)->firstOrFail();

    expect(CustomFieldValue::query()->where('entity_type', 'companies')->where('entity_id', $company->id)->exists())
        ->toBeFalse();
});

it('does not rewrite custom field values on a deduped (skipped) re-import', function () {
    seedMigrationsConfig();

    CustomFieldDefinition::factory()->forEntity('companies')->ofType('text')->create([
        'key' => 'internal_code', 'label' => 'Internal code', 'sort_order' => 1,
    ]);

    Http::fake([
        fakeMigrationsBaseUrl().'/companies*' => Http::response([
            'items' => [['id' => 32, 'denomination' => 'Reimported Srl', 'internal_code' => 'FIRST']],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $firstRun = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'companies']);
    runMigrationJobFor($firstRun);

    $company = Company::query()->where('old_id', 32)->firstOrFail();

    Http::fake([
        fakeMigrationsBaseUrl().'/companies*' => Http::response([
            'items' => [['id' => 32, 'denomination' => 'Reimported Srl', 'internal_code' => 'SECOND']],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $secondRun = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'companies']);
    runMigrationJobFor($secondRun);

    $stored = CustomFieldValue::query()
        ->where('entity_type', 'companies')
        ->where('entity_id', $company->id)
        ->firstOrFail();

    expect($stored->values)->toBe(['internal_code' => 'FIRST']);
});
