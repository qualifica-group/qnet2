<?php

use App\Enums\WorkflowStatusGroup;
use App\Models\BusinessFunction;
use App\Models\ExportRun;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use App\Services\RequestManagement\Report\ReportOperatorFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0108 — GET /report/operators (the picker's own source and the shared
// allow-list) plus `operator_keys` validation on both consumers:
// AC-007..AC-013 and AC-017.

uses(RefreshDatabase::class);

// The report's POST dispatches its generation job, and QUEUE_CONNECTION is
// `sync` under phpunit.xml: without this the job would really run and write
// real files while these tests only care about validation and frozen state.
beforeEach(function () {
    Queue::fake();
});

if (! function_exists('operatorsEndpointTree')) {
    /**
     * @return array<string, ProductCategory>
     */
    function operatorsEndpointTree(): array
    {
        $formazione = ProductCategory::factory()->create(['name' => 'Formazione']);
        $tree = [
            'gol' => ProductCategory::factory()->childOf($formazione)->create(['name' => 'GOL']),
            'autoimpiego' => ProductCategory::factory()->childOf($formazione)->create(['name' => 'Autoimpiego']),
            'yisu' => ProductCategory::factory()->childOf($formazione)->create(['name' => 'Yisu']),
            'autofinanziato' => ProductCategory::factory()->childOf($formazione)->create(['name' => 'Autofinanziato']),
            'consulenza' => ProductCategory::factory()->create(['name' => 'Consulenza']),
            'apl' => ProductCategory::factory()->create(['name' => 'APL']),
        ];

        return $tree;
    }
}

if (! function_exists('operatorsEndpointActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function operatorsEndpointActor(array $abilities): User
    {
        foreach (['report', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('operatorsEndpointQuote')) {
    function operatorsEndpointQuote(ProductCategory $category, ?int $operatorId, ?string $createdAt = null): Quote
    {
        $opportunity = Opportunity::factory()->create();
        OpportunityProductLine::factory()->create([
            'opportunity_id' => $opportunity->id,
            'business_function_id' => BusinessFunction::factory()->create()->id,
            'product_category_id' => $category->id,
        ]);

        $status = QuoteWorkflowStatus::factory()->global()->create([
            'system_key' => null,
            'group' => WorkflowStatusGroup::Open,
        ]);

        $quote = Quote::factory()->create([
            'opportunity_id' => $opportunity->id,
            'quote_workflow_status_id' => $status->id,
        ]);

        $quote->forceFill(array_filter([
            'operator_id' => $operatorId,
            'created_at' => $createdAt,
        ], static fn ($value): bool => $value !== null))->save();

        return $quote;
    }
}

if (! function_exists('operatorsDashboardQuery')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function operatorsDashboardQuery(array $overrides = []): array
    {
        return array_merge([
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-30',
            'category_keys' => array_keys((array) config('request-management-report.branches')),
            'row_mode' => 'all',
        ], $overrides);
    }
}

if (! function_exists('operatorsReportPayload')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function operatorsReportPayload(array $overrides = []): array
    {
        return array_merge(operatorsDashboardQuery(), ['format' => 'csv'], $overrides);
    }
}

// ---------------------------------------------------------------------------
// AC-010 / AC-012 — the picker's own endpoint
// ---------------------------------------------------------------------------

it('lists the in-scope GA2 sorted by name, for an authorized actor (AC-010)', function () {
    $tree = operatorsEndpointTree();
    $zoe = User::factory()->create(['name' => 'Zoe Bianchi']);
    $ada = User::factory()->create(['name' => 'Ada Rossi']);
    operatorsEndpointQuote($tree['gol'], $zoe->id);
    operatorsEndpointQuote($tree['consulenza'], $ada->id);

    Sanctum::actingAs(operatorsEndpointActor(['report', 'viewAll']));

    $response = $this->getJson('/api/request-management/report/operators')->assertOk();

    expect($response->json('data.operators'))->toBe([
        ['key' => (string) $ada->id, 'label' => 'Ada Rossi'],
        ['key' => (string) $zoe->id, 'label' => 'Zoe Bianchi'],
    ]);
});

it('403s without request-management.report (AC-010)', function () {
    operatorsEndpointTree();
    Sanctum::actingAs(operatorsEndpointActor([]));

    $this->getJson('/api/request-management/report/operators')->assertForbidden();
});

it('appends "Non assegnato" last, and only when such a request exists (AC-012)', function () {
    $tree = operatorsEndpointTree();
    $ada = User::factory()->create(['name' => 'Ada Rossi']);
    operatorsEndpointQuote($tree['gol'], $ada->id);

    Sanctum::actingAs(operatorsEndpointActor(['report', 'viewAll']));

    // Accept-Language drives the locale (SetLocale middleware), so this also
    // proves the label comes from the report's OWN it/ catalogue — the same
    // one the CSV's "Non assegnato" row reads (D-6), not a second one.
    $italian = ['Accept-Language' => 'it'];

    expect($this->getJson('/api/request-management/report/operators', $italian)->json('data.operators'))
        ->toBe([['key' => (string) $ada->id, 'label' => 'Ada Rossi']]);

    operatorsEndpointQuote($tree['gol'], null);

    expect($this->getJson('/api/request-management/report/operators', $italian)->json('data.operators'))->toBe([
        ['key' => (string) $ada->id, 'label' => 'Ada Rossi'],
        ['key' => ReportOperatorFilter::UNASSIGNED_KEY, 'label' => 'Non assegnato'],
    ]);
});

// ---------------------------------------------------------------------------
// AC-011 — all-time, category-agnostic, but scoped
// ---------------------------------------------------------------------------

it('is neither date- nor category-filtered (AC-011)', function () {
    $tree = operatorsEndpointTree();
    $old = User::factory()->create(['name' => 'Aaa Vecchia']);
    $apl = User::factory()->create(['name' => 'Bbb Apl']);
    operatorsEndpointQuote($tree['gol'], $old->id, '2019-01-01 09:00:00');
    operatorsEndpointQuote($tree['apl'], $apl->id);

    Sanctum::actingAs(operatorsEndpointActor(['report', 'viewAll']));

    expect(array_column($this->getJson('/api/request-management/report/operators')->json('data.operators'), 'key'))
        ->toBe([(string) $old->id, (string) $apl->id]);
});

it('never lists an operator outside the actor own perimeter (AC-011)', function () {
    $tree = operatorsEndpointTree();
    $stranger = User::factory()->create(['name' => 'Zzz Estranea']);
    $actor = operatorsEndpointActor(['report']); // no viewAll: scoped to their own requests

    operatorsEndpointQuote($tree['gol'], $stranger->id);
    operatorsEndpointQuote($tree['gol'], $actor->id);

    Sanctum::actingAs($actor);

    expect(array_column($this->getJson('/api/request-management/report/operators')->json('data.operators'), 'key'))
        ->toBe([(string) $actor->id]);
});

// ---------------------------------------------------------------------------
// AC-007 / AC-008 — validation of operator_keys on both consumers
// ---------------------------------------------------------------------------

it('422s on an unknown operator key, on both endpoints (AC-007)', function () {
    $tree = operatorsEndpointTree();
    $ada = User::factory()->create(['name' => 'Ada Rossi']);
    operatorsEndpointQuote($tree['gol'], $ada->id);

    Sanctum::actingAs(operatorsEndpointActor(['report', 'viewAll']));

    $keys = [(string) $ada->id, 'not-an-operator'];

    $this->getJson('/api/request-management/report/dashboard?'.http_build_query(operatorsDashboardQuery(['operator_keys' => $keys])))
        ->assertStatus(422)
        ->assertJsonValidationErrors('operator_keys.1');

    $this->postJson('/api/request-management/report', operatorsReportPayload(['operator_keys' => $keys]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('operator_keys.1');

    expect(ExportRun::query()->count())->toBe(0);
});

it('422s on an empty operator_keys array (AC-007)', function () {
    operatorsEndpointTree();
    Sanctum::actingAs(operatorsEndpointActor(['report', 'viewAll']));

    // JSON body only: an empty array CANNOT be expressed in a query string
    // (`http_build_query` drops the key entirely), so on the dashboard's GET
    // an empty selection is indistinguishable from an absent one and means
    // "every operator" (D-2). That is the client's own gate to enforce, and
    // it does — no request is fired with nothing selected (AC-044).
    $this->postJson('/api/request-management/report', operatorsReportPayload(['operator_keys' => []]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('operator_keys');
});

it('422s when a scoped actor names an operator outside their perimeter (AC-008)', function () {
    $tree = operatorsEndpointTree();
    $stranger = User::factory()->create(['name' => 'Zzz Estranea']);
    $actor = operatorsEndpointActor(['report']); // no viewAll

    operatorsEndpointQuote($tree['gol'], $stranger->id);
    operatorsEndpointQuote($tree['gol'], $actor->id);

    Sanctum::actingAs($actor);

    $this->getJson('/api/request-management/report/dashboard?'.http_build_query(
        operatorsDashboardQuery(['operator_keys' => [(string) $stranger->id]])
    ))->assertStatus(422)->assertJsonValidationErrors('operator_keys.0');
});

// ---------------------------------------------------------------------------
// AC-009 — the echo, and the frozen run
// ---------------------------------------------------------------------------

it('echoes operator_keys as sent, and null when it was not sent (AC-009)', function () {
    $tree = operatorsEndpointTree();
    $ada = User::factory()->create(['name' => 'Ada Rossi']);
    operatorsEndpointQuote($tree['gol'], $ada->id);

    Sanctum::actingAs(operatorsEndpointActor(['report', 'viewAll']));

    $selected = [(string) $ada->id, ReportOperatorFilter::UNASSIGNED_KEY];
    operatorsEndpointQuote($tree['gol'], null); // makes "unassigned" a legal key

    $this->getJson('/api/request-management/report/dashboard?'.http_build_query(
        operatorsDashboardQuery(['operator_keys' => $selected])
    ))->assertOk()->assertJsonPath('data.applied.operator_keys', $selected);

    $this->getJson('/api/request-management/report/dashboard?'.http_build_query(operatorsDashboardQuery()))
        ->assertOk()
        ->assertJsonPath('data.applied.operator_keys', null);
});

it('freezes operator_keys in the run state only when sent (AC-015)', function () {
    $tree = operatorsEndpointTree();
    $ada = User::factory()->create(['name' => 'Ada Rossi']);
    operatorsEndpointQuote($tree['gol'], $ada->id);

    Sanctum::actingAs(operatorsEndpointActor(['report', 'viewAll']));

    $this->postJson('/api/request-management/report', operatorsReportPayload(['operator_keys' => [(string) $ada->id]]))
        ->assertCreated();

    expect(ExportRun::query()->latest('id')->first()->state['operator_keys'])->toBe([(string) $ada->id]);

    $this->postJson('/api/request-management/report', operatorsReportPayload())->assertCreated();

    expect(ExportRun::query()->latest('id')->first()->state)->not->toHaveKey('operator_keys');
});

// ---------------------------------------------------------------------------
// AC-013 / AC-017 — routing and no throttle
// ---------------------------------------------------------------------------

it('resolves report/operators to its own action, not to show(exportRun) (AC-013)', function () {
    $tree = operatorsEndpointTree();
    operatorsEndpointQuote($tree['gol'], User::factory()->create()->id);

    Sanctum::actingAs(operatorsEndpointActor(['report', 'viewAll']));

    // A real round-trip: swallowed by the wildcard it would 404 (no such run).
    $this->getJson('/api/request-management/report/operators')
        ->assertOk()
        ->assertJsonStructure(['data' => ['operators']]);
});

it('carries no throttle middleware (AC-017)', function () {
    $route = collect(Route::getRoutes())
        ->first(fn ($candidate) => $candidate->uri() === 'api/request-management/report/operators');

    expect($route)->not->toBeNull()
        ->and(collect($route->gatherMiddleware())->filter(fn (string $m): bool => str_starts_with($m, 'throttle'))->all())
        ->toBe([]);
});
