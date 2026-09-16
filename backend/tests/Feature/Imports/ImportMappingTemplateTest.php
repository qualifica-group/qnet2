<?php

use App\Models\ImportMappingTemplate;
use App\Models\ImportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\Stubs\StubImportDefinition;

uses(RefreshDatabase::class);

if (! function_exists('stubImportActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     * @param  array<int, string>  $importRunAbilities  the `import-runs.*` MODULE
     *                                                  abilities (spec 0034), independent of the
     *                                                  domain `business-functions.*` ones above
     */
    function stubImportActorWith(array $abilities, array $importRunAbilities = []): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("business-functions.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("business-functions.{$ability}");
        }

        grantImportRunsPermissions($user, $importRunAbilities);

        return $user;
    }
}

if (! function_exists('registerStubImportDomain')) {
    function registerStubImportDomain(): void
    {
        config(['imports.definitions' => ['stub-widgets' => StubImportDefinition::class]]);
    }
}

/**
 * The stub domain's detected columns/mapping fixture shared by every test
 * below: 2 columns, deterministic ColumnAnalysis keys `Name`/`Type` (no
 * duplicates), mirroring leadsWizardDetectedColumns()'s shape.
 *
 * @return array<int, array{name: string, index: int, duplicate: bool}>
 */
function stubDetectedColumns(): array
{
    return [
        ['name' => 'Name', 'index' => 0, 'duplicate' => false],
        ['name' => 'Type', 'index' => 1, 'duplicate' => false],
    ];
}

/**
 * @return array<string, string>
 */
function stubColumnMapping(): array
{
    return ['Name' => 'name', 'Type' => 'type'];
}

// ---------------------------------------------------------------------------
// AC-001 / AC-004 — POST /api/imports/{domain}/mapping-templates
// ---------------------------------------------------------------------------

it('AC-001: 201 with a mapping_template snapshotting the run\'s columns/column_mapping/dedup_strategy', function () {
    registerStubImportDomain();
    $actor = stubImportActorWith(['import'], ['create']);
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'stub-widgets',
        'detected_columns' => stubDetectedColumns(),
        'column_mapping' => stubColumnMapping(),
        'dedup_strategy' => 'create_only',
    ]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/imports/stub-widgets/mapping-templates', [
        'name' => 'My template',
        'import_run_id' => $run->id,
    ])->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.mapping_template.name', 'My template')
        ->assertJsonPath('data.mapping_template.columns', ['Name', 'Type'])
        ->assertJsonPath('data.mapping_template.column_mapping', stubColumnMapping())
        ->assertJsonPath('data.mapping_template.dedup_strategy', 'create_only')
        ->assertJsonPath('data.mapping_template.created_by.id', $actor->id);

    expect(ImportMappingTemplate::query()->where('resource', 'stub-widgets')->count())->toBe(1);
});

it('AC-004: 422 when the run has no persisted column_mapping yet', function () {
    registerStubImportDomain();
    $actor = stubImportActorWith(['import'], ['create']);
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'stub-widgets',
        'detected_columns' => null,
        'column_mapping' => null,
    ]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/imports/stub-widgets/mapping-templates', [
        'name' => 'My template',
        'import_run_id' => $run->id,
    ])->assertStatus(422);
});

// ---------------------------------------------------------------------------
// AC-002 — duplicate name within the same domain
// ---------------------------------------------------------------------------

it('AC-002: 422 when a template with the same name already exists in the domain', function () {
    registerStubImportDomain();
    $actor = stubImportActorWith(['import'], ['create']);
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'stub-widgets',
        'detected_columns' => stubDetectedColumns(),
        'column_mapping' => stubColumnMapping(),
    ]);
    ImportMappingTemplate::factory()->create(['resource' => 'stub-widgets', 'name' => 'My template']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/imports/stub-widgets/mapping-templates', [
        'name' => 'My template',
        'import_run_id' => $run->id,
    ])->assertStatus(422)->assertJsonValidationErrors('name');
});

// ---------------------------------------------------------------------------
// AC-003 — the run must belong to the actor and match the route {domain}
// ---------------------------------------------------------------------------

it('AC-003: snapshots a run started by another user (runs are shared)', function () {
    registerStubImportDomain();
    $actor = stubImportActorWith(['import'], ['create']);
    $otherUser = User::factory()->create();
    $run = ImportRun::factory()->create([
        'user_id' => $otherUser->id,
        'resource' => 'stub-widgets',
        'detected_columns' => stubDetectedColumns(),
        'column_mapping' => stubColumnMapping(),
    ]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/imports/stub-widgets/mapping-templates', [
        'name' => 'My template',
        'import_run_id' => $run->id,
    ])->assertCreated();
});

it('AC-003: 404 (never 403) when import_run_id belongs to a different domain', function () {
    registerStubImportDomain();
    $actor = stubImportActorWith(['import'], ['create']);
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'some-other-domain',
        'detected_columns' => stubDetectedColumns(),
        'column_mapping' => stubColumnMapping(),
    ]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/imports/stub-widgets/mapping-templates', [
        'name' => 'My template',
        'import_run_id' => $run->id,
    ])->assertNotFound();
});

// ---------------------------------------------------------------------------
// AC-005 — GET /api/imports/{domain}/mapping-templates
// ---------------------------------------------------------------------------

it('AC-005: returns EVERY template of the domain regardless of creator, id desc', function () {
    registerStubImportDomain();
    $actor = stubImportActorWith(['import'], ['create']);
    $otherUser = User::factory()->create();
    $older = ImportMappingTemplate::factory()->create(['resource' => 'stub-widgets', 'user_id' => $otherUser->id, 'name' => 'Older']);
    $newer = ImportMappingTemplate::factory()->create(['resource' => 'stub-widgets', 'user_id' => $actor->id, 'name' => 'Newer']);
    ImportMappingTemplate::factory()->create(['resource' => 'some-other-domain', 'name' => 'Unrelated']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/imports/stub-widgets/mapping-templates')
        ->assertOk()
        ->assertJsonPath('data.mapping_templates.0.id', $newer->id)
        ->assertJsonPath('data.mapping_templates.1.id', $older->id)
        ->assertJsonCount(2, 'data.mapping_templates');
});

// ---------------------------------------------------------------------------
// AC-006 — matching_template on GET /api/imports/{domain}/{importRun}
// ---------------------------------------------------------------------------

it('AC-006: matching_template resolves the MOST RECENT template whose columns exactly match, in order', function () {
    registerStubImportDomain();
    $actor = stubImportActorWith(['import'], ['view']);
    $older = ImportMappingTemplate::factory()->create(['resource' => 'stub-widgets', 'columns' => ['Name', 'Type']]);
    $newer = ImportMappingTemplate::factory()->create(['resource' => 'stub-widgets', 'columns' => ['Name', 'Type']]);
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'stub-widgets',
        'detected_columns' => stubDetectedColumns(),
        'column_mapping' => stubColumnMapping(),
    ]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/imports/stub-widgets/{$run->id}")
        ->assertOk()
        ->assertJsonPath('data.import_run.matching_template.id', $newer->id)
        ->assertJsonMissingPath('data.import_run.matching_template.columns');

    expect($older->id)->toBeLessThan($newer->id);
});

it('AC-006: matching_template is null when columns match a different order, a superset or a subset', function () {
    registerStubImportDomain();
    $actor = stubImportActorWith(['import'], ['view']);
    ImportMappingTemplate::factory()->create(['resource' => 'stub-widgets', 'columns' => ['Type', 'Name']]);
    ImportMappingTemplate::factory()->create(['resource' => 'stub-widgets', 'columns' => ['Name', 'Type', 'Extra']]);
    ImportMappingTemplate::factory()->create(['resource' => 'stub-widgets', 'columns' => ['Name']]);
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'stub-widgets',
        'detected_columns' => stubDetectedColumns(),
        'column_mapping' => stubColumnMapping(),
    ]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/imports/stub-widgets/{$run->id}")
        ->assertOk()
        ->assertJsonPath('data.import_run.matching_template', null);
});

// ---------------------------------------------------------------------------
// AC-007 — DELETE /api/imports/{domain}/mapping-templates/{mappingTemplate}
// ---------------------------------------------------------------------------

it('AC-007: 403 when a non-creator, non-super-admin actor deletes another user\'s template', function () {
    registerStubImportDomain();
    $actor = stubImportActorWith(['import'], ['create']);
    $creator = User::factory()->create();
    $template = ImportMappingTemplate::factory()->create(['resource' => 'stub-widgets', 'user_id' => $creator->id]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/imports/stub-widgets/mapping-templates/{$template->id}")->assertForbidden();

    expect(ImportMappingTemplate::query()->find($template->id))->not->toBeNull();
});

it('AC-007: 200 and deleted when the creator deletes their own template', function () {
    registerStubImportDomain();
    $actor = stubImportActorWith(['import'], ['create']);
    $template = ImportMappingTemplate::factory()->create(['resource' => 'stub-widgets', 'user_id' => $actor->id]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/imports/stub-widgets/mapping-templates/{$template->id}")
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(ImportMappingTemplate::query()->find($template->id))->toBeNull();
});

it('AC-007: 404 when {domain} does not match the template\'s resource', function () {
    registerStubImportDomain();
    config(['imports.definitions' => ['stub-widgets' => StubImportDefinition::class, 'some-other-domain' => StubImportDefinition::class]]);
    $actor = stubImportActorWith(['import'], ['create']);
    $template = ImportMappingTemplate::factory()->create(['resource' => 'some-other-domain', 'user_id' => $actor->id]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/imports/stub-widgets/mapping-templates/{$template->id}")->assertNotFound();

    expect(ImportMappingTemplate::query()->find($template->id))->not->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-008 — double gate on GET/POST mapping-templates
// ---------------------------------------------------------------------------

it('AC-008: 403 on GET without import-runs.create', function () {
    registerStubImportDomain();
    $actor = stubImportActorWith(['import']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/imports/stub-widgets/mapping-templates')->assertForbidden();
});

it('AC-008: 403 on GET without the domain\'s {resource}.import ability', function () {
    registerStubImportDomain();
    $actor = stubImportActorWith([], ['create']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/imports/stub-widgets/mapping-templates')->assertForbidden();
});

it('AC-008: 403 on POST without import-runs.create', function () {
    registerStubImportDomain();
    $actor = stubImportActorWith(['import']);
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'stub-widgets',
        'detected_columns' => stubDetectedColumns(),
        'column_mapping' => stubColumnMapping(),
    ]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/imports/stub-widgets/mapping-templates', [
        'name' => 'My template',
        'import_run_id' => $run->id,
    ])->assertForbidden();
});

it('AC-008: 403 on POST without the domain\'s {resource}.import ability', function () {
    registerStubImportDomain();
    $actor = stubImportActorWith([], ['create']);
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'stub-widgets',
        'detected_columns' => stubDetectedColumns(),
        'column_mapping' => stubColumnMapping(),
    ]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/imports/stub-widgets/mapping-templates', [
        'name' => 'My template',
        'import_run_id' => $run->id,
    ])->assertForbidden();
});

it('404 for an unregistered domain', function () {
    registerStubImportDomain();
    $actor = stubImportActorWith(['import'], ['create']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/imports/unknown-domain/mapping-templates')->assertNotFound();
});
