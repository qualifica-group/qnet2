<?php

use App\Models\AgentFiscalProfile;
use App\Models\ExportRun;
use App\Models\Referent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function profileRows(object $test, array $body = []): array
{
    return $test->postJson('/api/tables/agent-fiscal-profiles/rows', $body + ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items');
}

it('AC-016: exposes the contract columns with their types, sorts and filters', function () {
    Sanctum::actingAs(agentFiscalProfileUserWith(['viewAny']));

    $data = $this->getJson('/api/tables/agent-fiscal-profiles/columns')->assertOk()->json('data');
    $columns = collect($data['columns'])->keyBy('id');

    expect($data['resource'])->toBe('agent-fiscal-profiles')
        ->and($columns->keys()->all())->toBe([
            'id', 'referent', 'withholding_enabled', 'withholding_rate', 'withholding_base_percent', 'enasarco_enabled',
            'enasarco_mandate_type', 'valid_from', 'valid_until', 'is_current', 'updated_at',
        ])
        ->and($columns['referent'])->toMatchArray(['type' => 'text', 'sortable' => true, 'filterType' => 'set'])
        ->and($columns['withholding_enabled'])->toMatchArray(['type' => 'boolean', 'sortable' => true, 'filterType' => 'set'])
        ->and($columns['withholding_rate'])->toMatchArray(['type' => 'number', 'sortable' => true, 'filterType' => 'number'])
        ->and($columns['withholding_base_percent'])->toMatchArray(['type' => 'number', 'filterType' => 'number'])
        ->and($columns['enasarco_enabled'])->toMatchArray(['type' => 'boolean', 'filterType' => 'set'])
        ->and($columns['enasarco_mandate_type'])->toMatchArray(['type' => 'badge', 'filterType' => 'set', 'options' => ['mono', 'pluri']])
        ->and($columns['valid_from'])->toMatchArray(['type' => 'date', 'sortable' => true, 'filterType' => 'date'])
        ->and($columns['valid_until'])->toMatchArray(['type' => 'date', 'filterType' => 'date'])
        ->and($columns['is_current'])->toMatchArray(['type' => 'badge', 'sortable' => false, 'filterType' => 'set', 'options' => ['yes', 'no']])
        ->and($columns['updated_at'])->toMatchArray(['type' => 'date', 'sortable' => true, 'filterType' => 'date'])
        ->and($data['defaultSort'])->toBe([
            ['columnId' => 'referent', 'direction' => 'asc'],
            ['columnId' => 'valid_from', 'direction' => 'desc'],
        ]);
});

it('AC-016: default sort is referent asc then valid_from desc, rows carry id, referent_id and abilities', function () {
    Sanctum::actingAs(agentFiscalProfileUserWith(['viewAny', 'update']));
    $rossi = Referent::factory()->create(['name' => 'Rossi']);
    $bianchi = Referent::factory()->create(['name' => 'Bianchi']);
    AgentFiscalProfile::factory()->create(['referent_id' => $rossi->id, 'valid_from' => '2026-01-01', 'valid_until' => '2026-12-31']);
    AgentFiscalProfile::factory()->create(['referent_id' => $bianchi->id, 'valid_from' => '2025-01-01', 'valid_until' => '2025-12-31']);
    AgentFiscalProfile::factory()->create(['referent_id' => $bianchi->id, 'valid_from' => '2026-01-01']);

    $rows = profileRows($this);

    expect(collect($rows)->map(fn ($row) => [$row['referent'], $row['valid_from']])->all())->toBe([
        ['Bianchi', '2026-01-01'], ['Bianchi', '2025-01-01'], ['Rossi', '2026-01-01'],
    ])
        ->and($rows[0])->toHaveKeys(['id', 'referent_id', 'withholding_rate', 'enasarco_mandate_type', 'valid_until', 'is_current', 'updated_at'])
        ->and($rows[0]['abilities'])->toBe(['update' => true, 'delete' => false])
        ->and($rows[0]['referent_id'])->toBe($bianchi->id)
        ->and($rows[0]['withholding_rate'])->toEqual(23.0);
});

it('AC-016: is_current reflects today and drives the yes/no filter', function () {
    Sanctum::actingAs(agentFiscalProfileUserWith(['viewAny']));
    $today = now()->toDateString();
    $current = AgentFiscalProfile::factory()->create(['valid_from' => now()->subYear()->toDateString(), 'valid_until' => null]);
    $closed = AgentFiscalProfile::factory()->create(['valid_from' => now()->subYears(2)->toDateString(), 'valid_until' => now()->subDay()->toDateString()]);
    $future = AgentFiscalProfile::factory()->create(['valid_from' => now()->addDay()->toDateString(), 'valid_until' => null]);
    $endsToday = AgentFiscalProfile::factory()->create(['valid_from' => now()->subMonth()->toDateString(), 'valid_until' => $today]);
    $startsToday = AgentFiscalProfile::factory()->create(['valid_from' => $today, 'valid_until' => null]);

    $byId = collect(profileRows($this))->pluck('is_current', 'id');
    expect($byId[$current->id])->toBe('yes')
        ->and($byId[$closed->id])->toBe('no')
        ->and($byId[$future->id])->toBe('no')
        ->and($byId[$endsToday->id])->toBe('yes')
        ->and($byId[$startsToday->id])->toBe('yes');

    $ids = fn (array $values) => collect(profileRows($this, ['filterModel' => ['is_current' => ['filterType' => 'set', 'values' => $values]]]))->pluck('id')->sort()->values()->all();

    expect($ids(['yes']))->toBe(collect([$current, $endsToday, $startsToday])->pluck('id')->sort()->values()->all())
        ->and($ids(['no']))->toBe(collect([$closed, $future])->pluck('id')->sort()->values()->all())
        ->and($ids(['yes', 'no']))->toHaveCount(5);
});

it('AC-016: the set, number and date filters narrow the rows', function () {
    Sanctum::actingAs(agentFiscalProfileUserWith(['viewAny']));
    $rossi = Referent::factory()->create(['name' => 'Rossi']);
    $a = AgentFiscalProfile::factory()->create(['referent_id' => $rossi->id, 'withholding_rate' => '20.00', 'valid_from' => '2026-01-01', 'valid_until' => '2026-12-31']);
    $b = AgentFiscalProfile::factory()->withEnasarco('mono')->create(['withholding_rate' => '23.00', 'valid_from' => '2027-01-01']);
    $c = AgentFiscalProfile::factory()->withoutWithholding()->create(['valid_from' => '2028-01-01']);

    $ids = fn (array $filterModel) => collect(profileRows($this, ['filterModel' => $filterModel]))->pluck('id')->sort()->values()->all();

    expect($ids(['referent' => ['filterType' => 'set', 'values' => ['Rossi']]]))->toBe([$a->id])
        ->and($ids(['withholding_enabled' => ['filterType' => 'set', 'values' => [false]]]))->toBe([$c->id])
        ->and($ids(['enasarco_enabled' => ['filterType' => 'set', 'values' => [true]]]))->toBe([$b->id])
        ->and($ids(['enasarco_mandate_type' => ['filterType' => 'set', 'values' => ['mono']]]))->toBe([$b->id])
        ->and($ids(['withholding_rate' => ['filterType' => 'number', 'type' => 'equals', 'filter' => 20]]))->toBe([$a->id])
        ->and($ids(['valid_from' => ['filterType' => 'date', 'type' => 'greaterThan', 'dateFrom' => '2026-06-01 00:00:00']]))->toBe([$b->id, $c->id]);
});

it('AC-016: sorting is allow-listed: referent, rate and valid_from sort, is_current and unknown columns do not', function () {
    Sanctum::actingAs(agentFiscalProfileUserWith(['viewAny']));
    $zeta = Referent::factory()->create(['name' => 'Zeta']);
    $alfa = Referent::factory()->create(['name' => 'Alfa']);
    AgentFiscalProfile::factory()->create(['referent_id' => $zeta->id, 'withholding_rate' => '10.00']);
    AgentFiscalProfile::factory()->create(['referent_id' => $alfa->id, 'withholding_rate' => '30.00']);

    $sorted = fn (string $col, string $dir) => collect(profileRows($this, ['sortModel' => [['colId' => $col, 'sort' => $dir]]]))->pluck('referent')->all();

    expect($sorted('referent', 'desc'))->toBe(['Zeta', 'Alfa'])
        ->and($sorted('withholding_rate', 'desc'))->toBe(['Alfa', 'Zeta'])
        ->and($sorted('withholding_rate', 'asc'))->toBe(['Zeta', 'Alfa']);

    $this->postJson('/api/tables/agent-fiscal-profiles/rows', [
        'startRow' => 0, 'endRow' => 25, 'sortModel' => [['colId' => 'created_by', 'sort' => 'asc']],
    ])->assertUnprocessable()->assertJsonValidationErrors('sortModel.0.colId');
});

it('AC-016: the values endpoint lists referent names and the is_current options', function () {
    Sanctum::actingAs(agentFiscalProfileUserWith(['viewAny']));
    AgentFiscalProfile::factory()->create(['referent_id' => Referent::factory()->create(['name' => 'Zeta'])->id]);
    AgentFiscalProfile::factory()->create(['referent_id' => Referent::factory()->create(['name' => 'Alfa'])->id]);

    $this->postJson('/api/tables/agent-fiscal-profiles/values', ['columnId' => 'referent'])
        ->assertOk()->assertJsonPath('data.values', ['Alfa', 'Zeta']);
    $this->postJson('/api/tables/agent-fiscal-profiles/values', ['columnId' => 'is_current'])
        ->assertOk()->assertJsonPath('data.values', ['yes', 'no']);
});

it('AC-016: 403 without viewAny on every table endpoint', function () {
    Sanctum::actingAs(agentFiscalProfileUserWith(['view', 'create']));

    $this->getJson('/api/tables/agent-fiscal-profiles/columns')->assertForbidden();
    $this->postJson('/api/tables/agent-fiscal-profiles/rows', ['startRow' => 0, 'endRow' => 25])->assertForbidden();
    $this->postJson('/api/tables/agent-fiscal-profiles/values', ['columnId' => 'referent'])->assertForbidden();
});

it('AC-016: the export carries the rows and is allowed only with agent-fiscal-profiles.export', function () {
    Storage::fake('local');
    AgentFiscalProfile::factory()->create(['referent_id' => Referent::factory()->create(['name' => 'Alfa Agent'])->id]);
    $body = ['format' => 'csv', 'columns' => [['colId' => 'referent', 'header' => 'Agent'], ['colId' => 'valid_from', 'header' => 'From']]];

    Sanctum::actingAs(agentFiscalProfileUserWith(['viewAny']));
    $this->postJson('/api/exports/agent-fiscal-profiles', $body)->assertForbidden();

    Sanctum::actingAs(agentFiscalProfileUserWith(['viewAny', 'export']));
    $response = $this->postJson('/api/exports/agent-fiscal-profiles', $body)->assertCreated();
    $run = ExportRun::findOrFail($response->json('data.export_run.id'))->fresh();

    expect($run->row_count)->toBe(1)
        ->and(Storage::disk('local')->get($run->file_path))->toContain('Alfa Agent');
});

it('AC-016: bulk delete removes the profiles through the table endpoint', function () {
    Sanctum::actingAs(agentFiscalProfileUserWith(['viewAny', 'delete']));
    $profile = AgentFiscalProfile::factory()->create();

    $this->postJson('/api/tables/agent-fiscal-profiles/bulk-delete', ['ids' => [$profile->id]])->assertOk();

    $this->assertDatabaseMissing('agent_fiscal_profiles', ['id' => $profile->id]);
});
