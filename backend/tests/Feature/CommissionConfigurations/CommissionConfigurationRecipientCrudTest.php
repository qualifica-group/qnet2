<?php

use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionRecipientRole;
use App\Models\CommissionConfiguration;
use App\Models\Product;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0089: the recipient dimension on the Configurator's write path
 * (D-7/D-9/D-12) — AC-009 through AC-014, AC-016's server-side half.
 *
 * Spec 0090 D-4/D-9 EMENDS 0089's D-7/D-9: `recipient_type` becomes an
 * ACCEPTED, allow-list-validated input instead of an always-ignored one, and
 * the role-change guard now keys off that allow-list. The two tests below
 * that asserted the SUPERSEDED behaviour ("recipient_type is always ignored"
 * / "COMMERCIAL -> SUPERVISOR is always blocked without resubmitting
 * recipient_id") are updated accordingly — the requirement itself changed,
 * per CLAUDE.md §2 ("cambia un test solo se il requisito è cambiato").
 */
uses(RefreshDatabase::class);

function recipientCrudActor(array $abilities): User
{
    foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
        Permission::findOrCreate("commission-configurations.{$ability}");
    }

    $actor = User::factory()->create();
    foreach ($abilities as $ability) {
        $actor->givePermissionTo("commission-configurations.{$ability}");
    }

    return $actor;
}

function recipientCrudPayload(array $overrides = []): array
{
    return array_replace([
        'name' => 'Recipient rule',
        'recipient_role' => 'COMMERCIAL',
        'application_scope' => 'PRODUCT',
        'product_id' => Product::factory()->create()->id,
        'commission_type' => 'PERCENTAGE',
        'value' => 5,
        'priority' => 0,
        'valid_from' => '2026-01-01',
        'status' => 'ACTIVE',
    ], $overrides);
}

it('rejects RECIPIENT scope without a recipient_id and rejects a product_id alongside it (AC-009)', function () {
    Sanctum::actingAs(recipientCrudActor(['create']));

    $this->postJson('/api/commission-configurations', recipientCrudPayload([
        'application_scope' => 'RECIPIENT',
        'product_id' => null,
    ]))->assertUnprocessable()->assertJsonValidationErrors('recipient_id');

    $this->postJson('/api/commission-configurations', recipientCrudPayload([
        'application_scope' => 'RECIPIENT',
        'recipient_id' => Referent::factory()->create()->id,
    ]))->assertUnprocessable()->assertJsonValidationErrors('product_id');
});

it('rejects a SUPERVISOR rule pointing at an id that only exists in referents, not users (AC-010)', function () {
    Sanctum::actingAs(recipientCrudActor(['create']));
    // Referents and users have independent auto-increment sequences; pick an
    // id that genuinely collides with no `users` row, so the assertion truly
    // exercises "exists in referents but not in users" (not a lucky gap).
    do {
        $referentOnlyId = Referent::factory()->create()->id;
    } while (User::whereKey($referentOnlyId)->exists());

    $this->postJson('/api/commission-configurations', recipientCrudPayload([
        'recipient_role' => 'SUPERVISOR',
        'recipient_id' => $referentOnlyId,
    ]))->assertUnprocessable()->assertJsonValidationErrors('recipient_id');
});

it('honours a client-submitted recipient_type within the role allow-list, and derives it when omitted (spec 0090 D-4, AC-007/AC-009)', function () {
    Sanctum::actingAs(recipientCrudActor(['create', 'view']));
    $commercial = Referent::factory()->create();
    $supervisorAsUser = User::factory()->create();

    $derived = $this->postJson('/api/commission-configurations', recipientCrudPayload([
        'recipient_id' => $commercial->id,
    ]))->assertCreated();
    expect($derived->json('data.recipient_type'))->toBe('referent')
        ->and($derived->json('data.recipient.name'))->toBe($commercial->name);

    $chosen = $this->postJson('/api/commission-configurations', recipientCrudPayload([
        'name' => 'Recipient rule (user)',
        'recipient_id' => $supervisorAsUser->id,
        'recipient_type' => 'user',
    ]))->assertCreated();
    expect($chosen->json('data.recipient_type'))->toBe('user')
        ->and($chosen->json('data.recipient_id'))->toBe($supervisorAsUser->id);
});

it('rejects a recipient_type outside the role allow-list (spec 0090 D-4, AC-008)', function () {
    Sanctum::actingAs(recipientCrudActor(['create']));
    $supplier = Registry::factory()->create();

    $this->postJson('/api/commission-configurations', recipientCrudPayload([
        'recipient_role' => 'SUPPLIER',
        'recipient_id' => $supplier->id,
        'recipient_type' => 'user',
    ]))->assertUnprocessable()->assertJsonValidationErrors('recipient_type');
});

it('refuses to change recipient_role toward a type no longer admitted without resubmitting recipient_id, but allows a role change that keeps it admitted (spec 0090 D-9, AC-012/AC-018)', function () {
    Sanctum::actingAs(recipientCrudActor(['create', 'view', 'update']));
    $commercial = Referent::factory()->create();
    $id = $this->postJson('/api/commission-configurations', recipientCrudPayload(['recipient_id' => $commercial->id]))
        ->assertCreated()->json('data.id');

    // COMMERCIAL(referent) -> SUPPLIER: SUPPLIER only admits `registry` (INV-4) -> blocked.
    $this->patchJson("/api/commission-configurations/{$id}", ['recipient_role' => 'SUPPLIER'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('recipient_id');

    // COMMERCIAL(referent) -> SUPERVISOR: SUPERVISOR's allow-list also admits
    // `referent` (spec 0090 D-4), so this is now lawful and keeps the SAME
    // recipient without resubmitting recipient_id (AC-018).
    $this->patchJson("/api/commission-configurations/{$id}", ['recipient_role' => 'SUPERVISOR'])
        ->assertOk()
        ->assertJsonPath('data.recipient_type', 'referent')
        ->assertJsonPath('data.recipient_id', $commercial->id);

    $supervisor = User::factory()->create();
    $this->patchJson("/api/commission-configurations/{$id}", [
        'recipient_role' => 'SUPERVISOR',
        'recipient_id' => $supervisor->id,
    ])->assertOk()
        ->assertJsonPath('data.recipient_type', 'user')
        ->assertJsonPath('data.recipient_id', $supervisor->id);
});

it('requires recipient_id alongside a submitted recipient_type, never repainting the persisted id under a new table (spec 0090 R-3)', function () {
    Sanctum::actingAs(recipientCrudActor(['create', 'view', 'update']));
    $commercial = Referent::factory()->create();
    $id = $this->postJson('/api/commission-configurations', recipientCrudPayload(['recipient_id' => $commercial->id]))
        ->assertCreated()->json('data.id');

    $this->patchJson("/api/commission-configurations/{$id}", ['recipient_type' => 'user'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('recipient_id');

    expect(CommissionConfiguration::findOrFail($id))
        ->recipient_type->toBe('referent')
        ->recipient_id->toBe($commercial->id);
});

it('clears the recipient back to a role-wide rule when recipient_id is set to null (AC-013)', function () {
    Sanctum::actingAs(recipientCrudActor(['create', 'view', 'update']));
    $commercial = Referent::factory()->create();
    $id = $this->postJson('/api/commission-configurations', recipientCrudPayload(['recipient_id' => $commercial->id]))
        ->assertCreated()->json('data.id');

    $this->patchJson("/api/commission-configurations/{$id}", ['recipient_id' => null])
        ->assertOk()
        ->assertJsonPath('data.recipient_type', null)
        ->assertJsonPath('data.recipient_id', null);

    expect(CommissionConfiguration::findOrFail($id))
        ->recipient_type->toBeNull()
        ->recipient_id->toBeNull();
});

it('omits recipient_id, recipient_type and recipient from the response when the field is hidden (AC-014)', function () {
    foreach (['viewAny', 'view', 'create'] as $ability) {
        Permission::findOrCreate("commission-configurations.{$ability}");
    }
    $role = Role::create(['name' => fake()->unique()->slug()]);
    $role->givePermissionTo(['commission-configurations.viewAny', 'commission-configurations.view', 'commission-configurations.create']);
    $role->fieldPermissions()->create([
        'resource' => 'commission-configurations',
        'field' => 'recipient_id',
        'visible' => false,
        'editable' => false,
        'required' => false,
    ]);
    $actor = User::factory()->create();
    $actor->assignRole($role);
    Sanctum::actingAs($actor);

    $commercial = Referent::factory()->create();
    $configuration = CommissionConfiguration::factory()
        ->forRecipient('referent', $commercial->id, CommissionApplicationScope::Product)
        ->create(['recipient_role' => CommissionRecipientRole::Commercial, 'product_id' => Product::factory()->create()->id]);

    $detail = $this->getJson("/api/commission-configurations/{$configuration->id}")
        ->assertOk()
        ->assertJsonPath('permissions.fields.recipient_id.hidden', true)
        ->json('data');

    expect($detail)->not->toHaveKeys(['recipient_id', 'recipient_type', 'recipient']);
});

it('rejects a recipient_id submission with 422, not 403, when the actor lacks editable field permission on it (AC-014)', function () {
    // Same shape as QuoteCommissionIntegrationTest's locked commission_value
    // case: visible but NOT editable — the base create/update ability is
    // present, so EnforcesFieldPermissions is the ONLY thing standing in the
    // way, and it fails as a 422 validation error, never a 403.
    foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
        Permission::findOrCreate("commission-configurations.{$ability}");
    }
    $role = Role::create(['name' => fake()->unique()->slug()]);
    $role->givePermissionTo([
        'commission-configurations.viewAny',
        'commission-configurations.view',
        'commission-configurations.create',
        'commission-configurations.update',
    ]);
    $role->fieldPermissions()->create([
        'resource' => 'commission-configurations',
        'field' => 'recipient_id',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);
    $actor = User::factory()->create();
    $actor->assignRole($role);
    Sanctum::actingAs($actor);
    $commercial = Referent::factory()->create();

    $this->postJson('/api/commission-configurations', recipientCrudPayload(['recipient_id' => $commercial->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('recipient_id');

    $configuration = CommissionConfiguration::factory()->create([
        'recipient_role' => CommissionRecipientRole::Commercial,
        'application_scope' => CommissionApplicationScope::Product,
        'product_id' => Product::factory()->create()->id,
    ]);

    $this->patchJson("/api/commission-configurations/{$configuration->id}", ['recipient_id' => $commercial->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('recipient_id');
});

it('rejects a submitted recipient_type when recipient_id is locked, even resubmitting the SAME id (spec 0090)', function () {
    // `recipient_type` is not its own field permission (D-12, unchanged): it
    // inherits recipient_id's. Without that inheritance, resubmitting the
    // SAME numeric id under a DIFFERENT type would pass EnforcesFieldPermissions'
    // value-diff check on recipient_id (unchanged) undetected, letting a
    // locked actor silently repoint who gets paid (referent -> user).
    foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
        Permission::findOrCreate("commission-configurations.{$ability}");
    }
    $role = Role::create(['name' => fake()->unique()->slug()]);
    $role->givePermissionTo([
        'commission-configurations.viewAny',
        'commission-configurations.view',
        'commission-configurations.create',
        'commission-configurations.update',
    ]);
    $role->fieldPermissions()->create([
        'resource' => 'commission-configurations',
        'field' => 'recipient_id',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);
    $actor = User::factory()->create();
    $actor->assignRole($role);
    Sanctum::actingAs($actor);

    $commercial = Referent::factory()->create();
    $configuration = CommissionConfiguration::factory()
        ->forRecipient('referent', $commercial->id, CommissionApplicationScope::Product)
        ->create(['recipient_role' => CommissionRecipientRole::Commercial, 'product_id' => Product::factory()->create()->id]);

    $this->patchJson("/api/commission-configurations/{$configuration->id}", [
        'recipient_type' => 'user',
        'recipient_id' => $commercial->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('recipient_type');

    expect(CommissionConfiguration::findOrFail($configuration->id))
        ->recipient_type->toBe('referent')
        ->recipient_id->toBe($commercial->id);
});
