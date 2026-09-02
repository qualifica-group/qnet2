<?php

use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\Role;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Attribution block on the operative work panel (user directive 2026-07-22),
 * migrated onto the Quote by spec 0086, D-2/D-3: "Fonte" (`source_id`) stays
 * on the underlying Opportunity, "Segnalatore" (`reporter_id`) and "Sede
 * operativa" (`operational_site_id`) are now Quote columns, and the GA2
 * "Operatore" (`operator_id`) is the Offerta's own `quotes.operator_id`
 * (spec 0087, D-9), synced onto its OWN `quote_user` pivot at
 * `ManagerPositions::OPERATOR` — never `quotes.supervisor_id` any more
 * (D-13/D-14). A write PROMOTES the operator onto the Opportunity's first
 * FREE manager slot when needed (D-6), never overwriting its existing GA2.
 * Readable AND writable from GET/PATCH /api/request-management/{quote},
 * sparse like every other key of that endpoint. AC-011/013/014.
 *
 * Spec 0097, D-1/D-5: the panel's write key for that operator is no longer
 * `operator_id` but `manager_slots` — the WHOLE team, of which the operator
 * is slot `ManagerPositions::OPERATOR`. The read keys `operator_id`/
 * `operator` stay (grid, notifications, transfer), joined by `managers`.
 * `operator_id` remains `updateWork()`'s internal key for the OTHER channels
 * (grid cell, bulk assign, transfer), which never come through this endpoint.
 */
uses(RefreshDatabase::class);

if (! function_exists('attributionActor')) {
    function attributionActor(): User
    {
        foreach (['viewAny', 'view', 'update', 'updateSource'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        // updateSource: source_id became a protected field (spec 0078,
        // ProtectedFieldAwareAuthorization) — without it the field is forced
        // readonly, which is orthogonal to what this file tests (the
        // attribution block's read/write plumbing), so the actor is granted
        // it here.
        $user = User::factory()->create();
        $user->givePermissionTo(['request-management.view', 'request-management.update', 'request-management.updateSource']);

        return $user;
    }
}

if (! function_exists('attributionQuote')) {
    function attributionQuote(User $operator): Quote
    {
        // A decoy opportunity is created first (never used) so the
        // opportunity id and the quote id can never coincide by
        // construction — a coincidence would let an assertion mixing up the
        // two records pass for the wrong reason.
        Opportunity::factory()->create();

        $opportunity = Opportunity::factory()->create();
        $opportunity->managers()->sync([$operator->id => ['position' => Opportunity::OPERATOR_MANAGER_POSITION]]);

        return Quote::factory()->for($opportunity)->create(['operator_id' => $operator->id]);
    }
}

it('GET exposes fonte, segnalatore and the GA2 operator', function () {
    $actor = attributionActor();
    $source = Source::factory()->create();
    $reporter = Referent::factory()->create();
    $quote = attributionQuote($actor);
    $quote->opportunity->update(['source_id' => $source->id]);
    $quote->update(['reporter_id' => $reporter->id]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$quote->id}")
        ->assertOk()
        ->assertJsonPath('data.source_id', $source->id)
        ->assertJsonPath('data.source.name', $source->name)
        ->assertJsonPath('data.reporter_id', $reporter->id)
        ->assertJsonPath('data.reporter.name', $reporter->name)
        ->assertJsonPath('data.operator_id', $actor->id)
        ->assertJsonPath('data.operator.name', $actor->name);
});

it('GET reports a null operator when there is none', function () {
    $actor = attributionActor();
    $actor->givePermissionTo(Permission::findOrCreate('request-management.viewAll'));
    $quote = Quote::factory()->create(['operator_id' => null]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$quote->id}")
        ->assertOk()
        ->assertJsonPath('data.operator_id', null)
        ->assertJsonPath('data.operator', null);
});

it('PATCH persists fonte and segnalatore and echoes them back', function () {
    $actor = attributionActor();
    $quote = attributionQuote($actor);
    $source = Source::factory()->create();
    $reporter = Referent::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'source_id' => $source->id,
        'reporter_id' => $reporter->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.source.id', $source->id)
        ->assertJsonPath('data.reporter.id', $reporter->id);

    $this->assertDatabaseHas('opportunities', [
        'id' => $quote->opportunity_id,
        'source_id' => $source->id,
    ]);
    $this->assertDatabaseHas('quotes', [
        'id' => $quote->id,
        'reporter_id' => $reporter->id,
    ]);
});

it('PATCH clears segnalatore with an explicit null', function () {
    $actor = attributionActor();
    $quote = attributionQuote($actor);
    $quote->update(['reporter_id' => Referent::factory()->create()->id]);
    $quote->opportunity->update(['source_id' => Source::factory()->create()->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", ['reporter_id' => null])
        ->assertOk()
        ->assertJsonPath('data.reporter', null);

    $this->assertDatabaseHas('quotes', [
        'id' => $quote->id,
        'reporter_id' => null,
    ]);
});

// User directive 2026-07-29: the Fonte is MANDATORY. The key stays sparse
// (absent = untouched, so a legacy row is never blocked server-side by a
// PATCH that does not mention it), but an explicit null is refused — there is
// no way to take a request back to having no source.
it('PATCH refuses to clear the fonte with an explicit null', function () {
    $actor = attributionActor();
    $quote = attributionQuote($actor);
    $source = Source::factory()->create();
    $quote->opportunity->update(['source_id' => $source->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", ['source_id' => null])
        ->assertStatus(422)
        ->assertJsonValidationErrors('source_id');

    expect($quote->opportunity->fresh()->source_id)->toBe($source->id);
});

it('PATCH leaves the attribution untouched when its keys are absent (sparse)', function () {
    $actor = attributionActor();
    $quote = attributionQuote($actor);
    $source = Source::factory()->create();
    $quote->opportunity->update(['source_id' => $source->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", ['next_callback_at' => null])
        ->assertOk()
        ->assertJsonPath('data.source_id', $source->id)
        ->assertJsonPath('data.operator_id', $actor->id);
});

it('PATCH manager_slots writes the Offerta\'s own GA2 slot and promotes the new operator onto the Opportunity\'s first free slot, without demoting the existing GA2 (spec 0087, D-9/D-13; spec 0097, D-5)', function () {
    $actor = attributionActor();
    $actor->givePermissionTo(Permission::findOrCreate('request-management.viewAll'));
    $quote = attributionQuote($actor);
    $newOperator = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", ['manager_slots' => [null, $newOperator->id]])
        ->assertOk()
        ->assertJsonPath('data.operator_id', $newOperator->id)
        ->assertJsonPath('data.operator.name', $newOperator->name)
        ->assertJsonPath('data.managers', [
            ['id' => $newOperator->id, 'name' => $newOperator->name, 'position' => 2],
        ]);

    $this->assertDatabaseHas('quotes', [
        'id' => $quote->id,
        'operator_id' => $newOperator->id,
    ]);
    $this->assertDatabaseHas('quote_user', [
        'quote_id' => $quote->id,
        'user_id' => $newOperator->id,
        'position' => 2,
    ]);
    // D-6/D-13: $newOperator was not yet a Gestore Account of the
    // Opportunity, so it is PROMOTED onto its first FREE slot — slot 1, since
    // $actor already occupies slot 2 — never overwriting the existing GA2.
    $this->assertDatabaseHas('opportunity_user', [
        'opportunity_id' => $quote->opportunity_id,
        'user_id' => $newOperator->id,
        'position' => 1,
    ]);
    $this->assertDatabaseHas('opportunity_user', [
        'opportunity_id' => $quote->opportunity_id,
        'user_id' => $actor->id,
        'position' => Opportunity::OPERATOR_MANAGER_POSITION,
    ]);
});

it('PATCH manager_slots leaves the Opportunity\'s other manager slots untouched', function () {
    $actor = attributionActor();
    $quote = attributionQuote($actor);
    $firstManager = User::factory()->create();
    $quote->opportunity->managers()->attach($firstManager->id, ['position' => 1]);
    $newOperator = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", ['manager_slots' => [null, $newOperator->id]])
        ->assertOk();

    $this->assertDatabaseHas('opportunity_user', [
        'opportunity_id' => $quote->opportunity_id,
        'user_id' => $firstManager->id,
        'position' => 1,
    ]);
});

it('PATCH manager_slots empties the Offerta\'s own GA2 slot, leaving the Opportunity\'s team untouched (spec 0087, D-9/D-13; spec 0097, D-5)', function () {
    $actor = attributionActor();
    $actor->givePermissionTo(Permission::findOrCreate('request-management.viewAll'));
    $quote = attributionQuote($actor);
    $quote->managers()->sync([$actor->id => ['position' => 2]]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", ['manager_slots' => [null, null]])
        ->assertOk()
        ->assertJsonPath('data.operator_id', null)
        ->assertJsonPath('data.managers', []);

    $this->assertDatabaseHas('quotes', [
        'id' => $quote->id,
        'operator_id' => null,
    ]);
    // Spec 0087, D-9/D-13: clearing the Offerta's own GA2 slot never reaches
    // the Opportunity's pivot — $actor, placed there by attributionQuote(),
    // stays exactly where it was.
    $this->assertDatabaseHas('opportunity_user', [
        'opportunity_id' => $quote->opportunity_id,
        'user_id' => $actor->id,
        'position' => Opportunity::OPERATOR_MANAGER_POSITION,
    ]);
});

it('PATCH rejects an unknown fonte, segnalatore or operatore', function () {
    $actor = attributionActor();
    $quote = attributionQuote($actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'source_id' => 999999,
        'reporter_id' => 999999,
        'manager_slots' => [999999],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['source_id', 'reporter_id', 'manager_slots.0']);
});

it('exposes the three fields in the permissions metadata block', function () {
    $actor = attributionActor();
    $quote = attributionQuote($actor);
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$quote->id}")
        ->assertOk()
        ->assertJsonPath('permissions.fields.source_id.editable', true)
        ->assertJsonPath('permissions.fields.reporter_id.editable', true)
        ->assertJsonPath('permissions.fields.manager_slots.editable', true);
});

it('denies the attribution write to an actor outside the D-3 scope', function () {
    $operator = attributionActor();
    $quote = attributionQuote($operator);
    $stranger = attributionActor();
    Sanctum::actingAs($stranger);

    $this->patchJson("/api/request-management/{$quote->id}", ['source_id' => Source::factory()->create()->id])
        ->assertStatus(403);

    expect($quote->opportunity->fresh()->source_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// Sede operativa (spec 0056, AC-011/013/014) — now a Quote column (D-6)
// ---------------------------------------------------------------------------

it('GET exposes the Sede operativa {id, label}, and null when unset (AC-011)', function () {
    $actor = attributionActor();
    $actor->givePermissionTo(Permission::findOrCreate('operational-sites.viewAny'));
    $site = OperationalSite::factory()->withAddress()->create();
    $quote = attributionQuote($actor);
    $quote->update(['operational_site_id' => $site->id]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$quote->id}")
        ->assertOk()
        ->assertJsonPath('data.operational_site_id', $site->id)
        ->assertJsonPath('data.operational_site.id', $site->id);
});

it('PATCH persists the Sede operativa and echoes it back (AC-011)', function () {
    $actor = attributionActor();
    $actor->givePermissionTo(Permission::findOrCreate('operational-sites.viewAny'));
    $quote = attributionQuote($actor);
    $site = OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", ['operational_site_id' => $site->id])
        ->assertOk()
        ->assertJsonPath('data.operational_site.id', $site->id);

    $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'operational_site_id' => $site->id]);
});

it('PATCH clears the Sede operativa with an explicit null', function () {
    $actor = attributionActor();
    $actor->givePermissionTo(Permission::findOrCreate('operational-sites.viewAny'));
    $site = OperationalSite::factory()->withAddress()->create();
    $quote = attributionQuote($actor);
    $quote->update(['operational_site_id' => $site->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", ['operational_site_id' => null])
        ->assertOk()
        ->assertJsonPath('data.operational_site', null);

    $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'operational_site_id' => null]);
});

it('PATCH rejects a non-existent operational_site_id -> 422', function () {
    $actor = attributionActor();
    $actor->givePermissionTo(Permission::findOrCreate('operational-sites.viewAny'));
    $quote = attributionQuote($actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", ['operational_site_id' => 999999])
        ->assertStatus(422)
        ->assertJsonValidationErrors('operational_site_id');
});

it('permissions.fields.operational_site_id is READ-ONLY for an actor without operational-sites.viewAny (AC-013)', function () {
    $actor = attributionActor();
    $quote = attributionQuote($actor);
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$quote->id}")
        ->assertOk()
        ->assertJsonPath('permissions.fields.operational_site_id.editable', false)
        ->assertJsonPath('permissions.fields.operational_site_id.readonly', true);
});

it('PATCH operational_site_id 422s for an actor without operational-sites.viewAny (AC-013)', function () {
    $actor = attributionActor();
    $quote = attributionQuote($actor);
    $site = OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", ['operational_site_id' => $site->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('operational_site_id');

    expect($quote->fresh()->operational_site_id)->toBeNull();
});

it('update: a role whose field-permission on operational_site_id is readonly -> 422, no write (AC-014)', function () {
    $role = Role::create(['name' => 'request-management-site-locked']);
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("request-management.{$ability}");
    }
    Permission::findOrCreate('operational-sites.viewAny');
    $role->givePermissionTo(['request-management.viewAny', 'request-management.view', 'request-management.update', 'operational-sites.viewAny']);
    $role->fieldPermissions()->create([
        'resource' => 'request-management',
        'field' => 'operational_site_id',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);
    $quote = attributionQuote($actor);
    $site = OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", ['operational_site_id' => $site->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('operational_site_id');

    expect($quote->fresh()->operational_site_id)->toBeNull();
});
