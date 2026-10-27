<?php

use App\Models\Contract;
use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\Quote;
use App\Models\User;
use App\Services\Contracts\ContractVisibilityScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

/**
 * Record visibility of Contratti (spec 0316, D-3): a contract follows its
 * quote, judged with the contracts' own viewSite/viewAll permissions.
 */
uses(RefreshDatabase::class);

function contractVisibilityGridIds(): array
{
    return collect(test()->postJson('/api/tables/contracts/rows', ['startRow' => 0, 'endRow' => 50])->assertOk()->json('items'))
        ->pluck('id')->sort()->values()->all();
}

function contractVisibilityActorAtSite(array $abilities, OperationalSite $site): User
{
    $user = contractScopedUserWith($abilities);
    EmploymentProfile::factory()->for($user)->physicalSite($site)->create();

    return $user;
}

function contractOfQuote(array $quoteAttributes = []): Contract
{
    return Contract::factory()->create(['quote_id' => Quote::factory()->create($quoteAttributes)->id]);
}

it('AC-030: a view-only actor sees only contracts whose quote they supervise or manage', function () {
    $actor = contractScopedUserWith(['viewAny', 'view']);
    $supervised = contractOfQuote(['supervisor_id' => $actor->id]);
    $managed = contractOfQuote();
    $managed->quote->managers()->attach($actor->id, ['position' => 3]);
    contractOfQuote();
    Sanctum::actingAs($actor);

    expect(contractVisibilityGridIds())->toBe(collect([$supervised->id, $managed->id])->sort()->values()->all());
});

it('AC-030: viewSite adds the contracts of quotes at the actor sites, never a quote without a site', function () {
    $site = OperationalSite::factory()->withAddress()->create();
    $actor = contractVisibilityActorAtSite(['viewAny', 'view', 'viewSite'], $site);
    $atSite = contractOfQuote(['operational_site_id' => $site->id]);
    contractOfQuote(['operational_site_id' => OperationalSite::factory()->withAddress()->create()->id]);
    contractOfQuote(['operational_site_id' => null]);
    Sanctum::actingAs($actor);

    expect(contractVisibilityGridIds())->toBe([$atSite->id]);
});

it('AC-030: a remote site membership counts as the actor site', function () {
    $site = OperationalSite::factory()->withAddress()->create();
    $actor = contractScopedUserWith(['viewAny', 'view', 'viewSite']);
    EmploymentProfile::factory()->for($actor)->create()->operationalSites()->attach($site->id);
    $atSite = contractOfQuote(['operational_site_id' => $site->id]);
    Sanctum::actingAs($actor);

    expect(contractVisibilityGridIds())->toBe([$atSite->id]);
});

it('AC-030: without viewSite the site of the actor grants nothing', function () {
    $site = OperationalSite::factory()->withAddress()->create();
    $actor = contractVisibilityActorAtSite(['viewAny', 'view'], $site);
    contractOfQuote(['operational_site_id' => $site->id]);
    Sanctum::actingAs($actor);

    expect(contractVisibilityGridIds())->toBe([]);
});

it('AC-030: viewAll shows every contract', function () {
    $first = contractOfQuote();
    $second = contractOfQuote();
    Sanctum::actingAs(contractScopedUserWith(['viewAny', 'view', 'viewAll']));

    expect(contractVisibilityGridIds())->toBe(collect([$first->id, $second->id])->sort()->values()->all());
});

it('AC-031: the detail is 403 out of scope and 200 in scope; viewAll without view sees nothing', function () {
    $mine = contractOfQuote();
    $other = contractOfQuote();
    $actor = contractScopedUserWith(['viewAny', 'view']);
    $mine->quote->managers()->attach($actor->id, ['position' => 3]);

    Sanctum::actingAs($actor);
    $this->getJson("/api/contracts/{$mine->id}")->assertOk();
    $this->getJson("/api/contracts/{$other->id}")->assertForbidden();

    Sanctum::actingAs(contractScopedUserWith(['viewAny', 'viewAll']));
    $this->getJson("/api/contracts/{$mine->id}")->assertForbidden();
});

it('AC-031: update and the domain actions are 403 out of scope even with the ability', function (string $method, string $suffix, array $payload) {
    $contract = contractOfQuote();
    Sanctum::actingAs(contractScopedUserWith(['viewAny', 'view', 'update', 'validate', 'terminate', 'program', 'changeStatus', 'reactivate']));

    $this->json($method, "/api/contracts/{$contract->id}{$suffix}", $payload)->assertForbidden();
})->with([
    'update' => ['PATCH', '', []],
    'validate' => ['POST', '/validate', []],
    'terminate' => ['POST', '/terminate', ['terminated_at' => '2026-01-01', 'termination_reason' => 'x']],
    'reactivate' => ['POST', '/reactivate', []],
    'change-status' => ['POST', '/change-status', ['contract_status_id' => 1]],
    'programmable-lines' => ['GET', '/programmable-lines', []],
]);

it('AC-031: the policy abilities need visibility on top of the permission', function () {
    $contract = contractOfQuote();
    $actor = contractScopedUserWith(['view', 'update', 'validate', 'terminate', 'program', 'changeStatus', 'reactivate']);

    foreach (['view', 'update', 'validate', 'terminate', 'program', 'changeStatus', 'reactivate'] as $ability) {
        expect($actor->can($ability, $contract))->toBeFalse($ability);
    }

    $contract->quote->update(['supervisor_id' => $actor->id]);

    foreach (['view', 'update', 'validate', 'terminate', 'program', 'changeStatus', 'reactivate'] as $ability) {
        expect($actor->fresh()->can($ability, $contract->fresh()))->toBeTrue($ability);
    }
});

it('AC-031: multi-programmable-lines refuses a selection containing an invisible contract', function () {
    [$visible] = multiQuoteProgramContract();
    [$alsoVisible] = multiQuoteProgramContract();
    [$hidden] = multiQuoteProgramContract();
    $actor = multiQuoteProgramActor(['contracts.program']);
    $actor->revokePermissionTo('contracts.viewAll');
    $visible->quote->update(['supervisor_id' => $actor->id]);
    $alsoVisible->quote->update(['supervisor_id' => $actor->id]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/contracts/multi-programmable-lines?'.http_build_query(['contract_ids' => [$visible->id, $alsoVisible->id]]))->assertOk();
    $this->getJson('/api/contracts/multi-programmable-lines?'.http_build_query(['contract_ids' => [$visible->id, $hidden->id]]))->assertForbidden();
});

it('AC-032: quotes.viewAll grants no contract and contracts.viewAll grants no quote', function () {
    $contract = contractOfQuote();

    $quotesAdmin = contractScopedUserWith(['viewAny', 'view']);
    $contractsAdmin = quoteScopedUserWith(['viewAny', 'view']);
    $quotesAdmin->givePermissionTo('quotes.viewAll');
    $contractsAdmin->givePermissionTo('contracts.viewAll');
    Sanctum::actingAs($quotesAdmin);
    expect(contractVisibilityGridIds())->toBe([]);
    $this->getJson("/api/contracts/{$contract->id}")->assertForbidden();

    Sanctum::actingAs($contractsAdmin);
    $this->getJson("/api/quotes/{$contract->quote_id}")->assertForbidden();
});

it('AC-031: a null actor matches no contract and a super-admin sees all', function () {
    $contract = contractOfQuote();

    expect(ContractVisibilityScope::scopeToActor(Contract::query(), null)->count())->toBe(0);

    $super = User::factory()->create();
    $super->assignRole(Role::findOrCreate('super-admin'));
    expect(ContractVisibilityScope::isVisibleTo($super, $contract))->toBeTrue();
});
