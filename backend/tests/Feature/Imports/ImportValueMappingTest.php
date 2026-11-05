<?php

use App\Enums\ImportStatus;
use App\Imports\LeadsImportDefinition;
use App\Models\ImportMappingTemplate;
use App\Models\ImportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\Stubs\FakeValueMappingImportDefinition;

uses(RefreshDatabase::class);

const VM_CSV = "Nome,Stato,Fonte\n"
    ."Anna,Attesa_App APL,Web\n"
    ."Luca,attesa_app  apl ,web\n"
    ."Gino,OK App. Fissato CPI,Web\n"
    ."Pia,,Radio\n"
    ."Teo,open,Web\n";

function vmActor(string ...$permissions): User
{
    $user = User::factory()->create();

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission);
        $user->givePermissionTo($permission);
    }

    return $user;
}

function vmRun(array $overrides = []): ImportRun
{
    config(['imports.definitions' => ['request-management' => FakeValueMappingImportDefinition::class]]);
    Storage::fake('local');
    Storage::disk('local')->put('imports/vm.csv', VM_CSV);

    return ImportRun::factory()->create([
        'resource' => 'request-management',
        'status' => ImportStatus::Configuring,
        'stored_path' => 'imports/vm.csv',
        'detected_columns' => [
            ['name' => 'Nome', 'index' => 0, 'duplicate' => false],
            ['name' => 'Stato', 'index' => 1, 'duplicate' => false],
            ['name' => 'Fonte', 'index' => 2, 'duplicate' => false],
        ],
        ...$overrides,
    ]);
}

/**
 * @return array<string, mixed>
 */
function vmConfigureBody(array $valueMappings): array
{
    return [
        'column_mapping' => ['Nome' => 'first_name', 'Stato' => 'workflow_status', 'Fonte' => 'source_name'],
        'global_config' => ['product_category_id' => 7],
        'dedup_strategy' => 'create_new',
        'value_mappings' => $valueMappings,
    ];
}

/**
 * @return array<string, mixed>
 */
function vmFullDecisions(): array
{
    return [
        'workflow_status' => [
            'Attesa_App APL' => ['id' => 2],
            'OK App. Fissato CPI' => ['create' => ['name' => 'OK App. Fissato CPI', 'group' => 'pending']],
            '' => ['id' => 1],
            'open' => ['id' => 1],
        ],
        'source_name' => ['Web' => ['id' => 1], 'Radio' => ['id' => 2]],
    ];
}

// ---------------------------------------------------------------------------
// Run detail: the two new keys on every domain
// ---------------------------------------------------------------------------

it('exposes value_mapping_fields and attribute_targets for a domain that declares them', function () {
    $run = vmRun();
    Sanctum::actingAs(vmActor('request-management.import'));

    $this->getJson("/api/imports/request-management/{$run->id}")->assertOk()
        ->assertJsonPath('data.import_run.value_mapping_fields', ['workflow_status', 'source_name'])
        ->assertJsonPath('data.import_run.attribute_targets', ['context' => 'quote', 'depends_on' => 'product_category_id']);
});

it('AC-018: leads exposes no value mapping and no attribute targets', function () {
    $run = ImportRun::factory()->create(['resource' => 'leads', 'status' => ImportStatus::Configuring]);
    Sanctum::actingAs(vmActor('leads.import'));

    $this->getJson("/api/imports/leads/{$run->id}")->assertOk()
        ->assertJsonPath('data.import_run.value_mapping_fields', [])
        ->assertJsonPath('data.import_run.attribute_targets', null);

    expect($this->getJson("/api/imports/leads/{$run->id}")->json('data.import_run.attribute_targets'))->toBeNull();
});

it('403, not 422, on value-mapping with an invalid body and no permission', function () {
    $run = vmRun();
    Sanctum::actingAs(vmActor('leads.import'));

    $this->postJson("/api/imports/request-management/{$run->id}/value-mapping", [])->assertForbidden();
});

it('request-management summary omits conversion_readiness; leads keeps it', function () {
    $run = vmRun(['status' => ImportStatus::Reviewing]);
    $leads = ImportRun::factory()->create(['resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    config(['imports.definitions' => [
        'request-management' => FakeValueMappingImportDefinition::class,
        'leads' => LeadsImportDefinition::class,
    ]]);
    Sanctum::actingAs(vmActor('request-management.import', 'leads.import'));

    expect($this->getJson("/api/imports/request-management/{$run->id}/summary")->assertOk()->json('data.summary'))
        ->not->toHaveKey('conversion_readiness')
        ->and($this->getJson("/api/imports/leads/{$leads->id}/summary")->assertOk()->json('data.summary'))
        ->toHaveKey('conversion_readiness');
});

it('confirm ignores convert_to_opportunity for request-management (no opportunities.create needed)', function () {
    Queue::fake();
    $run = vmRun(['status' => ImportStatus::Reviewing]);
    Sanctum::actingAs(vmActor('request-management.import'));

    $this->postJson("/api/imports/request-management/{$run->id}/confirm", ['convert_to_opportunity' => true])->assertOk();

    expect($run->fresh()->convert_to_opportunity)->toBeFalse();
});

// ---------------------------------------------------------------------------
// POST /value-mapping
// ---------------------------------------------------------------------------

it('lists distinct values merged by case/spacing, with counts, suggestions and the blank cell', function () {
    $run = vmRun();
    Sanctum::actingAs(vmActor('request-management.import'));

    $response = $this->postJson("/api/imports/request-management/{$run->id}/value-mapping", [
        'column_mapping' => ['Nome' => 'first_name', 'Stato' => 'workflow_status', 'Fonte' => 'source_name'],
        'global_config' => ['product_category_id' => 7],
    ])->assertOk()->assertJsonCount(2, 'data.fields');

    expect($response->json('data.fields.0'))->toMatchArray(['field' => 'workflow_status', 'column_key' => 'Stato', 'allow_create' => true])
        ->and($response->json('data.fields.0.values'))->toBe([
            ['value' => 'Attesa_App APL', 'count' => 2, 'suggested_id' => null],
            ['value' => 'OK App. Fissato CPI', 'count' => 1, 'suggested_id' => null],
            ['value' => '', 'count' => 1, 'suggested_id' => null],
            ['value' => 'open', 'count' => 1, 'suggested_id' => 1],
        ])
        ->and($response->json('data.fields.1.values'))->toBe([
            ['value' => 'Web', 'count' => 4, 'suggested_id' => null],
            ['value' => 'Radio', 'count' => 1, 'suggested_id' => null],
        ]);
});

it('lists only the value-mapping fields actually mapped', function () {
    $run = vmRun();
    Sanctum::actingAs(vmActor('request-management.import'));

    $this->postJson("/api/imports/request-management/{$run->id}/value-mapping", [
        'column_mapping' => ['Fonte' => 'source_name'],
    ])->assertOk()->assertJsonCount(1, 'data.fields')->assertJsonPath('data.fields.0.field', 'source_name');
});

it('422 when workflow_status is mapped without the product category', function () {
    $run = vmRun();
    Sanctum::actingAs(vmActor('request-management.import'));

    $this->postJson("/api/imports/request-management/{$run->id}/value-mapping", [
        'column_mapping' => ['Stato' => 'workflow_status'],
    ])->assertStatus(422)->assertJsonValidationErrors('global_config.product_category_id');
});

it('409 when the run is not in the mapping step', function () {
    $run = vmRun(['status' => ImportStatus::Reviewing]);
    Sanctum::actingAs(vmActor('request-management.import'));

    $this->postJson("/api/imports/request-management/{$run->id}/value-mapping", [
        'column_mapping' => ['Fonte' => 'source_name'],
    ])->assertStatus(409);
});

it('404 for a run of another domain and for a domain without value-mapping fields', function () {
    $run = vmRun();
    $other = ImportRun::factory()->create(['resource' => 'leads', 'status' => ImportStatus::Configuring]);
    Sanctum::actingAs(vmActor('request-management.import', 'leads.import'));

    $this->postJson("/api/imports/request-management/{$other->id}/value-mapping", ['column_mapping' => ['A' => 'b']])->assertNotFound();
    $this->postJson("/api/imports/leads/{$other->id}/value-mapping", ['column_mapping' => ['A' => 'b']])->assertNotFound();
    expect($run->exists)->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-001 — per-domain authorization
// ---------------------------------------------------------------------------

it('AC-001: leads.import alone cannot reach any request-management endpoint', function () {
    $run = vmRun();
    Sanctum::actingAs(vmActor('leads.import'));

    $this->getJson("/api/imports/request-management/{$run->id}")->assertForbidden();
    $this->postJson("/api/imports/request-management/{$run->id}/value-mapping", ['column_mapping' => ['Fonte' => 'source_name']])->assertForbidden();
    $this->putJson("/api/imports/request-management/{$run->id}/configure", vmConfigureBody(vmFullDecisions()))->assertForbidden();
    $this->postJson("/api/imports/request-management/{$run->id}/confirm")->assertForbidden();
    $this->getJson('/api/imports/request-management')->assertForbidden();
});

it('AC-001: request-management.import alone cannot reach the leads endpoints', function () {
    $run = ImportRun::factory()->create(['resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    Sanctum::actingAs(vmActor('request-management.import'));

    $this->getJson("/api/imports/leads/{$run->id}")->assertForbidden();
    $this->getJson('/api/imports/leads')->assertForbidden();
});

it('AC-001: request-management.import alone opens the request-management endpoints', function () {
    $run = vmRun();
    Sanctum::actingAs(vmActor('request-management.import'));

    $this->getJson("/api/imports/request-management/{$run->id}")->assertOk();
    $this->getJson('/api/imports/request-management')->assertOk();
});

// ---------------------------------------------------------------------------
// PUT /configure — value_mappings + attribute targets
// ---------------------------------------------------------------------------

it('persists value_mappings on the run and starts staging', function () {
    Queue::fake();
    $run = vmRun();
    Sanctum::actingAs(vmActor('request-management.import'));

    $this->putJson("/api/imports/request-management/{$run->id}/configure", vmConfigureBody(vmFullDecisions()))->assertOk();

    expect($run->fresh()->value_mappings)->toBe(vmFullDecisions())
        ->and($run->fresh()->status)->toBe(ImportStatus::Staging);
});

it('AC-004: 422 when a distinct value has no decision, and the run stays configuring', function () {
    Queue::fake();
    $run = vmRun();
    $decisions = vmFullDecisions();
    unset($decisions['workflow_status']['Attesa_App APL']);
    Sanctum::actingAs(vmActor('request-management.import'));

    $this->putJson("/api/imports/request-management/{$run->id}/configure", vmConfigureBody($decisions))
        ->assertStatus(422)->assertJsonValidationErrors('value_mappings.workflow_status');

    expect($run->fresh()->status)->toBe(ImportStatus::Configuring)->and($run->fresh()->value_mappings)->toBeNull();
});

it('AC-004: 422 for an id outside the selectable options and for a create colliding with an existing name', function () {
    $run = vmRun();
    Sanctum::actingAs(vmActor('request-management.import'));

    $foreign = vmFullDecisions();
    $foreign['workflow_status']['Attesa_App APL'] = ['id' => 999];
    $this->putJson("/api/imports/request-management/{$run->id}/configure", vmConfigureBody($foreign))
        ->assertStatus(422)->assertJsonValidationErrors('value_mappings.workflow_status');

    $duplicate = vmFullDecisions();
    $duplicate['workflow_status']['OK App. Fissato CPI'] = ['create' => ['name' => 'open', 'group' => 'open']];
    $this->putJson("/api/imports/request-management/{$run->id}/configure", vmConfigureBody($duplicate))
        ->assertStatus(422)->assertJsonValidationErrors('value_mappings.workflow_status');
});

it('422 for a value_mappings field the domain does not declare', function () {
    $run = vmRun();
    Sanctum::actingAs(vmActor('request-management.import'));

    $this->putJson("/api/imports/request-management/{$run->id}/configure", vmConfigureBody([...vmFullDecisions(), 'bogus' => ['x' => ['id' => 1]]]))
        ->assertStatus(422)->assertJsonValidationErrors('value_mappings.bogus');
});

it('accepts a valid attribute.<code> target and rejects an inapplicable one', function () {
    Queue::fake();
    $run = vmRun();
    Sanctum::actingAs(vmActor('request-management.import'));

    $ok = vmConfigureBody(vmFullDecisions());
    $ok['column_mapping']['Nome'] = 'attribute.price';
    $this->putJson("/api/imports/request-management/{$run->id}/configure", $ok)->assertOk();

    $run = vmRun();
    $bad = vmConfigureBody(vmFullDecisions());
    $bad['column_mapping']['Nome'] = 'attribute.unknown';
    $this->putJson("/api/imports/request-management/{$run->id}/configure", $bad)
        ->assertStatus(422)->assertJsonValidationErrors('column_mapping.Nome');
});

it('rejects attribute.<code> on a domain without attribute targets', function () {
    $run = ImportRun::factory()->create([
        'resource' => 'leads',
        'status' => ImportStatus::Configuring,
        'detected_columns' => [['name' => 'Nome', 'index' => 0, 'duplicate' => false]],
    ]);
    Sanctum::actingAs(vmActor('leads.import'));

    $this->putJson("/api/imports/leads/{$run->id}/configure", [
        'column_mapping' => ['Nome' => 'attribute.price'],
        'dedup_strategy' => 'create_new',
    ])->assertStatus(422)->assertJsonValidationErrors('column_mapping.Nome');
});

// ---------------------------------------------------------------------------
// AC-016 (backend) — templates keep only reusable ids
// ---------------------------------------------------------------------------

it('AC-016: a template keeps only {id} decisions and matching_template exposes them', function () {
    $run = vmRun([
        'column_mapping' => ['Nome' => 'first_name', 'Stato' => 'workflow_status', 'Fonte' => 'source_name'],
        'value_mappings' => vmFullDecisions(),
        'dedup_strategy' => 'create_new',
    ]);
    Sanctum::actingAs(vmActor('request-management.import'));

    $this->postJson('/api/imports/request-management/mapping-templates', [
        'name' => 'DIL',
        'import_run_id' => $run->id,
    ])->assertCreated();

    $expected = [
        'workflow_status' => ['Attesa_App APL' => ['id' => 2], '' => ['id' => 1], 'open' => ['id' => 1]],
        'source_name' => ['Web' => ['id' => 1], 'Radio' => ['id' => 2]],
    ];
    expect(ImportMappingTemplate::query()->firstOrFail()->value_mappings)->toBe($expected);

    $this->getJson("/api/imports/request-management/{$run->id}")->assertOk()
        ->assertJsonPath('data.import_run.matching_template.value_mappings', $expected);
});

it('a template without any reusable decision stores null', function () {
    $run = vmRun([
        'column_mapping' => ['Nome' => 'first_name'],
        'value_mappings' => ['workflow_status' => ['X' => ['create' => ['name' => 'X', 'group' => 'open']]]],
    ]);
    Sanctum::actingAs(vmActor('request-management.import'));

    $this->postJson('/api/imports/request-management/mapping-templates', ['name' => 'N', 'import_run_id' => $run->id])->assertCreated();

    expect(ImportMappingTemplate::query()->firstOrFail()->value_mappings)->toBeNull();
});
