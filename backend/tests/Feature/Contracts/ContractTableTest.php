<?php

use App\Enums\ContractStatusGroup;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\ExportRun;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Backend-driven table for the `contracts` domain (spec 0072, MT-04):
 * AC-029..AC-036 — declared columns, relation-derived sort/filter/search,
 * the BR-6 `alert` indicator, per-row authorization and export parity.
 */
uses(RefreshDatabase::class);

if (! function_exists('contractTableUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function contractTableUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'update', 'export', 'viewActivity', 'validate', 'terminate', 'program', 'changeStatus', 'reactivate'] as $ability) {
            Permission::findOrCreate("contracts.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("contracts.{$ability}");
        }

        return $user;
    }
}

/**
 * A contract wired to a fully-labelled quote/opportunity/registry chain
 * (D-1), so every relation-derived column has a real value to sort/filter on.
 *
 * @param  array<string, mixed>  $quoteOverrides
 * @param  array<string, mixed>  $contractOverrides
 */
if (! function_exists('makeLabelledContract')) {
    function makeLabelledContract(
        string $registryName,
        string $opportunityName,
        string $commercialName,
        string $reporterName,
        string $supervisorName,
        string $quoteCode,
        string $quoteTitle,
        array $quoteOverrides = [],
        array $contractOverrides = [],
    ): Contract {
        $registry = Registry::factory()->create(['name' => $registryName]);
        $opportunity = Opportunity::factory()->create(['name' => $opportunityName, 'registry_id' => $registry->id]);
        $commercial = Referent::factory()->create(['name' => $commercialName]);
        $reporter = Referent::factory()->create(['name' => $reporterName]);
        $supervisor = User::factory()->create(['name' => $supervisorName]);

        $quote = Quote::factory()->create(array_merge([
            'opportunity_id' => $opportunity->id,
            'commercial_id' => $commercial->id,
            'reporter_id' => $reporter->id,
            'supervisor_id' => $supervisor->id,
            'code' => $quoteCode,
            'title' => $quoteTitle,
        ], $quoteOverrides));

        return Contract::factory()->create(array_merge(['quote_id' => $quote->id], $contractOverrides));
    }
}

// ---------------------------------------------------------------------------
// AC-029 — declared columns, in the frozen order
// ---------------------------------------------------------------------------

it('AC-029: GET /api/tables/contracts/columns declares the 19 columns in the exact frozen order', function () {
    $actor = contractTableUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/contracts/columns')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data');

    expect($data['resource'])->toBe('contracts');

    $ids = collect($data['columns'])->pluck('id')->all();

    expect($ids)->toBe([
        'id', 'code', 'title', 'registry', 'opportunity', 'commercial', 'reporter',
        'supervisor', 'managers', 'contract_status', 'quote_date', 'accepted_at', 'validated_at',
        'renewal_date', 'expiry_date', 'terminated_at', 'revenue_net', 'revenue_vat',
        'revenue_gross', 'alert',
    ]);
});

// ---------------------------------------------------------------------------
// AC-030 — ordering on every relation-derived column, via allow-listed
// correlated subqueries only (never raw user input in SQL, backend.md §8).
// ---------------------------------------------------------------------------

it('AC-030: sorts ascending by registry/opportunity/commercial/reporter/supervisor/contract_status', function () {
    $actor = contractTableUserWith(['viewAny', 'view']);

    $statusA = ContractStatus::factory()->create(['name' => 'Alfa status']);
    $statusB = ContractStatus::factory()->create(['name' => 'Zulu status']);

    $first = makeLabelledContract(
        'Alfa registry', 'Alfa opportunity', 'Alfa commercial', 'Alfa reporter', 'Alfa supervisor',
        'QUO-1001', 'Alfa quote',
        contractOverrides: ['contract_status_id' => $statusA->id],
    );
    $second = makeLabelledContract(
        'Zulu registry', 'Zulu opportunity', 'Zulu commercial', 'Zulu reporter', 'Zulu supervisor',
        'QUO-1002', 'Zulu quote',
        contractOverrides: ['contract_status_id' => $statusB->id],
    );

    Sanctum::actingAs($actor);

    foreach (['registry', 'opportunity', 'commercial', 'reporter', 'supervisor', 'contract_status'] as $columnId) {
        $response = $this->postJson('/api/tables/contracts/rows', [
            'startRow' => 0,
            'endRow' => 25,
            'sortModel' => [['colId' => $columnId, 'sort' => 'asc']],
        ])->assertOk();

        $ids = collect($response->json('items'))->pluck('id')->all();

        expect($ids)->toBe([$first->id, $second->id], "sorting by [{$columnId}] failed");
    }
});

// ---------------------------------------------------------------------------
// AC-031 — `set` filter on contract_status
// ---------------------------------------------------------------------------

it('AC-031: filters rows by a contract_status set filter', function () {
    $actor = contractTableUserWith(['viewAny', 'view']);
    $statusA = ContractStatus::factory()->create(['name' => 'Da validare demo']);
    $statusB = ContractStatus::factory()->create(['name' => 'Sospeso demo']);

    $matching = Contract::factory()->create(['contract_status_id' => $statusA->id]);
    Contract::factory()->create(['contract_status_id' => $statusB->id]);

    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/contracts/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['contract_status' => ['filterType' => 'set', 'values' => ['Da validare demo']]],
    ])->assertOk();

    $ids = collect($response->json('items'))->pluck('id')->all();

    expect($ids)->toBe([$matching->id]);
});

// ---------------------------------------------------------------------------
// AC-032 — the alert indicator, computed from config('contracts.*') windows
// ---------------------------------------------------------------------------

it('AC-032: exposes alert=expiring, alert=renewal_due or null per the configured windows', function () {
    $actor = contractTableUserWith(['viewAny', 'view']);
    $withinDays = (int) config('contracts.expiring_within_days');

    $expiring = Contract::factory()->create(['expiry_date' => Carbon::today()->addDays(min(5, $withinDays))]);
    $renewalDue = Contract::factory()->create([
        'expiry_date' => null,
        'renewal_date' => Carbon::today()->addDays(min(5, (int) config('contracts.renewal_within_days'))),
    ]);
    $none = Contract::factory()->create(['expiry_date' => null, 'renewal_date' => null]);

    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/contracts/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $items = collect($response->json('items'));

    expect($items->firstWhere('id', $expiring->id)['alert'])->toBe('expiring')
        ->and($items->firstWhere('id', $renewalDue->id)['alert'])->toBe('renewal_due')
        ->and($items->firstWhere('id', $none->id)['alert'])->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-033 — a closed_lost contract never carries an alert, even within window
// ---------------------------------------------------------------------------

it('AC-033: a closed_lost contract never carries an alert even with expiry_date within window', function () {
    $actor = contractTableUserWith(['viewAny', 'view']);
    $closedLost = ContractStatus::factory()->group(ContractStatusGroup::ClosedLost)->create();

    $contract = Contract::factory()->create([
        'contract_status_id' => $closedLost->id,
        'expiry_date' => Carbon::today()->addDays(1),
    ]);

    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/contracts/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $contract->id);

    expect($row['alert'])->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-034 — global quick-search on the quote's code/title
// ---------------------------------------------------------------------------

it('AC-034: the global quick-search matches the quote code or title', function () {
    $actor = contractTableUserWith(['viewAny', 'view']);

    $quote = Quote::factory()->create(['code' => 'QUO-7777', 'title' => 'Fornitura speciale']);
    $matching = Contract::factory()->create(['quote_id' => $quote->id]);
    Contract::factory()->create(); // unrelated noise row

    Sanctum::actingAs($actor);

    $byCode = $this->postJson('/api/tables/contracts/rows', ['startRow' => 0, 'endRow' => 25, 'search' => 'QUO-7777'])->assertOk();
    expect(collect($byCode->json('items'))->pluck('id')->all())->toBe([$matching->id]);

    $byTitle = $this->postJson('/api/tables/contracts/rows', ['startRow' => 0, 'endRow' => 25, 'search' => 'speciale'])->assertOk();
    expect(collect($byTitle->json('items'))->pluck('id')->all())->toBe([$matching->id]);
});

// ---------------------------------------------------------------------------
// AC-035 — 403 without contracts.viewAny on every /api/tables/contracts/*
// endpoint
// ---------------------------------------------------------------------------

it('AC-035: 403s on columns/rows/values without contracts.viewAny', function () {
    $actor = contractTableUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/tables/contracts/columns')->assertForbidden();
    $this->postJson('/api/tables/contracts/rows', ['startRow' => 0, 'endRow' => 25])->assertForbidden();
    $this->postJson('/api/tables/contracts/values', ['columnId' => 'contract_status'])->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-036 — export produces a file with the same visible columns
// ---------------------------------------------------------------------------

it('AC-036: POST /api/exports/contracts exports the visible columns', function () {
    Storage::fake('local');
    $actor = contractTableUserWith(['viewAny', 'view', 'export']);
    $quote = Quote::factory()->create(['code' => 'QUO-9001', 'title' => 'Contratto esportato']);
    Contract::factory()->create(['quote_id' => $quote->id]);

    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/exports/contracts', [
        'format' => 'csv',
        'columns' => [
            ['colId' => 'code', 'header' => 'Code'],
            ['colId' => 'title', 'header' => 'Title'],
        ],
    ])->assertCreated();

    $run = ExportRun::findOrFail($response->json('data.export_run.id'))->fresh();

    expect($run->row_count)->toBe(1);

    $csv = Storage::disk('local')->get($run->file_path);
    $lines = array_values(array_filter(explode("\n", trim($csv, "\xEF\xBB\xBF\n"))));
    $rows = array_map(static fn (string $line): array => str_getcsv($line, ',', '"', ''), $lines);

    expect($rows[0])->toBe(['Code', 'Title'])
        ->and($rows[1])->toBe(['QUO-9001', 'Contratto esportato']);
});

// ---------------------------------------------------------------------------
// `managers` — the offer's G.A. team on the contracts grid (user directive
// 2026-08-31), mirroring the Offerte module's own column; plus the three
// people columns that ship hidden by default.
// ---------------------------------------------------------------------------

it('hides commercial/supervisor/managers from the DEFAULT layout, leaving every other column visible', function () {
    $actor = contractTableUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $columns = collect($this->getJson('/api/tables/contracts/columns')->assertOk()->json('data.columns'));

    // `id` is the generic engine's own hidden row-key column, not a catalogue entry.
    expect($columns->reject->visible->pluck('id')->all())->toBe(['id', 'commercial', 'supervisor', 'managers']);
});

it('declares `managers` beside the supervisor: filterable as a set, never sortable', function () {
    $actor = contractTableUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/contracts/columns')->assertOk()->json('data');
    $managers = collect($data['columns'])->firstWhere('id', 'managers');
    $ids = collect($data['columns'])->pluck('id')->all();

    expect($managers['sortable'])->toBeFalse()
        ->and($managers['filterable'])->toBeTrue()
        ->and($managers['filterType'])->toBe('set')
        ->and($ids[array_search('supervisor', $ids, true) + 1])->toBe('managers')
        ->and(collect($data['filters'])->firstWhere('columnId', 'managers')['type'])->toBe('set');
});

it('projects the offer G.A. team on the row, ordered by pivot position', function () {
    $actor = contractTableUserWith(['viewAny', 'view']);
    $contract = makeLabelledContract('Reg', 'Opp', 'Comm', 'Rep', 'Sup', 'QUO-7001', 'Con GA');
    $first = User::factory()->create(['name' => 'Anna Prima']);
    $second = User::factory()->create(['name' => 'Bruno Secondo']);
    $contract->quote->managers()->sync([$second->id => ['position' => 2], $first->id => ['position' => 1]]);

    Sanctum::actingAs($actor);

    $row = collect($this->postJson('/api/tables/contracts/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()
        ->json('items'))
        ->firstWhere('id', $contract->id);

    expect(array_column($row['managers'], 'name'))->toBe(['Anna Prima', 'Bruno Secondo']);
});

it('projects an empty G.A. array when the offer has no team', function () {
    $actor = contractTableUserWith(['viewAny', 'view']);
    $contract = makeLabelledContract('Reg', 'Opp', 'Comm', 'Rep', 'Sup', 'QUO-7002', 'Senza GA');

    Sanctum::actingAs($actor);

    $row = collect($this->postJson('/api/tables/contracts/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()
        ->json('items'))
        ->firstWhere('id', $contract->id);

    expect($row['managers'])->toBe([]);
});

it('filters the grid by G.A. name', function () {
    $actor = contractTableUserWith(['viewAny', 'view']);
    $wanted = makeLabelledContract('Reg A', 'Opp A', 'Comm A', 'Rep A', 'Sup A', 'QUO-7003', 'Con GA');
    $other = makeLabelledContract('Reg B', 'Opp B', 'Comm B', 'Rep B', 'Sup B', 'QUO-7004', 'Altro GA');
    $manager = User::factory()->create(['name' => 'Carla Gestore']);
    $wanted->quote->managers()->sync([$manager->id => ['position' => 1]]);
    $other->quote->managers()->sync([User::factory()->create(['name' => 'Dario Gestore'])->id => ['position' => 1]]);

    Sanctum::actingAs($actor);

    $ids = collect($this->postJson('/api/tables/contracts/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['managers' => ['filterType' => 'set', 'values' => ['Carla Gestore']]],
    ])->assertOk()->json('items'))->pluck('id')->all();

    expect($ids)->toBe([$wanted->id]);
});

it('offers the distinct G.A. names of the matching contracts as filter values', function () {
    $actor = contractTableUserWith(['viewAny', 'view']);
    $contract = makeLabelledContract('Reg C', 'Opp C', 'Comm C', 'Rep C', 'Sup C', 'QUO-7005', 'Con GA');
    $contract->quote->managers()->sync([
        User::factory()->create(['name' => 'Zeta Gestore'])->id => ['position' => 1],
        User::factory()->create(['name' => 'Alfa Gestore'])->id => ['position' => 2],
    ]);
    // An offer with no contract: its manager must NOT show up.
    Quote::factory()->create()->managers()->sync([User::factory()->create(['name' => 'Escluso Gestore'])->id => ['position' => 1]]);

    Sanctum::actingAs($actor);

    $values = $this->postJson('/api/tables/contracts/values', ['columnId' => 'managers'])->assertOk()->json('data.values');

    expect($values)->toBe(['Alfa Gestore', 'Zeta Gestore']);
});
