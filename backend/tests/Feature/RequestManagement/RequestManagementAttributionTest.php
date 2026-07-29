<?php

use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\Referent;
use App\Models\Role;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Attribution block on the operative work panel (user directive 2026-07-22):
 * "Fonte" (`source_id`), "Segnalatore" (`reporter_id`) and the GA2
 * "Operatore" (`operator_id`, the `opportunity_user` row at pivot position
 * Opportunity::OPERATOR_MANAGER_POSITION) are readable AND writable from
 * GET/PATCH /api/request-management/{opportunity}, sparse like every other
 * key of that endpoint. Spec 0056 joins the same block with "Sede operativa"
 * (`operational_site_id`), AC-011/013/014.
 */
uses(RefreshDatabase::class);

if (! function_exists('attributionActor')) {
    function attributionActor(): User
    {
        foreach (['viewAny', 'view', 'update'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['request-management.view', 'request-management.update']);

        return $user;
    }
}

if (! function_exists('attributionOpportunity')) {
    function attributionOpportunity(User $operator): Opportunity
    {
        $opportunity = Opportunity::factory()->create();
        $opportunity->managers()->sync([$operator->id => ['position' => Opportunity::OPERATOR_MANAGER_POSITION]]);

        return $opportunity;
    }
}

it('GET exposes fonte, segnalatore and the GA2 operator', function () {
    $actor = attributionActor();
    $source = Source::factory()->create();
    $reporter = Referent::factory()->create();
    $opportunity = attributionOpportunity($actor);
    $opportunity->update(['source_id' => $source->id, 'reporter_id' => $reporter->id]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$opportunity->id}")
        ->assertOk()
        ->assertJsonPath('data.source_id', $source->id)
        ->assertJsonPath('data.source.name', $source->name)
        ->assertJsonPath('data.reporter_id', $reporter->id)
        ->assertJsonPath('data.reporter.name', $reporter->name)
        ->assertJsonPath('data.operator_id', $actor->id)
        ->assertJsonPath('data.operator.name', $actor->name);
});

it('GET reports a null operator when the GA2 slot is empty', function () {
    $actor = attributionActor();
    $actor->givePermissionTo(Permission::findOrCreate('request-management.viewAll'));
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$opportunity->id}")
        ->assertOk()
        ->assertJsonPath('data.operator_id', null)
        ->assertJsonPath('data.operator', null);
});

it('PATCH persists fonte and segnalatore and echoes them back', function () {
    $actor = attributionActor();
    $opportunity = attributionOpportunity($actor);
    $source = Source::factory()->create();
    $reporter = Referent::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'source_id' => $source->id,
        'reporter_id' => $reporter->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.source.id', $source->id)
        ->assertJsonPath('data.reporter.id', $reporter->id);

    $this->assertDatabaseHas('opportunities', [
        'id' => $opportunity->id,
        'source_id' => $source->id,
        'reporter_id' => $reporter->id,
    ]);
});

it('PATCH clears segnalatore with an explicit null', function () {
    $actor = attributionActor();
    $opportunity = attributionOpportunity($actor);
    $opportunity->update([
        'source_id' => Source::factory()->create()->id,
        'reporter_id' => Referent::factory()->create()->id,
    ]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", ['reporter_id' => null])
        ->assertOk()
        ->assertJsonPath('data.reporter', null);

    $this->assertDatabaseHas('opportunities', [
        'id' => $opportunity->id,
        'reporter_id' => null,
    ]);
});

// User directive 2026-07-29: the Fonte is MANDATORY. The key stays sparse
// (absent = untouched, so a legacy row is never blocked server-side by a
// PATCH that does not mention it), but an explicit null is refused — there is
// no way to take a request back to having no source.
it('PATCH refuses to clear the fonte with an explicit null', function () {
    $actor = attributionActor();
    $opportunity = attributionOpportunity($actor);
    $source = Source::factory()->create();
    $opportunity->update(['source_id' => $source->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", ['source_id' => null])
        ->assertStatus(422)
        ->assertJsonValidationErrors('source_id');

    expect($opportunity->fresh()->source_id)->toBe($source->id);
});

it('PATCH leaves the attribution untouched when its keys are absent (sparse)', function () {
    $actor = attributionActor();
    $opportunity = attributionOpportunity($actor);
    $source = Source::factory()->create();
    $opportunity->update(['source_id' => $source->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", ['next_callback_at' => null])
        ->assertOk()
        ->assertJsonPath('data.source_id', $source->id)
        ->assertJsonPath('data.operator_id', $actor->id);
});

it('PATCH operator_id moves the GA2 slot to another user', function () {
    $actor = attributionActor();
    $actor->givePermissionTo(Permission::findOrCreate('request-management.viewAll'));
    $opportunity = attributionOpportunity($actor);
    $newOperator = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", ['operator_id' => $newOperator->id])
        ->assertOk()
        ->assertJsonPath('data.operator_id', $newOperator->id)
        ->assertJsonPath('data.operator.name', $newOperator->name);

    $this->assertDatabaseHas('opportunity_user', [
        'opportunity_id' => $opportunity->id,
        'user_id' => $newOperator->id,
        'position' => Opportunity::OPERATOR_MANAGER_POSITION,
    ]);
    $this->assertDatabaseMissing('opportunity_user', [
        'opportunity_id' => $opportunity->id,
        'user_id' => $actor->id,
    ]);
});

it('PATCH operator_id leaves the other manager slots untouched', function () {
    $actor = attributionActor();
    $opportunity = attributionOpportunity($actor);
    $firstManager = User::factory()->create();
    $opportunity->managers()->attach($firstManager->id, ['position' => 1]);
    $newOperator = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", ['operator_id' => $newOperator->id])
        ->assertOk();

    $this->assertDatabaseHas('opportunity_user', [
        'opportunity_id' => $opportunity->id,
        'user_id' => $firstManager->id,
        'position' => 1,
    ]);
});

it('PATCH operator_id null empties the GA2 slot', function () {
    $actor = attributionActor();
    $actor->givePermissionTo(Permission::findOrCreate('request-management.viewAll'));
    $opportunity = attributionOpportunity($actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", ['operator_id' => null])
        ->assertOk()
        ->assertJsonPath('data.operator_id', null);

    $this->assertDatabaseMissing('opportunity_user', [
        'opportunity_id' => $opportunity->id,
        'position' => Opportunity::OPERATOR_MANAGER_POSITION,
    ]);
});

it('PATCH rejects an unknown fonte, segnalatore or operatore', function () {
    $actor = attributionActor();
    $opportunity = attributionOpportunity($actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'source_id' => 999999,
        'reporter_id' => 999999,
        'operator_id' => 999999,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['source_id', 'reporter_id', 'operator_id']);
});

it('exposes the three fields in the permissions metadata block', function () {
    $actor = attributionActor();
    $opportunity = attributionOpportunity($actor);
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$opportunity->id}")
        ->assertOk()
        ->assertJsonPath('permissions.fields.source_id.editable', true)
        ->assertJsonPath('permissions.fields.reporter_id.editable', true)
        ->assertJsonPath('permissions.fields.operator_id.editable', true);
});

it('denies the attribution write to an actor outside the D-3 scope', function () {
    $operator = attributionActor();
    $opportunity = attributionOpportunity($operator);
    $stranger = attributionActor();
    Sanctum::actingAs($stranger);

    $this->patchJson("/api/request-management/{$opportunity->id}", ['source_id' => Source::factory()->create()->id])
        ->assertStatus(403);

    expect($opportunity->fresh()->source_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// Sede operativa (spec 0056, AC-011/013/014) — same attribution block
// ---------------------------------------------------------------------------

it('GET exposes the Sede operativa {id, label}, and null when unset (AC-011)', function () {
    $actor = attributionActor();
    $actor->givePermissionTo(Permission::findOrCreate('operational-sites.viewAny'));
    $site = OperationalSite::factory()->withAddress()->create();
    $opportunity = attributionOpportunity($actor);
    $opportunity->update(['operational_site_id' => $site->id]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$opportunity->id}")
        ->assertOk()
        ->assertJsonPath('data.operational_site_id', $site->id)
        ->assertJsonPath('data.operational_site.id', $site->id);
});

it('PATCH persists the Sede operativa and echoes it back (AC-011)', function () {
    $actor = attributionActor();
    $actor->givePermissionTo(Permission::findOrCreate('operational-sites.viewAny'));
    $opportunity = attributionOpportunity($actor);
    $site = OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", ['operational_site_id' => $site->id])
        ->assertOk()
        ->assertJsonPath('data.operational_site.id', $site->id);

    $this->assertDatabaseHas('opportunities', ['id' => $opportunity->id, 'operational_site_id' => $site->id]);
});

it('PATCH clears the Sede operativa with an explicit null', function () {
    $actor = attributionActor();
    $actor->givePermissionTo(Permission::findOrCreate('operational-sites.viewAny'));
    $site = OperationalSite::factory()->withAddress()->create();
    $opportunity = attributionOpportunity($actor);
    $opportunity->update(['operational_site_id' => $site->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", ['operational_site_id' => null])
        ->assertOk()
        ->assertJsonPath('data.operational_site', null);

    $this->assertDatabaseHas('opportunities', ['id' => $opportunity->id, 'operational_site_id' => null]);
});

it('PATCH rejects a non-existent operational_site_id -> 422', function () {
    $actor = attributionActor();
    $actor->givePermissionTo(Permission::findOrCreate('operational-sites.viewAny'));
    $opportunity = attributionOpportunity($actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", ['operational_site_id' => 999999])
        ->assertStatus(422)
        ->assertJsonValidationErrors('operational_site_id');
});

it('permissions.fields.operational_site_id is READ-ONLY for an actor without operational-sites.viewAny (AC-013)', function () {
    $actor = attributionActor();
    $opportunity = attributionOpportunity($actor);
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$opportunity->id}")
        ->assertOk()
        ->assertJsonPath('permissions.fields.operational_site_id.editable', false)
        ->assertJsonPath('permissions.fields.operational_site_id.readonly', true);
});

it('PATCH operational_site_id 422s for an actor without operational-sites.viewAny (AC-013)', function () {
    $actor = attributionActor();
    $opportunity = attributionOpportunity($actor);
    $site = OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", ['operational_site_id' => $site->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('operational_site_id');

    expect($opportunity->fresh()->operational_site_id)->toBeNull();
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
    $opportunity = attributionOpportunity($actor);
    $site = OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", ['operational_site_id' => $site->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('operational_site_id');

    expect($opportunity->fresh()->operational_site_id)->toBeNull();
});
