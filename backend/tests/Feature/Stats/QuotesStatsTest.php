<?php

use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| GET /api/stats/quotes (spec 0151, D-8, AC-007)
|--------------------------------------------------------------------------
|
| The 6 widgets D-8 lists: total, revenue_net, margin_net, won (stats),
| by_status (distribution) and trend. Global counts, no visibility scoping.
*/

function quotesStatsActor(): User
{
    Permission::findOrCreate('quotes.viewAny');

    $user = User::factory()->create();
    $user->givePermissionTo('quotes.viewAny');

    return $user;
}

/**
 * @return array<string, array<string, mixed>>
 */
function quotesStatsWidgets(): array
{
    return collect(test()->getJson('/api/stats/quotes')->assertOk()->json('data.widgets'))->keyBy('key')->all();
}

beforeEach(function () {
    Carbon::setTestNow('2026-09-23 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('computes total, net revenue and net margin over every quote (AC-007)', function () {
    Sanctum::actingAs(quotesStatsActor());
    Quote::factory()->create(['revenue_net' => 1000, 'margin_net' => 300]);
    Quote::factory()->create(['revenue_net' => 500.50, 'margin_net' => 100.25]);

    $widgets = quotesStatsWidgets();

    expect($widgets['total']['value'])->toBe(2)
        ->and($widgets['revenue_net']['value'])->toBe(1500.5)
        ->and($widgets['revenue_net']['format'])->toBe('currency')
        ->and($widgets['margin_net']['value'])->toBe(400.25)
        ->and($widgets['margin_net']['format'])->toBe('currency');
});

it('counts only the quotes on a closed_won status as won (AC-007)', function () {
    Sanctum::actingAs(quotesStatsActor());
    $won = QuoteWorkflowStatus::factory()->global()->system('closed_won')->create();
    $lost = QuoteWorkflowStatus::factory()->global()->system('closed_lost')->create();
    Quote::factory()->create(['quote_workflow_status_id' => $won->id]);
    Quote::factory()->create(['quote_workflow_status_id' => $won->id]);
    Quote::factory()->create(['quote_workflow_status_id' => $lost->id]);

    expect(quotesStatsWidgets()['won']['value'])->toBe(2);
});

it('breaks quotes down by workflow status with its colour (AC-007)', function () {
    Sanctum::actingAs(quotesStatsActor());
    $status = QuoteWorkflowStatus::factory()->global()->create(['name' => 'In trattativa', 'color' => 'blue']);
    Quote::factory()->count(3)->create(['quote_workflow_status_id' => $status->id]);

    $widgets = quotesStatsWidgets();

    expect($widgets['by_status']['total'])->toBe(3)
        ->and($widgets['by_status']['items'])->toBe([
            ['key' => (string) $status->id, 'label' => 'In trattativa', 'value' => 3, 'color' => 'blue'],
        ]);
});

it('places every quote in the monthly creation trend (AC-007)', function () {
    Sanctum::actingAs(quotesStatsActor());
    Quote::factory()->create();

    $points = quotesStatsWidgets()['trend']['points'];

    expect(array_sum(array_column($points, 'value')))->toBe(1);
});

it('returns 403 without quotes.viewAny (AC-007)', function () {
    Permission::findOrCreate('quotes.viewAny');
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/stats/quotes')->assertForbidden();
});

it('returns 401 unauthenticated (AC-007)', function () {
    $this->getJson('/api/stats/quotes')->assertUnauthorized();
});
