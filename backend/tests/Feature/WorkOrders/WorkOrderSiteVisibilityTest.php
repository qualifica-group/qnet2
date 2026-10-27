<?php

use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrders\WorkOrderVisibilityScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/*
|--------------------------------------------------------------------------
| Spec 0316 — the `work-orders.viewSite` middle tier of the Commessa scope
|--------------------------------------------------------------------------
|
| Members (Responsabili/Partecipanti) OR, with `work-orders.viewSite`, the
| commesse with at least one source offer (`quote_id`, or the offers of the
| pivot lines) located in one of the actor's Sedi (physical or remote).
*/

uses(RefreshDatabase::class);

/**
 * @param  array<int, string>  $abilities
 */
function siteWorkOrderActor(array $abilities, ?OperationalSite $physical = null, OperationalSite ...$remote): User
{
    foreach (['viewAny', 'view', 'viewAll', 'viewSite', 'viewCosts', 'viewContractData', 'viewInvoices', 'viewEmails'] as $ability) {
        Permission::findOrCreate("work-orders.{$ability}");
    }

    $user = User::factory()->create();

    foreach ($abilities as $ability) {
        $user->givePermissionTo("work-orders.{$ability}");
    }

    $factory = EmploymentProfile::factory();

    if ($physical !== null) {
        $factory = $factory->physicalSite($physical);
    }

    if ($remote !== []) {
        $factory = $factory->remoteSites(...$remote);
    }

    $factory->create(['user_id' => $user->id]);

    return $user;
}

function siteWorkOrder(string $title, ?OperationalSite $site): WorkOrder
{
    return WorkOrder::factory()->create([
        'title' => $title,
        'quote_id' => Quote::factory()->create(['operational_site_id' => $site?->id])->id,
    ]);
}

/**
 * Multi-offer commessa (quote_id NULL): one pivot line per given site.
 */
function siteMultiQuoteWorkOrder(string $title, ?OperationalSite ...$sites): WorkOrder
{
    $workOrder = WorkOrder::factory()->create(['title' => $title, 'quote_id' => null]);

    foreach ($sites as $site) {
        $quote = Quote::factory()->create(['operational_site_id' => $site?->id]);
        $workOrder->quoteLines()->attach(QuoteLine::factory()->create(['quote_id' => $quote->id])->id);
    }

    return $workOrder;
}

/**
 * @return array<int, string>
 */
function siteWorkOrderGridTitles(): array
{
    $items = test()->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 50])
        ->assertOk()->json('items');

    return collect($items)->pluck('title')->sort()->values()->all();
}

it('AC-020: viewSite lists the members commesse plus those whose offer is in the actor site', function () {
    $mine = OperationalSite::factory()->create();
    $other = OperationalSite::factory()->create();
    $actor = siteWorkOrderActor(['viewAny', 'view', 'viewSite'], $mine);

    siteWorkOrder('In sede', $mine);
    siteWorkOrder('Altra sede', $other);
    siteWorkOrder('Membro', $other)->supervisors()->attach($actor->id);

    Sanctum::actingAs($actor);

    expect(siteWorkOrderGridTitles())->toBe(['In sede', 'Membro']);
});

it('AC-020: an offer without a site never matches the site tier', function () {
    $mine = OperationalSite::factory()->create();
    $actor = siteWorkOrderActor(['viewAny', 'view', 'viewSite'], $mine);

    siteWorkOrder('Senza sede', null);

    Sanctum::actingAs($actor);

    expect(siteWorkOrderGridTitles())->toBe([]);
});

it('AC-020: a remote site membership counts as the actor site', function () {
    $physical = OperationalSite::factory()->create();
    $remote = OperationalSite::factory()->create();
    $actor = siteWorkOrderActor(['viewAny', 'view', 'viewSite'], $physical, $remote);

    siteWorkOrder('Sede remota', $remote);

    Sanctum::actingAs($actor);

    expect(siteWorkOrderGridTitles())->toBe(['Sede remota']);
});

it('AC-020: viewSite without view lists nothing and without viewSite the site tier is off', function () {
    $mine = OperationalSite::factory()->create();
    siteWorkOrder('In sede', $mine);

    $onlyView = siteWorkOrderActor(['viewAny', 'view'], $mine);
    Sanctum::actingAs($onlyView);
    expect(siteWorkOrderGridTitles())->toBe([]);

    $onlySite = siteWorkOrderActor(['viewAny', 'viewSite'], $mine);
    Sanctum::actingAs($onlySite);
    $this->getJson('/api/work-orders/'.WorkOrder::query()->value('id'))->assertForbidden();
});

it('AC-021: a multi-offer commessa matches when at least one pivot offer is in the actor site', function () {
    $mine = OperationalSite::factory()->create();
    $other = OperationalSite::factory()->create();
    $actor = siteWorkOrderActor(['viewAny', 'view', 'viewSite'], $mine);

    siteMultiQuoteWorkOrder('Una in sede', $other, $mine);
    siteMultiQuoteWorkOrder('Nessuna in sede', $other, null);

    Sanctum::actingAs($actor);

    expect(siteWorkOrderGridTitles())->toBe(['Una in sede']);
});

it('AC-021: the record verdict agrees with the grid on a freshly loaded commessa (query fallback)', function () {
    $mine = OperationalSite::factory()->create();
    $actor = siteWorkOrderActor(['view', 'viewSite'], $mine);

    $mono = siteWorkOrder('Mono', $mine);
    $multi = siteMultiQuoteWorkOrder('Multi', $mine);
    $out = siteMultiQuoteWorkOrder('Fuori', OperationalSite::factory()->create());

    expect(WorkOrderVisibilityScope::isVisibleTo($actor, WorkOrder::find($mono->id)))->toBeTrue()
        ->and(WorkOrderVisibilityScope::isVisibleTo($actor, WorkOrder::find($multi->id)))->toBeTrue()
        ->and(WorkOrderVisibilityScope::isVisibleTo($actor, WorkOrder::find($out->id)))->toBeFalse();
});

it('AC-022: detail and sub-resources are 200 in-site and 403 out of scope', function () {
    $mine = OperationalSite::factory()->create();
    $actor = siteWorkOrderActor(['viewAny', 'view', 'viewSite', 'viewCosts', 'viewContractData', 'viewInvoices', 'viewEmails'], $mine);

    $in = siteWorkOrder('In sede', $mine);
    $out = siteWorkOrder('Fuori', OperationalSite::factory()->create());

    Sanctum::actingAs($actor);

    foreach (['', '/costs', '/contract-data', '/invoices', '/emails'] as $suffix) {
        $this->getJson("/api/work-orders/{$in->id}{$suffix}")->assertOk();
        $this->getJson("/api/work-orders/{$out->id}{$suffix}")->assertForbidden();
    }
});

it('AC-022: for-select equals the grid for a site-tier actor', function () {
    $mine = OperationalSite::factory()->create();
    $actor = siteWorkOrderActor(['viewAny', 'view', 'viewSite'], $mine);

    siteWorkOrder('In sede', $mine);
    siteMultiQuoteWorkOrder('Multi in sede', $mine);
    siteWorkOrder('Fuori', OperationalSite::factory()->create());

    Sanctum::actingAs($actor);

    $picker = collect($this->getJson('/api/work-orders/for-select')->assertOk()->json('items'))->pluck('id')->sort()->values()->all();
    $grid = collect($this->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 50])->json('items'))->pluck('id')->sort()->values()->all();

    expect($picker)->toBe($grid)->and($picker)->toHaveCount(2);
});

it('AC-023: the mentionable set includes viewSite holders of the offer site', function () {
    Permission::findOrCreate('notes.create');
    $mine = OperationalSite::factory()->create();
    $other = OperationalSite::factory()->create();

    $member = siteWorkOrderActor(['view'], $other);
    $inSite = siteWorkOrderActor(['view', 'viewSite'], $mine);
    $siteWrongPermission = siteWorkOrderActor(['view'], $mine);
    $siteOtherSite = siteWorkOrderActor(['view', 'viewSite'], $other);

    $workOrder = siteMultiQuoteWorkOrder('Menzioni', $mine);
    $workOrder->supervisors()->attach($member->id);

    Sanctum::actingAs($member);

    $ids = collect($this->getJson("/api/notes/mentionable-users?entity_type=work-orders&entity_id={$workOrder->id}")
        ->assertOk()->json('items'))->pluck('id')->sort()->values()->all();

    expect($ids)->toContain($member->id, $inSite->id)
        ->and($ids)->not->toContain($siteWrongPermission->id, $siteOtherSite->id);
});
