<?php

use App\Models\Contract;
use App\Models\Quote;
use App\Services\Contracts\ContractVisibilityScope;
use App\Services\Quotes\QuoteVisibilityScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * The `contracts.viewCompetence` tier (spec 0340): a contract follows its
 * quote's revenue categories, judged with the CONTRACTS' own ability.
 */
uses(RefreshDatabase::class);

function competenceContractIds(): array
{
    return collect(test()->postJson('/api/tables/contracts/rows', ['startRow' => 0, 'endRow' => 100])->assertOk()->json('items'))
        ->pluck('id')->sort()->values()->all();
}

it('AC-006: contracts.viewCompetence shows the contract of a competent quote, quotes.viewCompetence alone does not', function () {
    [$function, $covered] = competenceVisibilityCategory();
    $contract = categoryTabContract([$covered]);
    categoryTabContract([competenceVisibilityCategory()[1]]);

    $withContracts = competenceVisibilityActor('contracts');
    grantCompetence($withContracts, $function, $covered);
    Sanctum::actingAs($withContracts);
    expect(competenceContractIds())->toBe([$contract->id]);
    $this->getJson("/api/contracts/{$contract->id}")->assertOk();

    $quotesOnly = competenceVisibilityActor('contracts', ['viewAny', 'view']);
    competenceVisibilityActor('quotes'); // ensures quotes.viewCompetence exists
    $quotesOnly->givePermissionTo('quotes.viewCompetence');
    grantCompetence($quotesOnly, $function, $covered);
    Sanctum::actingAs($quotesOnly);
    app()->forgetScopedInstances();
    expect(competenceContractIds())->toBe([]);
    $this->getJson("/api/contracts/{$contract->id}", ['Accept-Language' => 'it'])->assertForbidden()
        ->assertJsonPath('message', trans('authorization.contract_out_of_scope', [], 'it'));
});

it('AC-012: viewCompetence without competence adds nothing to the contracts', function () {
    $actor = competenceVisibilityActor('contracts');
    categoryTabContract([competenceVisibilityCategory()[1]]);
    Sanctum::actingAs($actor);

    expect(competenceContractIds())->toBe([]);
});

it('AC-005: a contract on a quote without revenue lines is visible only with the all-categories flag', function () {
    $contract = Contract::factory()->create();

    $scoped = competenceVisibilityActor('contracts');
    [$function, $covered] = competenceVisibilityCategory();
    grantCompetence($scoped, $function, $covered);
    Sanctum::actingAs($scoped);
    expect(competenceContractIds())->toBe([]);

    $wildcard = competenceVisibilityActor('contracts');
    grantCompetenceOnAllCategories($wildcard);
    Sanctum::actingAs($wildcard);
    app()->forgetScopedInstances();
    expect(competenceContractIds())->toBe([$contract->id]);
});

it('AC-013: the tier does not grant update on a contract', function () {
    [$function, $covered] = competenceVisibilityCategory();
    $contract = categoryTabContract([$covered]);
    $actor = competenceVisibilityActor('contracts');
    grantCompetence($actor, $function, $covered);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/contracts/{$contract->id}", [])->assertForbidden();

    $editor = competenceVisibilityActor('contracts', ['viewAny', 'view', 'viewCompetence', 'update']);
    grantCompetence($editor, $function, $covered);
    Sanctum::actingAs($editor);
    app()->forgetScopedInstances();
    expect($this->patchJson("/api/contracts/{$contract->id}", [])->status())->not->toBe(403);
});

it('AC-020: a null actor matches no contract', function () {
    categoryTabContract([competenceVisibilityCategory()[1]]);

    expect(ContractVisibilityScope::scopeToActor(Contract::query(), null)->count())->toBe(0);
});

it('AC-016: the verdict on the contract quote is skipped only by contracts.viewAll', function () {
    [$function, $covered] = competenceVisibilityCategory();
    $bounded = competenceVisibilityActor('contracts');
    grantCompetence($bounded, $function, $covered);
    $bounded->givePermissionTo(Permission::findOrCreate('quotes.viewAll', 'web'));
    $seesAll = competenceVisibilityActor('contracts', ['viewAny', 'view', 'viewCompetence', 'viewAll']);
    grantCompetence($seesAll, $function, $covered);

    expect(QuoteVisibilityScope::withCompetenceVerdict(Quote::query(), $bounded, ContractVisibilityScope::RESOURCE)->toSql())
        ->toContain(QuoteVisibilityScope::COMPETENCE_VERDICT_ATTRIBUTE)
        ->and(QuoteVisibilityScope::withCompetenceVerdict(Quote::query(), $seesAll, ContractVisibilityScope::RESOURCE)->toSql())
        ->not->toContain(QuoteVisibilityScope::COMPETENCE_VERDICT_ATTRIBUTE);
});
