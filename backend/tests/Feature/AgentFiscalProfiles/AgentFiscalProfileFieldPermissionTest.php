<?php

use App\DataObjects\AgentFiscalProfiles\AgentFiscalProfileData;
use App\Models\AgentFiscalProfile;
use App\Models\ExportRun;
use App\Models\Referent;
use App\Services\AgentFiscalProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('AC-015: a read-only field submitted with a different value is a 422, the same value passes', function () {
    $user = agentFiscalProfileUserWith(['view', 'update']);
    restrictAgentFiscalProfileField($user, 'withholding_rate', visible: true, editable: false);
    Sanctum::actingAs($user);
    $profile = AgentFiscalProfile::factory()->create(['withholding_rate' => '23.00']);
    $payload = agentFiscalProfilePayload(['referent_id' => $profile->referent_id]);

    $this->getJson("/api/agent-fiscal-profiles/{$profile->id}")
        ->assertJsonPath('permissions.fields.withholding_rate.editable', false);

    $this->putJson("/api/agent-fiscal-profiles/{$profile->id}", [...$payload, 'withholding_rate' => '10.00'])
        ->assertUnprocessable()->assertJsonValidationErrors('withholding_rate');
    expect($profile->fresh()->withholding_rate)->toBe('23.00');

    $this->putJson("/api/agent-fiscal-profiles/{$profile->id}", [...$payload, 'notes' => 'ok'])->assertOk();
});

it('AC-015: a hidden field is absent from the detail, the table rows and the table columns', function () {
    $user = agentFiscalProfileUserWith(['view', 'viewAny']);
    restrictAgentFiscalProfileField($user, 'withholding_rate', visible: false, editable: false);
    restrictAgentFiscalProfileField($user, 'enasarco_mandate_type', visible: false, editable: false);
    Sanctum::actingAs($user);
    $profile = AgentFiscalProfile::factory()->create();

    $data = $this->getJson("/api/agent-fiscal-profiles/{$profile->id}")->assertOk()->json('data');
    expect($data)->not->toHaveKeys(['withholding_rate', 'enasarco_mandate_type'])
        ->and($data)->toHaveKeys(['withholding_base_percent', 'referent_id', 'referent']);

    $row = $this->postJson('/api/tables/agent-fiscal-profiles/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items.0');
    expect($row)->not->toHaveKeys(['withholding_rate', 'enasarco_mandate_type'])
        ->and($row)->toHaveKeys(['withholding_base_percent', 'referent', 'referent_id']);

    $columns = collect($this->getJson('/api/tables/agent-fiscal-profiles/columns')->assertOk()->json('data.columns'))->pluck('id');
    expect($columns)->not->toContain('withholding_rate')->not->toContain('enasarco_mandate_type')->toContain('withholding_base_percent');
});

it('AC-015: the activity log records create and update', function () {
    Sanctum::actingAs(agentFiscalProfileUserWith(['create', 'update']));

    $id = $this->postJson('/api/agent-fiscal-profiles', agentFiscalProfilePayload())->assertCreated()->json('data.id');
    $referentId = AgentFiscalProfile::findOrFail($id)->referent_id;
    $this->putJson("/api/agent-fiscal-profiles/{$id}", agentFiscalProfilePayload(['referent_id' => $referentId, 'withholding_rate' => '20.00']))->assertOk();

    $events = DB::table('activity_log')
        ->where('subject_type', 'agent_fiscal_profile')
        ->where('subject_id', $id)
        ->orderBy('id')
        ->pluck('event')
        ->all();

    expect($events)->toBe(['created', 'updated']);
});

it('AC-015: the field catalogue marks the referent and the validity start as mandatory', function () {
    Sanctum::actingAs(agentFiscalProfileUserWith(['viewAny', 'create']));

    $response = $this->getJson('/api/meta/agent-fiscal-profiles')->assertOk()
        ->assertJsonPath('permissions.fields.referent_id.required', true)
        ->assertJsonPath('permissions.fields.valid_from.required', true)
        ->assertJsonPath('permissions.fields.valid_until.required', false);

    expect(collect($response->json('data.fields'))->firstWhere('key', 'referent_id')['mandatory'])->toBeTrue();
});

it('AC-015: a hidden column cannot be sorted or filtered (422) and is absent from the export', function () {
    Storage::fake('local');
    $user = agentFiscalProfileUserWith(['viewAny', 'export']);
    restrictAgentFiscalProfileField($user, 'withholding_rate', visible: false, editable: false);
    Sanctum::actingAs($user);
    AgentFiscalProfile::factory()->create(['withholding_rate' => '23.00']);

    $this->postJson('/api/tables/agent-fiscal-profiles/rows', [
        'startRow' => 0, 'endRow' => 25, 'sortModel' => [['colId' => 'withholding_rate', 'sort' => 'asc']],
    ])->assertUnprocessable()->assertJsonValidationErrors('sortModel.0.colId');

    $this->postJson('/api/tables/agent-fiscal-profiles/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['withholding_rate' => ['filterType' => 'number', 'type' => 'equals', 'filter' => 23]],
    ])->assertUnprocessable();

    $export = fn (array $columns) => $this->postJson('/api/exports/agent-fiscal-profiles', ['format' => 'csv', 'columns' => $columns]);

    $export([['colId' => 'valid_from', 'header' => 'From'], ['colId' => 'withholding_rate', 'header' => 'Rate']])
        ->assertUnprocessable()->assertJsonValidationErrors('columns.1.colId');

    $response = $export([['colId' => 'valid_from', 'header' => 'From']])->assertCreated();
    $csv = Storage::disk('local')->get(ExportRun::findOrFail($response->json('data.export_run.id'))->fresh()->file_path);

    expect($csv)->toContain('2026-01-01')->not->toContain('23.00');
});

it('AC-014: the service re-checks the overlap under the referent lock', function () {
    $actor = agentFiscalProfileUserWith(['create']);
    $referent = Referent::factory()->create();
    AgentFiscalProfile::factory()->create(['referent_id' => $referent->id, 'valid_from' => '2026-01-01', 'valid_until' => null]);
    $data = AgentFiscalProfileData::fromValidated(agentFiscalProfilePayload(['referent_id' => $referent->id, 'valid_from' => '2026-06-01']));

    expect(fn () => app(AgentFiscalProfileService::class)->create($data, $actor))
        ->toThrow(ValidationException::class);
    expect(AgentFiscalProfile::count())->toBe(1);
});
