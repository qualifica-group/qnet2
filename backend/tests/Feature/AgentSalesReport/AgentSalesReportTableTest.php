<?php

declare(strict_types=1);

use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0365 AC-003 (table part), AC-004, AC-005, AC-006, AC-007: the read-only
 * `agent-sales-report` table domain.
 */
uses(RefreshDatabase::class);

// Tasks 427625/427940, REQUIREMENT CHANGED: product line and category after the title, the
// taxable amount net of the reporter's commission after the reporter, and a `view` row action.
const AGENT_SALES_REPORT_COLUMNS = [
    'code', 'quote_date', 'accepted_at', 'registry', 'title', 'root_category', 'category', 'address', 'postal_code',
    'city', 'phone', 'email', 'company_site', 'managers', 'quote_workflow_status', 'revenue_net', 'commercial',
    'reporter', 'revenue_net_after_reporter',
];

/**
 * @param  array<string, mixed>  $state
 * @return array<int, array<string, mixed>>
 */
function agentSalesReportRows(mixed $test, array $state = []): array
{
    return $test->postJson('/api/tables/agent-sales-report/rows', ['startRow' => 0, 'endRow' => 25, ...$state])
        ->assertOk()->json('items');
}

it('AC-003: columns and rows answer 403 without agent-sales-report.view', function () {
    Sanctum::actingAs(User::factory()->create()->givePermissionTo(Permission::findOrCreate('quotes.viewAny', 'web')));

    $this->getJson('/api/tables/agent-sales-report/columns')->assertForbidden();
    $this->postJson('/api/tables/agent-sales-report/rows', ['startRow' => 0, 'endRow' => 25])->assertForbidden();
});

it('AC-003: an unauthenticated call is 401', function () {
    $this->getJson('/api/tables/agent-sales-report/columns')->assertUnauthorized();
});

it('AC-004: declares the contract columns in order, read-only, with the view action only', function () {
    Sanctum::actingAs(agentSalesReportActor()->givePermissionTo(Permission::findOrCreate('quotes.view', 'web')));

    $data = $this->getJson('/api/tables/agent-sales-report/columns')->assertOk()->json('data');
    $columns = collect($data['columns'])->reject(fn (array $column): bool => in_array($column['id'], ['id', 'old_id'], true))->values();

    expect($columns->pluck('id')->all())->toBe(AGENT_SALES_REPORT_COLUMNS)
        ->and($columns->pluck('editable')->filter()->all())->toBe([])
        ->and($columns->every(fn (array $column): bool => $column['visible'] === true))->toBeTrue()
        ->and(collect($data['actions'])->pluck('key')->all())->toBe(['view'])
        ->and($data['defaultSort'])->toBe([
            ['columnId' => 'quote_date', 'direction' => 'desc'],
            ['columnId' => 'id', 'direction' => 'desc'],
        ])
        ->and($columns->firstWhere('id', 'registry'))->toMatchArray(['sortable' => true, 'filterType' => 'set'])
        ->and($columns->firstWhere('id', 'address'))->toMatchArray(['sortable' => false, 'filterable' => false])
        ->and($columns->firstWhere('id', 'city'))->toMatchArray(['sortable' => true, 'filterType' => 'text'])
        ->and($columns->firstWhere('id', 'managers'))->toMatchArray(['sortable' => false, 'filterType' => 'set'])
        ->and($columns->firstWhere('id', 'revenue_net'))->toMatchArray(['type' => 'number', 'filterType' => 'number'])
        ->and($data['searchable'])->toContain('code', 'title');
});

it('AC-005: projects every contract value of a fully populated offer', function () {
    $status = QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'open')->sole();
    $quote = agentSalesReportQuote(attributes: ['title' => 'Corso sicurezza', 'revenue_net' => 1234.5]);
    $team = agentSalesReportTeam($quote);
    $quote->forceFill(['accepted_at' => '2026-03-20'])->saveQuietly();
    Sanctum::actingAs(agentSalesReportActor());

    $row = agentSalesReportRows($this)[0];

    expect($row)->toMatchArray([
        'id' => $quote->id,
        'code' => $quote->code,
        'quote_date' => '2026-03-10',
        'accepted_at' => '2026-03-20',
        'registry' => 'Acme Spa',
        'title' => 'Corso sicurezza',
        'address' => 'Via Roma 1, Scala B',
        'postal_code' => '20100',
        'city' => 'Milano',
        'phone' => '+39 02 1234567',
        'email' => 'acmespa@example.com',
    ])
        ->and($row['revenue_net'])->toEqual(1234.5)
        ->and($row['company_site']['name'])->toBe('Sede Nord')
        ->and($row['commercial'])->toBe(['id' => $team['commercial']->id, 'name' => 'Carla Commerciale'])
        ->and($row['reporter']['name'])->toBe('Sara Segnalatrice')
        ->and($row['managers'][0]['name'])->toBe('Gino Gestore')
        ->and($row['quote_workflow_status']['id'])->toBe($status->id)
        ->and($row['quote_workflow_status']['name'])->toBe($status->name);
});

it('AC-005: a registry without card yields null address and contacts', function () {
    $quote = agentSalesReportQuote(attributes: ['title' => 'Nuda']);
    $quote->opportunity->registry->personalData()->delete();
    Sanctum::actingAs(agentSalesReportActor());

    expect(agentSalesReportRows($this)[0])->toMatchArray(['address' => null, 'postal_code' => null, 'city' => null, 'phone' => null, 'email' => null]);
});

it('orders by quote_date desc then id desc by default and sorts by the allow-listed columns', function () {
    $old = agentSalesReportQuote(attributes: ['quote_date' => '2026-01-01']);
    $firstNew = agentSalesReportQuote(attributes: ['quote_date' => '2026-05-01']);
    $secondNew = agentSalesReportQuote(attributes: ['quote_date' => '2026-05-01']);
    Sanctum::actingAs(agentSalesReportActor());

    expect(collect(agentSalesReportRows($this))->pluck('id')->all())->toBe([$secondNew->id, $firstNew->id, $old->id]);

    $byRegistry = agentSalesReportQuote(agentSalesReportRegistry('Zeta Srl', 'Aosta'));
    $sortedByRegistry = collect(agentSalesReportRows($this, ['sortModel' => [['colId' => 'registry', 'sort' => 'desc']]]))->pluck('id')->first();
    $sortedByCity = collect(agentSalesReportRows($this, ['sortModel' => [['colId' => 'city', 'sort' => 'asc']]]))->pluck('id')->first();

    expect($sortedByRegistry)->toBe($byRegistry->id)
        ->and($sortedByCity)->toBe($byRegistry->id);
});

it('AC-003: a sort on a column outside the allow-list is refused', function () {
    Sanctum::actingAs(agentSalesReportActor());

    $this->postJson('/api/tables/agent-sales-report/rows', [
        'startRow' => 0, 'endRow' => 25, 'sortModel' => [['colId' => 'address', 'sort' => 'asc']],
    ])->assertStatus(422);
});

it('AC-006: without a wide visibility the report shows only the offers the actor follows', function () {
    $actor = agentSalesReportActor(seesAll: false);
    $mine = agentSalesReportQuote(attributes: ['supervisor_id' => $actor->id]);
    agentSalesReportQuote(attributes: ['title' => 'Altrui']);
    Sanctum::actingAs($actor);

    expect(collect(agentSalesReportRows($this))->pluck('id')->all())->toBe([$mine->id]);
});

it('AC-007: filters by registry, commercial and title search, and the total follows', function () {
    $acme = agentSalesReportQuote(agentSalesReportRegistry('Acme Spa'), ['title' => 'Fibra ottica']);
    $other = agentSalesReportQuote(agentSalesReportRegistry('Beta Srl', 'Roma'), ['title' => 'Antincendio']);
    $team = agentSalesReportTeam($other);
    Sanctum::actingAs(agentSalesReportActor());

    $ids = fn (array $state): array => collect(agentSalesReportRows($this, $state))->pluck('id')->all();

    expect($ids(['filterModel' => ['registry' => ['filterType' => 'set', 'values' => ['Acme Spa']]]]))->toBe([$acme->id])
        ->and($ids(['filterModel' => ['commercial' => ['filterType' => 'set', 'values' => [$team['commercial']->name]]]]))->toBe([$other->id])
        ->and($ids(['search' => 'Antincendio']))->toBe([$other->id])
        ->and($ids(['search' => $acme->code]))->toBe([$acme->id])
        ->and($ids(['filterModel' => ['city' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'Rom']]]))->toBe([$other->id]);

    $total = $this->postJson('/api/tables/agent-sales-report/rows', [
        'startRow' => 0, 'endRow' => 25, 'filterModel' => ['registry' => ['filterType' => 'set', 'values' => ['Acme Spa']]],
    ])->assertOk()->json('pagination.total');

    expect($total)->toBe(1);
});

it('lists the registry names as distinct values of the registry column', function () {
    agentSalesReportQuote(agentSalesReportRegistry('Acme Spa'));
    agentSalesReportQuote(agentSalesReportRegistry('Beta Srl', 'Roma'));
    Sanctum::actingAs(agentSalesReportActor());

    $values = $this->postJson('/api/tables/agent-sales-report/values', ['columnId' => 'registry'])->assertOk()->json('data.values');

    expect($values)->toContain('Acme Spa', 'Beta Srl');
});

it('does not run a query per row (no lazy loading) across a page', function () {
    foreach (range(1, 6) as $index) {
        agentSalesReportTeam(agentSalesReportQuote(agentSalesReportRegistry("Cliente {$index}")));
    }
    Sanctum::actingAs(agentSalesReportActor());

    expect(Quote::query()->count())->toBe(6)
        ->and(agentSalesReportRows($this))->toHaveCount(6);
});
