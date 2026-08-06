<?php

use App\Models\BusinessFunction;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\RewardType;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// POST /api/request-management (spec 0057): creates the Opportunity behind a
// new "Gestione Richieste" row, gated by `request-management.create` — the
// same permission catalogue as the rest of this module (AC-001..AC-010).

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

// ---------------------------------------------------------------------------
// AC-001 — 403 without the permission
// ---------------------------------------------------------------------------

it('AC-001: POST without request-management.create -> 403, no row created', function () {
    $actor = requestManagementCreatorWith([]);
    $registry = Registry::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'product_lines' => oneProductLine(),
        'source_id' => aSourceId(),
    ])->assertForbidden();

    expect(Opportunity::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-002 — existing registry branch
// ---------------------------------------------------------------------------

it('AC-002: POST with registry_id + product_lines -> 201, attached to that registry, its anagrafica untouched', function () {
    $actor = requestManagementCreatorWith(['create']);
    $registry = Registry::factory()->withPersonalData()->create();
    $originalName = $registry->name;
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'product_lines' => oneProductLine(),
        'source_id' => aSourceId(),
    ])->assertCreated();

    $opportunityId = $response->json('data.id');
    $this->assertDatabaseHas('opportunities', ['id' => $opportunityId, 'registry_id' => $registry->id]);
    expect($registry->fresh()->name)->toBe($originalName);
});

// ---------------------------------------------------------------------------
// AC-003 — new client branch (identity + contacts + address)
// ---------------------------------------------------------------------------

it('AC-003: POST with client_identity + contacts + address -> 201, new Registry+PersonalData created, name derived', function () {
    $actor = requestManagementCreatorWith(['create']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', [
        'client_identity' => [
            'type' => 'individual',
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
        ],
        'client_contacts' => [
            ['type' => 'phone', 'value' => '3331234567', 'is_primary' => true],
        ],
        'client_address' => ['line1' => 'Via Roma 1'],
        'product_lines' => oneProductLine(),
        'source_id' => aSourceId(),
    ])->assertCreated();

    $opportunity = Opportunity::with('registry.personalData.contacts', 'registry.personalData.addresses')->findOrFail($response->json('data.id'));
    $registry = $opportunity->registry;

    expect($registry)->not->toBeNull();
    $card = $registry->personalData;
    expect($card)->not->toBeNull();
    expect($card->first_name)->toBe('Mario');
    expect($card->last_name)->toBe('Rossi');
    expect($card->contacts)->toHaveCount(1);
    expect($card->contacts->first()->value)->toBe('3331234567');
    expect($card->addresses)->toHaveCount(1);
    expect($card->addresses->first()->line1)->toBe('Via Roma 1');
    // registries.name is derived from the card (RegistryProfileWriter), same
    // precedent as POST /api/registries.
    expect($registry->name)->toBe($card->fresh()->full_name);
});

// ---------------------------------------------------------------------------
// AC-004 / AC-005 — the D-2 XOR
// ---------------------------------------------------------------------------

it('AC-004: POST with registry_id AND client_identity together -> 422, no row created', function () {
    $actor = requestManagementCreatorWith(['create']);
    $registry = Registry::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'client_identity' => ['type' => 'individual', 'first_name' => 'Mario', 'last_name' => 'Rossi'],
        'product_lines' => oneProductLine(),
    ])->assertStatus(422)->assertJsonValidationErrors('registry_id');

    expect(Opportunity::count())->toBe(0);
});

it('AC-005: POST with neither registry_id nor client_identity -> 422, no row created', function () {
    $actor = requestManagementCreatorWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', [
        'product_lines' => oneProductLine(),
    ])->assertStatus(422)->assertJsonValidationErrors('registry_id');

    expect(Opportunity::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-006 — product_lines validation
// ---------------------------------------------------------------------------

it('AC-006: POST without product_lines -> 422, no row created', function () {
    $actor = requestManagementCreatorWith(['create']);
    $registry = Registry::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', ['registry_id' => $registry->id])
        ->assertStatus(422)->assertJsonValidationErrors('product_lines');

    expect(Opportunity::count())->toBe(0);
});

it('AC-006: POST with a category not belonging to the chosen business function -> 422, no row created', function () {
    $actor = requestManagementCreatorWith(['create']);
    $registry = Registry::factory()->create();
    $businessFunction = BusinessFunction::factory()->create();
    $otherBusinessFunction = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $otherBusinessFunction->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'product_lines' => [['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id]],
    ])->assertStatus(422)->assertJsonValidationErrors('product_lines.0.business_function_id');

    expect(Opportunity::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-007 — name is derived
// ---------------------------------------------------------------------------

it('AC-007: the created opportunity name is OPP_{id}', function () {
    $actor = requestManagementCreatorWith(['create']);
    $registry = Registry::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'product_lines' => oneProductLine(),
        'source_id' => aSourceId(),
    ])->assertCreated();

    $opportunity = Opportunity::findOrFail($response->json('data.id'));
    expect($opportunity->name)->toBe('OPP_'.$opportunity->id);
    expect($response->json('data.name'))->toBe('OPP_'.$opportunity->id);
});

// ---------------------------------------------------------------------------
// Initial attribution (user directive 2026-07-24): Fonte, Segnalatore and the
// reward assignments accepted at create — same fields/semantics the work panel
// already carries, set up front on the created Opportunity.
// ---------------------------------------------------------------------------

it('creates with source_id, reporter_id and rewards -> 201, all persisted and the reward targets the reporter', function () {
    $actor = requestManagementCreatorWith(['create']);
    $registry = Registry::factory()->create();
    $source = Source::factory()->create();
    $reporter = Referent::factory()->create();
    $rewardType = RewardType::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'product_lines' => oneProductLine(),
        'source_id' => $source->id,
        'reporter_id' => $reporter->id,
        'rewards' => [['reward_type_id' => $rewardType->id]],
    ])->assertCreated();

    $opportunity = Opportunity::with('rewards')->findOrFail($response->json('data.id'));
    expect($opportunity->source_id)->toBe($source->id);
    expect($opportunity->reporter_id)->toBe($reporter->id);
    expect($opportunity->rewards)->toHaveCount(1);
    $reward = $opportunity->rewards->first();
    expect($reward->reward_type_id)->toBe($rewardType->id);
    // D-3: the beneficiary is always the opportunity's reporter, never chosen.
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
});

// ---------------------------------------------------------------------------
// GA2 "Operatore" at creation (user directive 2026-07-29): a supervisory act,
// gated by `request-management.assignOperator` ON TOP of `create`.
// ---------------------------------------------------------------------------

it('creates with operator_id -> 201, the user lands on the GA2 pivot slot', function () {
    $actor = requestManagementCreatorWith(['create', 'assignOperator']);
    $registry = Registry::factory()->create();
    $operator = User::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'product_lines' => oneProductLine(),
        'source_id' => aSourceId(),
        'operator_id' => $operator->id,
    ])->assertCreated();

    $opportunity = Opportunity::findOrFail($response->json('data.id'));
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
});

// An ABSENT operator_id is not "no operator": it defaults to the creating
// actor (user directive 2026-08-04) — see
// RequestManagementCreateActorDefaultsTest for the whole default block.
it('falls back to the creating actor as GA2 when operator_id is absent', function () {
    $actor = requestManagementCreatorWith(['create']);
    $registry = Registry::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'product_lines' => oneProductLine(),
        'source_id' => aSourceId(),
    ])->assertCreated();

    $opportunity = Opportunity::findOrFail($response->json('data.id'));
    expect($opportunity->operatorManager()?->id)->toBe($actor->id);
});

// ---------------------------------------------------------------------------
// Sede operativa at creation (user directive 2026-07-31): the same field the
// work panel edits, and what scopes the operator list the form offers.
//
// Submitting it needs `operational-sites.viewAny` ON TOP of `create` (user
// directive 2026-08-03), the same ability the field's own ceiling hangs off in
// RequestManagementAuthorization — creation resolves no field permission, so
// the restriction is enforced in the controller instead.
// ---------------------------------------------------------------------------

it('creates with operational_site_id -> 201, the site is persisted on the request', function () {
    $actor = requestManagementCreatorWith(['create']);
    $actor->givePermissionTo(Permission::findOrCreate('operational-sites.viewAny'));
    $registry = Registry::factory()->create();
    $site = OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'product_lines' => oneProductLine(),
        'source_id' => aSourceId(),
        'operational_site_id' => $site->id,
    ])->assertCreated();

    expect(Opportunity::findOrFail($response->json('data.id'))->operational_site_id)->toBe($site->id);
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
});

// Absent falls back to the actor's OWN Sede (user directive 2026-08-04); this
// actor has no employment profile, so there is none to fall back to.
it('creates without a site when operational_site_id is absent and the actor has no Sede', function () {
    $actor = requestManagementCreatorWith(['create']);
    $registry = Registry::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'product_lines' => oneProductLine(),
        'source_id' => aSourceId(),
    ])->assertCreated();

    expect(Opportunity::findOrFail($response->json('data.id'))->operational_site_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-010 — response shape is the same RequestManagementResource as the GET
// ---------------------------------------------------------------------------

it('AC-010: the 201 response is a full RequestManagementResource, matching the GET shape', function () {
    $actor = requestManagementCreatorWith(['create', 'view', 'viewAll']);
    $registry = Registry::factory()->create();
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'product_lines' => oneProductLine(),
        'source_id' => aSourceId(),
    ])->assertCreated();

    $opportunityId = $created->json('data.id');
    $created->assertJsonStructure([
        'success', 'message', 'permissions',
        'data' => ['id', 'name', 'registry', 'product_lines', 'status', 'client_identity', 'client_contacts', 'client_address'],
    ]);

    $shown = $this->getJson("/api/request-management/{$opportunityId}")->assertOk();
    expect(array_keys($created->json('data')))->toBe(array_keys($shown->json('data')));
});
