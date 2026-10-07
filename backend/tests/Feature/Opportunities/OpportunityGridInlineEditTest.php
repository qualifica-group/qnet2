<?php

declare(strict_types=1);

use App\Models\Opportunity;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0206: the opportunities grid edits its cells through the form's own
// UpdateOpportunityRequest + OpportunityService (OpportunityCellWriter).

uses(RefreshDatabase::class);

function opportunityGridEditor(): User
{
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("opportunities.{$ability}");
    }

    $actor = User::factory()->create();
    $actor->givePermissionTo(['opportunities.viewAny', 'opportunities.update']);

    return $actor;
}

function patchOpportunityCell(Opportunity $opportunity, string $column, mixed $value): TestResponse
{
    return test()->patchJson("/api/tables/opportunities/rows/{$opportunity->id}", ['column' => $column, 'value' => $value]);
}

it('declares every column the form edits as a single field', function () {
    Sanctum::actingAs(opportunityGridEditor());

    $editable = collect($this->getJson('/api/tables/opportunities/columns')->assertOk()->json('data.columns'))
        ->where('editable', true)->pluck('id')->sort()->values()->all();

    expect($editable)->toBe([
        'commercial', 'estimated_value', 'expected_close_date', 'managers', 'name', 'product_category',
        'products_of_interest', 'referent', 'registry', 'source', 'start_date', 'success_probability', 'supervisor',
    ]);
});

it('writes the title through the name writer: a typed title is manual', function () {
    Sanctum::actingAs(opportunityGridEditor());
    $opportunity = Opportunity::factory()->create();

    patchOpportunityCell($opportunity, 'name', 'Rinnovo contratto')->assertOk()->assertJsonPath('data.name', 'Rinnovo contratto');

    expect($opportunity->fresh()->name_is_manual)->toBeTrue();
});

it('applies the form rules: a decimal probability is refused', function () {
    Sanctum::actingAs(opportunityGridEditor());
    $opportunity = Opportunity::factory()->create(['success_probability' => 40]);

    patchOpportunityCell($opportunity, 'success_probability', 50.5)->assertUnprocessable();

    expect($opportunity->fresh()->success_probability)->toBe(40);
});

it('a new anagrafica clears the referent and fills only the empty roles', function () {
    Sanctum::actingAs(opportunityGridEditor());
    $keptCommercial = Referent::factory()->create();
    $opportunity = Opportunity::factory()->create([
        'referent_id' => Referent::factory(),
        'commercial_id' => $keptCommercial->id,
        'supervisor_id' => null,
    ]);
    $inheritedSupervisor = User::factory()->create();
    $manager = User::factory()->create();
    $registry = Registry::factory()->create(['commercial_id' => Referent::factory(), 'supervisor_id' => $inheritedSupervisor->id]);
    $registry->managers()->sync([$manager->id => ['position' => 2]]);
    $opportunity->managers()->detach();

    patchOpportunityCell($opportunity, 'registry', $registry->id)->assertOk();

    $fresh = $opportunity->fresh();
    expect($fresh->registry_id)->toBe($registry->id)
        ->and($fresh->referent_id)->toBeNull()
        ->and($fresh->commercial_id)->toBe($keptCommercial->id)
        ->and($fresh->supervisor_id)->toBe($inheritedSupervisor->id)
        ->and($fresh->managers->mapWithKeys(fn (User $user): array => [$user->id => (int) $user->pivot->position])->all())
        ->toBe([$manager->id => 2]);
});

it('refuses clearing the mandatory anagrafica', function () {
    Sanctum::actingAs(opportunityGridEditor());
    $opportunity = Opportunity::factory()->create();

    patchOpportunityCell($opportunity, 'registry', null)->assertUnprocessable();
});

it('keeps status and business function read-only', function () {
    Sanctum::actingAs(opportunityGridEditor());
    $opportunity = Opportunity::factory()->create();

    patchOpportunityCell($opportunity, 'status', 'won')->assertUnprocessable();
    patchOpportunityCell($opportunity, 'business_function', 'x')->assertUnprocessable();
});
