<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0365 D-5: the legacy "filtro salvato" is the generic filter-views feature of the domain.
 */
uses(RefreshDatabase::class);

it('saves a view, lists it, and its filterModel narrows the rows', function () {
    $acme = agentSalesReportQuote(agentSalesReportRegistry('Acme Spa'));
    agentSalesReportQuote(agentSalesReportRegistry('Beta Srl', 'Roma'));
    Sanctum::actingAs(agentSalesReportActor());
    $filters = ['registry' => ['filterType' => 'set', 'values' => ['Acme Spa']]];

    $this->postJson('/api/tables/agent-sales-report/filter-views', [
        'name' => 'Solo Acme', 'visibility' => 'private', 'filters' => $filters,
    ])->assertSuccessful();

    $view = collect($this->getJson('/api/tables/agent-sales-report/filter-views')->assertOk()->json('data'))->firstWhere('name', 'Solo Acme');
    expect($view)->not->toBeNull();

    $ids = collect($this->postJson('/api/tables/agent-sales-report/rows', [
        'startRow' => 0, 'endRow' => 25, 'filterModel' => $view['filters'],
    ])->assertOk()->json('items'))->pluck('id')->all();

    expect($ids)->toBe([$acme->id]);
});

it('answers 403 to reading and saving views without agent-sales-report.view', function () {
    Sanctum::actingAs(User::factory()->create()->givePermissionTo(Permission::findOrCreate('quotes.viewAny', 'web')));

    $this->getJson('/api/tables/agent-sales-report/filter-views')->assertForbidden();
    $this->postJson('/api/tables/agent-sales-report/filter-views', [
        'name' => 'x', 'visibility' => 'private', 'filters' => [],
    ])->assertForbidden();
});
