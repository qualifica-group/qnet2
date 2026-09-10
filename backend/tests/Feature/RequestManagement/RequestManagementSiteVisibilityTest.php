<?php

use App\Authorization\AssignablePermissionCatalogue;
use App\Models\BusinessFunction;
use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Role;
use App\Models\User;
use App\Services\RequestManagement\RequestManagementScope;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0105 — the THIRD visibility tier of Gestione Richieste:
// `request-management.viewSite` shows the requests of the actor's own Sedi
// operative (physical AND remote, D-4) even where they are not the GA2
// Operatore, without lifting the scope the way `viewAll` does.

uses(RefreshDatabase::class);

if (! function_exists('siteVisibilityActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function siteVisibilityActor(array $abilities): User
    {
        foreach (['viewAny', 'view', 'viewAll', 'viewSite', 'update', 'delete', 'assignOperator', 'viewActivity'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('siteVisibilityMemberOf')) {
    /**
     * Gives $user the employment profile carrying their memberships: one
     * PHYSICAL site (spec 0103 D-3) plus any number of REMOTE ones.
     */
    function siteVisibilityMemberOf(User $user, ?OperationalSite $physical, OperationalSite ...$remote): User
    {
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
}

if (! function_exists('siteVisibilityRows')) {
    /**
     * The ids the request-management grid returns for the acting user.
     *
     * @return array<int, int>
     */
    function siteVisibilityRows(): array
    {
        $response = test()->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 50])->assertOk();

        return collect($response->json('items'))->pluck('id')->all();
    }
}

// ---------------------------------------------------------------------------
// AC-001 / AC-002 / AC-003 — the grid widens to the actor's memberships
// ---------------------------------------------------------------------------

it('rows: a viewSite actor sees the requests of their PHYSICAL Sede they do not operate (AC-001)', function () {
    $site = OperationalSite::factory()->create();
    $otherSite = OperationalSite::factory()->create();
    $actor = siteVisibilityMemberOf(siteVisibilityActor(['viewAny', 'view', 'viewSite']), $site);

    $inMySite = Quote::factory()->create(['operational_site_id' => $site->id]);
    $elsewhere = Quote::factory()->create(['operational_site_id' => $otherSite->id]);

    Sanctum::actingAs($actor);
    $ids = siteVisibilityRows();

    expect($ids)->toContain($inMySite->id)
        ->and($ids)->not->toContain($elsewhere->id);
});

it('rows: a REMOTE membership carries the same visibility as a physical one (AC-002)', function () {
    $site = OperationalSite::factory()->create();
    $actor = siteVisibilityMemberOf(siteVisibilityActor(['viewAny', 'view', 'viewSite']), null, $site);

    $inMySite = Quote::factory()->create(['operational_site_id' => $site->id]);
    $elsewhere = Quote::factory()->create(['operational_site_id' => OperationalSite::factory()->create()->id]);

    Sanctum::actingAs($actor);
    $ids = siteVisibilityRows();

    expect($ids)->toContain($inMySite->id)
        ->and($ids)->not->toContain($elsewhere->id);
});

it('rows: every membership counts, physical and remote together (AC-003)', function () {
    $physical = OperationalSite::factory()->create();
    $remote = OperationalSite::factory()->create();
    $third = OperationalSite::factory()->create();
    $actor = siteVisibilityMemberOf(siteVisibilityActor(['viewAny', 'view', 'viewSite']), $physical, $remote);

    $first = Quote::factory()->create(['operational_site_id' => $physical->id]);
    $second = Quote::factory()->create(['operational_site_id' => $remote->id]);
    $outside = Quote::factory()->create(['operational_site_id' => $third->id]);

    Sanctum::actingAs($actor);
    $ids = siteVisibilityRows();

    expect($ids)->toContain($first->id, $second->id)
        ->and($ids)->not->toContain($outside->id);
});

// ---------------------------------------------------------------------------
// AC-004 — a request with no Sede belongs to no site (fail-closed, D-3)
// ---------------------------------------------------------------------------

it('rows: a request without a Sede is invisible to a viewSite actor, and its panel answers 403 (AC-004)', function () {
    $site = OperationalSite::factory()->create();
    $actor = siteVisibilityMemberOf(siteVisibilityActor(['viewAny', 'view', 'viewSite']), $site);

    $siteless = Quote::factory()->create(['operational_site_id' => null]);

    Sanctum::actingAs($actor);

    expect(siteVisibilityRows())->not->toContain($siteless->id);

    $this->getJson("/api/request-management/{$siteless->id}")->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-005 — the two tiers are a UNION, never a replacement (D-6)
// ---------------------------------------------------------------------------

it('rows: a viewSite actor keeps the requests they operate in someone else\'s Sede (AC-005)', function () {
    $mySite = OperationalSite::factory()->create();
    $actor = siteVisibilityMemberOf(siteVisibilityActor(['viewAny', 'view', 'viewSite']), $mySite);

    $operatedElsewhere = Quote::factory()->create([
        'operational_site_id' => OperationalSite::factory()->create()->id,
        'operator_id' => $actor->id,
    ]);
    $inMySite = Quote::factory()->create(['operational_site_id' => $mySite->id]);

    Sanctum::actingAs($actor);

    expect(siteVisibilityRows())->toContain($operatedElsewhere->id, $inMySite->id);

    $this->getJson("/api/request-management/{$operatedElsewhere->id}")->assertOk();
});

// ---------------------------------------------------------------------------
// AC-006 — without the permission nothing changes
// ---------------------------------------------------------------------------

it('rows: a member of the Sede WITHOUT viewSite still sees only what they operate (AC-006)', function () {
    $site = OperationalSite::factory()->create();
    $actor = siteVisibilityMemberOf(siteVisibilityActor(['viewAny', 'view']), $site);

    $operated = Quote::factory()->create(['operational_site_id' => $site->id, 'operator_id' => $actor->id]);
    $colleagues = Quote::factory()->create(['operational_site_id' => $site->id]);

    Sanctum::actingAs($actor);
    $ids = siteVisibilityRows();

    expect($ids)->toBe([$operated->id])
        ->and($ids)->not->toContain($colleagues->id);
});

// ---------------------------------------------------------------------------
// AC-007 — the disjunction stays inside its own closure (D-6): it must never
// escape the conditions the caller already put on the builder. The notes read
// gate is the sharpest probe: its query is pre-scoped to ONE Opportunity, so a
// root-level OR would hand the actor the thread of every Opportunity that has
// no Offerta of theirs at all.
// ---------------------------------------------------------------------------

it('the site disjunction never escapes a pre-scoped query (AC-007)', function () {
    $site = OperationalSite::factory()->create();
    $actor = siteVisibilityMemberOf(siteVisibilityActor(['viewAny', 'view', 'viewSite']), $site);

    // Reachable: an Offerta of my Sede hangs off this Opportunity.
    $mine = Quote::factory()->create(['operational_site_id' => $site->id]);
    // Unreachable: none of its Offerte is in my Sede.
    $foreign = Quote::factory()->create(['operational_site_id' => OperationalSite::factory()->create()->id]);

    Sanctum::actingAs($actor);

    $this->getJson('/api/notes/mentionable-users?entity_type=request-management&entity_id='.$mine->opportunity_id)
        ->assertOk();

    $this->getJson('/api/notes/mentionable-users?entity_type=request-management&entity_id='.$foreign->opportunity_id)
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-008 / AC-009 — the tier widens WHICH rows, never WHAT may be done (D-2)
// ---------------------------------------------------------------------------

it('PATCH on a request of my Sede I do not operate succeeds with update, 403 without it (AC-008)', function () {
    $site = OperationalSite::factory()->create();
    $quote = Quote::factory()->create(['operational_site_id' => $site->id]);
    $callbackAt = now()->addDay()->format('Y-m-d\TH:i');

    // Both actors are built BEFORE the first actingAs: spatie resolves a
    // permission by name AND guard, and creating one while Sanctum is the
    // active driver would file it under a second guard the other actor never
    // holds.
    $reader = siteVisibilityMemberOf(siteVisibilityActor(['viewAny', 'view', 'viewSite']), $site);
    $writer = siteVisibilityMemberOf(siteVisibilityActor(['viewAny', 'view', 'viewSite', 'update']), $site);

    Sanctum::actingAs($reader);
    $this->patchJson("/api/request-management/{$quote->id}", ['next_callback_at' => $callbackAt])->assertForbidden();

    Sanctum::actingAs($writer);
    $this->patchJson("/api/request-management/{$quote->id}", ['next_callback_at' => $callbackAt])->assertOk();

    expect($quote->fresh()->next_callback_at)->not->toBeNull();
});

it('DELETE reaches a request of my Sede and stops at another Sede (AC-009)', function () {
    $site = OperationalSite::factory()->create();
    $actor = siteVisibilityMemberOf(siteVisibilityActor(['viewAny', 'view', 'viewSite', 'delete']), $site);

    $mine = Quote::factory()->create(['operational_site_id' => $site->id]);
    $foreign = Quote::factory()->create(['operational_site_id' => OperationalSite::factory()->create()->id]);

    Sanctum::actingAs($actor);

    $this->deleteJson("/api/request-management/{$mine->id}")->assertNoContent();
    $this->deleteJson("/api/request-management/{$foreign->id}")->assertForbidden();

    expect(Quote::query()->whereKey($mine->id)->exists())->toBeFalse()
        ->and(Opportunity::query()->whereKey($mine->opportunity_id)->exists())->toBeTrue()
        ->and(Quote::query()->whereKey($foreign->id)->exists())->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-010 — the bulk in-scope filter inherits the same rule
// ---------------------------------------------------------------------------

it('bulk assign-operators touches the requests of my Sede and silently skips the others (AC-010)', function () {
    $site = OperationalSite::factory()->create();
    $actor = siteVisibilityMemberOf(
        siteVisibilityActor(['viewAny', 'view', 'viewSite', 'update', 'assignOperator']),
        $site,
    );

    $mine = Quote::factory()->create(['operational_site_id' => $site->id]);
    $foreign = Quote::factory()->create(['operational_site_id' => OperationalSite::factory()->create()->id]);

    // Direttiva utente 2026-09-10: `mode=single` now refuses an operator no
    // targeted offer would have accepted, so the assignee must belong to the
    // Sede of `$mine`. Before that rule any Sede did, and this fixture used a
    // third one. `$foreign` still needs nothing: being outside the actor's
    // scope it stays invisible to the check too, which is the point here.
    $operator = siteVisibilityMemberOf(User::factory()->create(), $site);

    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => [$mine->id, $foreign->id],
        'mode' => 'single',
        'operator_id' => $operator->id,
    ])->assertOk()->assertJsonPath('data.assigned', 1);

    expect($mine->fresh()->operator_id)->toBe($operator->id)
        ->and($foreign->fresh()->operator_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-011 — the category tab counts run on the same scope
// ---------------------------------------------------------------------------

it('category tabs count the requests of my Sede I do not operate (AC-011)', function () {
    $site = OperationalSite::factory()->create();
    $actor = siteVisibilityMemberOf(siteVisibilityActor(['viewAny', 'view', 'viewSite']), $site);
    $category = ProductCategory::factory()->create(['name' => 'Alpha']);

    foreach ([$site->id, OperationalSite::factory()->create()->id] as $siteId) {
        $opportunity = Opportunity::factory()->create();
        OpportunityProductLine::factory()->create([
            'opportunity_id' => $opportunity->id,
            'business_function_id' => BusinessFunction::factory()->create()->id,
            'product_category_id' => $category->id,
        ]);
        Quote::factory()->for($opportunity)->create(['operational_site_id' => $siteId]);
    }

    Sanctum::actingAs($actor);

    $this->getJson('/api/request-management/product-categories')
        ->assertOk()
        ->assertJsonCount(1, 'data.categories')
        ->assertJsonPath('data.categories.0.requests_count', 1);
});

// ---------------------------------------------------------------------------
// AC-012 — notes: read access and the mentionable set move together (D-9)
// ---------------------------------------------------------------------------

it('a viewSite actor reads the thread of an Opportunity with an Offerta in their Sede, and is mentionable in it (AC-012)', function () {
    Permission::findOrCreate('notes.create');
    $site = OperationalSite::factory()->create();
    $actor = siteVisibilityMemberOf(siteVisibilityActor(['viewAny', 'view', 'viewSite']), $site);
    // Built before the first actingAs, same guard reason as AC-008 above.
    $supervisor = siteVisibilityActor(['viewAny', 'view', 'viewAll']);
    $reachable = Quote::factory()->create(['operational_site_id' => $site->id]);
    $unreachable = Quote::factory()->create(['operational_site_id' => OperationalSite::factory()->create()->id]);

    Sanctum::actingAs($actor);
    $this->getJson('/api/notes/mentionable-users?entity_type=request-management&entity_id='.$reachable->opportunity_id)
        ->assertOk();

    // The mentionable set is read by someone else: the actor must be IN it.
    Sanctum::actingAs($supervisor);

    $reachableIds = collect(
        $this->getJson('/api/notes/mentionable-users?entity_type=request-management&entity_id='.$reachable->opportunity_id)
            ->assertOk()->json('items')
    )->pluck('id')->all();

    $unreachableIds = collect(
        $this->getJson('/api/notes/mentionable-users?entity_type=request-management&entity_id='.$unreachable->opportunity_id)
            ->assertOk()->json('items')
    )->pluck('id')->all();

    expect($reachableIds)->toContain($actor->id)
        ->and($unreachableIds)->not->toContain($actor->id);
});

// ---------------------------------------------------------------------------
// AC-012b — the operational history is one of the surfaces D-2 lists, and it
// runs through the same shared scope: covered here because sharing the method
// is not the same as proving the endpoint answers.
// ---------------------------------------------------------------------------

it('the activity log of a request in my Sede is readable, another Sede\'s is not (AC-012)', function () {
    $site = OperationalSite::factory()->create();
    $actor = siteVisibilityMemberOf(siteVisibilityActor(['viewAny', 'view', 'viewSite', 'viewActivity']), $site);

    $mine = Quote::factory()->create(['operational_site_id' => $site->id]);
    $foreign = Quote::factory()->create(['operational_site_id' => OperationalSite::factory()->create()->id]);

    Sanctum::actingAs($actor);

    $this->getJson("/api/activity-log/request-management/{$mine->opportunity_id}")->assertOk();
    $this->getJson("/api/activity-log/request-management/{$foreign->opportunity_id}")->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-013 — the fail-closed branch survives the new disjunction
// ---------------------------------------------------------------------------

it('a null actor is still scoped to an always-empty result (AC-013)', function () {
    Quote::factory()->create(['operational_site_id' => OperationalSite::factory()->create()->id]);

    expect(RequestManagementScope::scopeToActor(Quote::query(), null)->exists())->toBeFalse();
});

// ---------------------------------------------------------------------------
// AC-014 / AC-015 — catalogue and seeded roles
// ---------------------------------------------------------------------------

it('permissions:sync creates request-management.viewSite and it is assignable from the Role form (AC-014)', function () {
    Permission::query()->where('name', 'request-management.viewSite')->delete();

    Artisan::call('permissions:sync');

    expect(Permission::query()->where('name', 'request-management.viewSite')->exists())->toBeTrue()
        ->and(app(AssignablePermissionCatalogue::class)->isAssignable('request-management.viewSite'))->toBeTrue();
});

it('the seeded Commercial role does not hold viewSite (AC-015)', function () {
    $this->seed(TestUsersSeeder::class);

    $commercial = Role::query()->where('name', 'commercial')->firstOrFail();

    expect($commercial->hasPermissionTo('request-management.viewSite'))->toBeFalse()
        ->and($commercial->hasPermissionTo('request-management.viewAll'))->toBeFalse();
});
