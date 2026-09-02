<?php

use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * POST /api/request-management — Operatore and Sede operativa default to the
 * CREATING ACTOR and the actor's own Sede (user directive 2026-08-04): a
 * request is worked by whoever opened it, from the Sede they belong to, and
 * that holds "a prescindere se il campo lo vede o meno" — an actor without
 * `request-management.assignOperator` / `operational-sites.viewAny` never
 * renders the two controls, so their payload simply omits the keys.
 *
 * A submitted value still wins: those cases live in
 * RequestManagementCreateTest (the two "creates with ..." tests), whose helpers
 * (`requestManagementCreatorWith`, `aSourceId`, `oneProductLine`) are reused
 * here — Pest loads every test file of the suite, and the helpers are guarded
 * by `function_exists`.
 */
uses(RefreshDatabase::class);

/** An actor whose employment profile sits on a freshly created Sede. */
function anActorAtOwnSite(string ...$abilities): array
{
    $actor = requestManagementCreatorWith(['create', ...$abilities]);
    $site = OperationalSite::factory()->withAddress()->create();
    EmploymentProfile::factory()->create(['user_id' => $actor->id, 'operational_site_id' => $site->id]);

    return [$actor, $site];
}

/**
 * @return array<string, mixed>
 */
function aMinimalRequestPayload(): array
{
    return [
        'registry_id' => Registry::factory()->create()->id,
        'product_lines' => oneProductLine(),
        'source_id' => aSourceId(),
    ];
}

it('defaults the GA2 operator to the creating actor when operator_id is absent', function () {
    [$actor] = anActorAtOwnSite();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', aMinimalRequestPayload())->assertCreated();

    // Spec 0087, D-13: `data.id` is the OFFERTA's own id — the default lands
    // SOLELY on its GA2 slot, promoted onto the Opportunity's first FREE
    // manager slot (1, born with none), never its GA2 specifically.
    $quote = Quote::findOrFail($response->json('data.id'));
    expect($quote->operator_id)->toBe($actor->id);
    $this->assertDatabaseHas('opportunity_user', [
        'opportunity_id' => $quote->opportunity_id,
        'user_id' => $actor->id,
        'position' => 1,
    ]);
});

it("defaults the Sede operativa to the actor's own when operational_site_id is absent", function () {
    [$actor, $site] = anActorAtOwnSite();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', aMinimalRequestPayload())->assertCreated();

    expect(Opportunity::findOrFail($response->json('data.id'))->operational_site_id)->toBe($site->id);
});

/**
 * The whole point of the directive: the actor holds NEITHER supervisory
 * ability, so the create form renders neither control and the payload cannot
 * carry either key — the defaults still land.
 */
it('applies both defaults for an actor who may not submit either field', function () {
    [$actor, $site] = anActorAtOwnSite();
    expect($actor->can('request-management.assignOperator'))->toBeFalse()
        ->and($actor->can('operational-sites.viewAny'))->toBeFalse();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', aMinimalRequestPayload())->assertCreated();

    $quote = Quote::findOrFail($response->json('data.id'));
    expect($quote->operator_id)->toBe($actor->id)
        ->and($quote->opportunity->operational_site_id)->toBe($site->id);
});

it('leaves the Sede null when the actor has no employment Sede, still defaulting the operator', function () {
    $actor = requestManagementCreatorWith(['create']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', aMinimalRequestPayload())->assertCreated();

    $quote = Quote::findOrFail($response->json('data.id'));
    expect($quote->operational_site_id)->toBeNull()
        ->and($quote->operator_id)->toBe($actor->id);
});

it('keeps a submitted operator/Sede over the actor defaults', function () {
    [$actor] = anActorAtOwnSite('assignOperator');
    $actor->givePermissionTo(Permission::findOrCreate('operational-sites.viewAny'));
    $otherOperator = User::factory()->create();
    $otherSite = OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', [
        ...aMinimalRequestPayload(),
        // Spec 0097, D-1: the operator is submitted as the OPERATOR slot of
        // the team, no longer as a scalar `operator_id`.
        'manager_slots' => [null, $otherOperator->id],
        'operational_site_id' => $otherSite->id,
    ])->assertCreated();

    $quote = Quote::findOrFail($response->json('data.id'));
    expect($quote->operator_id)->toBe($otherOperator->id)
        ->and($quote->operational_site_id)->toBe($otherSite->id);
});
