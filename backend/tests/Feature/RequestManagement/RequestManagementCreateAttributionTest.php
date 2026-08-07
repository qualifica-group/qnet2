<?php

use App\Models\BusinessFunction;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\RewardType;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// POST /api/request-management (spec 0057, migrated onto the Quote by spec
// 0086 D-5): the attribution block accepted at creation — Fonte, Segnalatore,
// rewards, GA2 "Operatore"/Supervisore and Sede operativa — plus AC-010's
// response-shape contract. Split out of RequestManagementCreateTest
// (engineering.md §6, file-size budget); the D-2 XOR/product_lines/name-
// derivation cases stay there. `data.id` is the OFFERTA (Quote) id (AC-027),
// never the Opportunity's — every fixture below seeds a decoy Opportunity
// first so the two ids can never coincide by construction.

uses(RefreshDatabase::class);

if (! function_exists('requestManagementCreatorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function requestManagementCreatorWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'export', 'viewActivity', 'viewAll', 'assignOperator'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('aSourceId')) {
    /** The Fonte every successful create must carry: mandatory since the user directive 2026-07-29. */
    function aSourceId(): int
    {
        return Source::factory()->create()->id;
    }
}

if (! function_exists('oneProductLine')) {
    /**
     * @return array<int, array{business_function_id: int, product_category_id: int}>
     */
    function oneProductLine(): array
    {
        $businessFunction = BusinessFunction::factory()->create();
        $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);

        return [['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id]];
    }
}

if (! function_exists('decoyOpportunity')) {
    /**
     * A throwaway Opportunity, created before the real POST under test so its
     * id can never coincide with the freshly-created Offerta's own id.
     */
    function decoyOpportunity(): void
    {
        Opportunity::factory()->create();
    }
}

// ---------------------------------------------------------------------------
// Initial attribution (user directive 2026-07-24): Fonte, Segnalatore and the
// reward assignments accepted at create — same fields/semantics the work
// panel already carries. Spec 0086, D-4: the reward beneficiary is the
// OFFERTA's own Segnalatore (`quote.reporter_id`), not the Opportunity's.
// ---------------------------------------------------------------------------

it('creates with source_id, reporter_id and rewards -> 201, all persisted and the reward targets the OFFERTA reporter (D-4)', function () {
    $actor = requestManagementCreatorWith(['create']);
    $registry = Registry::factory()->create();
    $source = Source::factory()->create();
    $reporter = Referent::factory()->create();
    $rewardType = RewardType::factory()->create();
    decoyOpportunity();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'product_lines' => oneProductLine(),
        'source_id' => $source->id,
        'reporter_id' => $reporter->id,
        'rewards' => [['reward_type_id' => $rewardType->id]],
    ])->assertCreated();

    $quote = Quote::with('rewards')->findOrFail($response->json('data.id'));
    expect($quote->opportunity->source_id)->toBe($source->id);
    expect($quote->reporter_id)->toBe($reporter->id);
    expect($quote->rewards)->toHaveCount(1);
    $reward = $quote->rewards->first();
    expect($reward->reward_type_id)->toBe($rewardType->id);
    expect($reward->source_type)->toBe('quote');
    expect($reward->source_id)->toBe($quote->id);
    // D-4: the beneficiary is always the OFFERTA's reporter, never chosen.
    expect($reward->referent_id)->toBe($reporter->id);
});

it('rejects rewards without a reporter_id -> 422 (D-3), no row created', function () {
    $actor = requestManagementCreatorWith(['create']);
    $registry = Registry::factory()->create();
    $rewardType = RewardType::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'product_lines' => oneProductLine(),
        'rewards' => [['reward_type_id' => $rewardType->id]],
    ])->assertStatus(422)->assertJsonValidationErrors('rewards');

    expect(Opportunity::count())->toBe(0);
    expect(Quote::count())->toBe(0);
});

it('rejects a non-existent source_id -> 422, no row created', function () {
    $actor = requestManagementCreatorWith(['create']);
    $registry = Registry::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'product_lines' => oneProductLine(),
        'source_id' => 999999,
    ])->assertStatus(422)->assertJsonValidationErrors('source_id');

    expect(Opportunity::count())->toBe(0);
    expect(Quote::count())->toBe(0);
});

it('rejects a create without a source_id -> 422, no row created', function () {
    $actor = requestManagementCreatorWith(['create']);
    $registry = Registry::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'product_lines' => oneProductLine(),
    ])->assertStatus(422)->assertJsonValidationErrors('source_id');

    expect(Opportunity::count())->toBe(0);
    expect(Quote::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// GA2 "Operatore" at creation (user directive 2026-07-29): a supervisory act,
// gated by `request-management.assignOperator` ON TOP of `create`. Spec
// 0086, D-3/AC-029: the SAME resolved actor lands on `quotes.supervisor_id`
// AND the Opportunity's GA2 pivot slot.
// ---------------------------------------------------------------------------

it('creates with operator_id -> 201, the user lands on quotes.supervisor_id AND the GA2 pivot slot (AC-029)', function () {
    $actor = requestManagementCreatorWith(['create', 'assignOperator']);
    $registry = Registry::factory()->create();
    $operator = User::factory()->create();
    decoyOpportunity();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'product_lines' => oneProductLine(),
        'source_id' => aSourceId(),
        'operator_id' => $operator->id,
    ])->assertCreated();

    $quote = Quote::findOrFail($response->json('data.id'));
    $opportunity = $quote->opportunity;
    expect($quote->supervisor_id)->toBe($operator->id);
    expect($opportunity->operatorManager()?->id)->toBe($operator->id);
    $this->assertDatabaseHas('opportunity_user', [
        'opportunity_id' => $opportunity->id,
        'user_id' => $operator->id,
        'position' => Opportunity::OPERATOR_MANAGER_POSITION,
    ]);
});

it('rejects operator_id from an actor without request-management.assignOperator -> 403, no row created', function () {
    $actor = requestManagementCreatorWith(['create']);
    $registry = Registry::factory()->create();
    $operator = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'product_lines' => oneProductLine(),
        'source_id' => aSourceId(),
        'operator_id' => $operator->id,
    ])->assertForbidden();

    expect(Opportunity::count())->toBe(0);
    expect(Quote::count())->toBe(0);
});

// An ABSENT operator_id is not "no operator": it defaults to the creating
// actor (user directive 2026-08-04) — see
// RequestManagementCreateActorDefaultsTest for the whole default block.
it('falls back to the creating actor as GA2/Supervisore when operator_id is absent', function () {
    $actor = requestManagementCreatorWith(['create']);
    $registry = Registry::factory()->create();
    decoyOpportunity();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'product_lines' => oneProductLine(),
        'source_id' => aSourceId(),
    ])->assertCreated();

    $quote = Quote::findOrFail($response->json('data.id'));
    expect($quote->supervisor_id)->toBe($actor->id);
    expect($quote->opportunity->operatorManager()?->id)->toBe($actor->id);
});

// ---------------------------------------------------------------------------
// Sede operativa at creation (user directive 2026-07-31): the same field the
// work panel edits, and what scopes the operator list the form offers. Spec
// 0086, D-6: now written on the Offerta itself.
//
// Submitting it needs `operational-sites.viewAny` ON TOP of `create` (user
// directive 2026-08-03), the same ability the field's own ceiling hangs off in
// RequestManagementAuthorization — creation resolves no field permission, so
// the restriction is enforced in the controller instead.
// ---------------------------------------------------------------------------

it('creates with operational_site_id -> 201, the site is persisted on the OFFERTA', function () {
    $actor = requestManagementCreatorWith(['create']);
    $actor->givePermissionTo(Permission::findOrCreate('operational-sites.viewAny'));
    $registry = Registry::factory()->create();
    $site = OperationalSite::factory()->withAddress()->create();
    decoyOpportunity();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'product_lines' => oneProductLine(),
        'source_id' => aSourceId(),
        'operational_site_id' => $site->id,
    ])->assertCreated();

    expect(Quote::findOrFail($response->json('data.id'))->operational_site_id)->toBe($site->id);
});

it('rejects operational_site_id from an actor without operational-sites.viewAny -> 403, no row created', function () {
    $actor = requestManagementCreatorWith(['create']);
    $registry = Registry::factory()->create();
    $site = OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'product_lines' => oneProductLine(),
        'source_id' => aSourceId(),
        'operational_site_id' => $site->id,
    ])->assertForbidden();

    expect(Opportunity::count())->toBe(0);
    expect(Quote::count())->toBe(0);
});

it('rejects an operational_site_id that does not exist -> 422', function () {
    $actor = requestManagementCreatorWith(['create']);
    $registry = Registry::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'product_lines' => oneProductLine(),
        'source_id' => aSourceId(),
        'operational_site_id' => 999999,
    ])->assertStatus(422)->assertJsonValidationErrors('operational_site_id');

    expect(Opportunity::count())->toBe(0);
    expect(Quote::count())->toBe(0);
});

// Absent falls back to the actor's OWN Sede (user directive 2026-08-04); this
// actor has no employment profile, so there is none to fall back to.
it('creates without a site when operational_site_id is absent and the actor has no Sede', function () {
    $actor = requestManagementCreatorWith(['create']);
    $registry = Registry::factory()->create();
    decoyOpportunity();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'product_lines' => oneProductLine(),
        'source_id' => aSourceId(),
    ])->assertCreated();

    expect(Quote::findOrFail($response->json('data.id'))->operational_site_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-010 — response shape is the same RequestManagementResource as the GET
// ---------------------------------------------------------------------------

it('AC-010: the 201 response is a full RequestManagementResource, matching the GET shape', function () {
    $actor = requestManagementCreatorWith(['create', 'view', 'viewAll']);
    $registry = Registry::factory()->create();
    decoyOpportunity();
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'product_lines' => oneProductLine(),
        'source_id' => aSourceId(),
    ])->assertCreated();

    $quoteId = $created->json('data.id');
    $created->assertJsonStructure([
        'success', 'message', 'permissions',
        'data' => ['id', 'opportunity_id', 'name', 'registry', 'product_lines', 'status', 'client_identity', 'client_contacts', 'client_address'],
    ]);

    $shown = $this->getJson("/api/request-management/{$quoteId}")->assertOk();
    expect(array_keys($created->json('data')))->toBe(array_keys($shown->json('data')));
});
