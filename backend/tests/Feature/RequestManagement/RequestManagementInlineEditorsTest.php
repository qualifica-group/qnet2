<?php

use App\Enums\ContactTypeEnum;
use App\Enums\PersonalDataTypeEnum;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Registry;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

// PATCH /api/tables/request-management/rows/{row} — the columns spec 0055
// activates: the GA2 operator (relation editor) and the four CLIENT
// anagraphic fields, which live on the Registry's PersonalData card and are
// written one at a time through RequestManagementService::updateWork().
// `workflow_status`/`next_callback_at` keep their own dedicated files.

uses(RefreshDatabase::class);

if (! function_exists('inlineEditorsActor')) {
    /**
     * A direct-permission actor (no role), so the role_field_permissions
     * matrix can never narrow anything — the ceiling alone is in play.
     *
     * @param  array<int, string>  $abilities
     */
    function inlineEditorsActor(array $abilities): User
    {
        foreach (['viewAny', 'view', 'update', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('inlineEditorsActorWithMatrixRow')) {
    /**
     * A role-bearing actor carrying one role_field_permissions row: the DB
     * matrix only ever restricts actors reached through a role.
     *
     * @param  array<string, mixed>  $matrixRow
     */
    function inlineEditorsActorWithMatrixRow(array $matrixRow): User
    {
        foreach (['viewAny', 'view', 'update', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $role = Role::create(['name' => 'inline-editors-role-'.uniqid()]);
        $role->givePermissionTo(['request-management.viewAny', 'request-management.update']);
        $role->fieldPermissions()->create($matrixRow);

        $actor = User::factory()->create();
        $actor->assignRole($role);

        return $actor;
    }
}

if (! function_exists('inlineEditorsRequest')) {
    /** An opportunity the actor operates as GA2, whose client carries an anagraphic card. */
    function inlineEditorsRequest(User $manager): Opportunity
    {
        $registry = Registry::factory()->withPersonalData()->create();
        $opportunity = Opportunity::factory()->create(['registry_id' => $registry->id]);
        $opportunity->managers()->sync([$manager->id => ['position' => 2]]);

        return $opportunity;
    }
}

if (! function_exists('inlineEditorsColumns')) {
    /** @return Collection<string, array<string, mixed>> */
    function inlineEditorsColumns(): Collection
    {
        return collect(test()->getJson('/api/tables/request-management/columns')->assertOk()->json('data.columns'))
            ->keyBy('id');
    }
}

// ---------------------------------------------------------------------------
// AC-001 / AC-002 — the config the grid builds its editors from
// ---------------------------------------------------------------------------

it('AC-001: every activated column advertises its own editor', function () {
    Sanctum::actingAs(inlineEditorsActor(['viewAny', 'update']));

    $columns = inlineEditorsColumns();

    expect($columns['workflow_status']['editor'])->toBe('select')
        ->and($columns['workflow_status']['editable'])->toBeTrue()
        ->and($columns['next_callback_at']['editor'])->toBe('datetime')
        ->and($columns['operator_ga2']['editor'])->toBe('relation')
        ->and($columns['operator_ga2']['relation']['resource'])->toBe('users');

    foreach (['first_name', 'last_name', 'tax_code', 'phone'] as $id) {
        expect($columns[$id]['editable'])->toBeTrue()
            ->and($columns[$id])->not->toHaveKey('editor');
    }
});

it('AC-001: workflow_status options carry requires_note and color per entry', function () {
    Sanctum::actingAs(inlineEditorsActor(['viewAny', 'update']));

    $options = inlineEditorsColumns()['workflow_status']['options'];

    expect($options)->toBeArray()->not->toBeEmpty()
        ->and($options[0])->toHaveKeys(['value', 'label', 'color', 'requires_note']);
});

it('AC-002: product_categories stays read-only', function () {
    Sanctum::actingAs(inlineEditorsActor(['viewAny', 'update']));

    expect(inlineEditorsColumns()['product_categories']['editable'])->toBeFalse();
});

it('AC-002: PATCH product_categories -> 422, never a silent write', function () {
    $actor = inlineEditorsActor(['viewAny', 'update']);
    $opportunity = inlineEditorsRequest($actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$opportunity->id}", [
        'column' => 'product_categories',
        'value' => 'Anything',
    ])->assertStatus(422);
});

// ---------------------------------------------------------------------------
// AC-003 — the update ability gates every activated column
// ---------------------------------------------------------------------------

it('AC-003: without request-management.update every column is read-only and every PATCH is 403', function () {
    $actor = inlineEditorsActor(['viewAny']);
    $opportunity = inlineEditorsRequest($actor);
    Sanctum::actingAs($actor);

    $columns = inlineEditorsColumns();

    foreach (['workflow_status', 'next_callback_at', 'operator_ga2', 'first_name', 'last_name', 'tax_code', 'phone'] as $id) {
        expect($columns[$id]['editable'])->toBeFalse();

        $this->patchJson("/api/tables/request-management/rows/{$opportunity->id}", [
            'column' => $id,
            'value' => 'Whatever',
        ])->assertForbidden();
    }
});

// ---------------------------------------------------------------------------
// AC-004 / AC-005 — the per-field matrix, one key at a time
// ---------------------------------------------------------------------------

it('AC-004: denying client_tax_code leaves the other three anagraphic columns editable', function () {
    $actor = inlineEditorsActorWithMatrixRow([
        'resource' => 'request-management',
        'field' => 'client_tax_code',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);
    $opportunity = inlineEditorsRequest($actor);
    $opportunity->managers()->sync([$actor->id => ['position' => 2]]);
    Sanctum::actingAs($actor);

    $columns = inlineEditorsColumns();

    expect($columns['tax_code']['editable'])->toBeFalse()
        ->and($columns['first_name']['editable'])->toBeTrue()
        ->and($columns['last_name']['editable'])->toBeTrue()
        ->and($columns['phone']['editable'])->toBeTrue();

    $this->patchJson("/api/tables/request-management/rows/{$opportunity->id}", [
        'column' => 'tax_code',
        'value' => 'RSSMRA80A01H501U',
    ])->assertForbidden();

    $this->patchJson("/api/tables/request-management/rows/{$opportunity->id}", [
        'column' => 'first_name',
        'value' => 'Mario',
    ])->assertOk();
});

it('AC-005: a required field rejects a blank value and keeps the persisted one', function () {
    $actor = inlineEditorsActorWithMatrixRow([
        'resource' => 'request-management',
        'field' => 'client_first_name',
        'visible' => true,
        'editable' => true,
        'required' => true,
    ]);
    $opportunity = inlineEditorsRequest($actor);
    $opportunity->managers()->sync([$actor->id => ['position' => 2]]);
    $card = $opportunity->registry->personalData;
    $card->update(['first_name' => 'Mario']);
    Sanctum::actingAs($actor);

    foreach ([null, '', '   '] as $blank) {
        $this->patchJson("/api/tables/request-management/rows/{$opportunity->id}", [
            'column' => 'first_name',
            'value' => $blank,
        ])->assertStatus(422);
    }

    expect($card->fresh()->first_name)->toBe('Mario');
});

// ---------------------------------------------------------------------------
// AC-009 / AC-012 — client identity: sparse write, no collateral blanking
// ---------------------------------------------------------------------------

it('AC-009: PATCH first_name updates only that field and re-derives the registry name', function () {
    $actor = inlineEditorsActor(['viewAny', 'update']);
    $opportunity = inlineEditorsRequest($actor);
    $card = $opportunity->registry->personalData;
    // An INDIVIDUAL card: on a company card `registries.name` derives from the
    // company name, so the re-derivation below would (correctly) not move.
    $card->update(['type' => PersonalDataTypeEnum::Individual, 'first_name' => 'Vecchio', 'last_name' => 'Rossi', 'tax_code' => 'RSSMRA80A01H501U']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$opportunity->id}", [
        'column' => 'first_name',
        'value' => 'Mario',
    ])->assertOk()->assertJsonPath('data.first_name', 'Mario');

    $fresh = $card->fresh();

    expect($fresh->first_name)->toBe('Mario')
        ->and($fresh->last_name)->toBe('Rossi')
        ->and($fresh->tax_code)->toBe('RSSMRA80A01H501U')
        ->and($opportunity->registry->fresh()->name)->toBe('Mario Rossi');
});

it('AC-012: a text column with an editableField validates as a string, not as an id', function () {
    $actor = inlineEditorsActor(['viewAny', 'update']);
    $opportunity = inlineEditorsRequest($actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$opportunity->id}", [
        'column' => 'last_name',
        'value' => 'Bianchi',
    ])->assertOk();

    expect($opportunity->registry->personalData->fresh()->last_name)->toBe('Bianchi');
});

it('a client anagraphic value longer than the column width -> 422', function () {
    $actor = inlineEditorsActor(['viewAny', 'update']);
    $opportunity = inlineEditorsRequest($actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$opportunity->id}", [
        'column' => 'last_name',
        'value' => str_repeat('a', 256),
    ])->assertStatus(422);
});

it('a request whose client has no anagraphic card -> 422, not a silent create', function () {
    $actor = inlineEditorsActor(['viewAny', 'update']);
    $opportunity = Opportunity::factory()->create(['registry_id' => Registry::factory()->create()->id]);
    $opportunity->managers()->sync([$actor->id => ['position' => 2]]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$opportunity->id}", [
        'column' => 'first_name',
        'value' => 'Mario',
    ])->assertStatus(422);
});

// ---------------------------------------------------------------------------
// AC-010 — phone: update in place, never a destructive sync
// ---------------------------------------------------------------------------

it('AC-010: PATCH phone updates the primary telephone row and leaves the other contacts alone', function () {
    $actor = inlineEditorsActor(['viewAny', 'update']);
    $opportunity = inlineEditorsRequest($actor);
    $card = $opportunity->registry->personalData;
    $phone = Contact::factory()->create([
        'contactable_type' => 'personal_data', 'contactable_id' => $card->id,
        'type' => ContactTypeEnum::Mobile, 'value' => '3330000000', 'is_primary' => true,
    ]);
    $email = Contact::factory()->create([
        'contactable_type' => 'personal_data', 'contactable_id' => $card->id,
        'type' => ContactTypeEnum::Email, 'value' => 'mario@example.test', 'is_primary' => true,
    ]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$opportunity->id}", [
        'column' => 'phone',
        'value' => '3331234567',
    ])->assertOk()->assertJsonPath('data.phone', '3331234567');

    expect($phone->fresh()->value)->toBe('3331234567')
        // the row keeps its own kind: a mobile stays a mobile
        ->and($phone->fresh()->type)->toBe(ContactTypeEnum::Mobile)
        ->and($email->fresh())->not->toBeNull();
});

it('AC-010: PATCH phone on a card with no telephone row creates a primary phone contact', function () {
    $actor = inlineEditorsActor(['viewAny', 'update']);
    $opportunity = inlineEditorsRequest($actor);
    $card = $opportunity->registry->personalData;
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$opportunity->id}", [
        'column' => 'phone',
        'value' => '3331234567',
    ])->assertOk();

    $created = $card->contacts()->where('type', ContactTypeEnum::Phone->value)->first();

    expect($created)->not->toBeNull()
        ->and($created->value)->toBe('3331234567')
        ->and((bool) $created->is_primary)->toBeTrue();
});

it('AC-010: clearing phone removes the telephone row instead of leaving an empty one', function () {
    $actor = inlineEditorsActor(['viewAny', 'update']);
    $opportunity = inlineEditorsRequest($actor);
    $card = $opportunity->registry->personalData;
    Contact::factory()->create([
        'contactable_type' => 'personal_data', 'contactable_id' => $card->id,
        'type' => ContactTypeEnum::Phone, 'value' => '3330000000', 'is_primary' => true,
    ]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$opportunity->id}", [
        'column' => 'phone',
        'value' => null,
    ])->assertOk()->assertJsonPath('data.phone', null);

    expect($card->contacts()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-008 — the GA2 operator
// ---------------------------------------------------------------------------

it('AC-008: PATCH operator_ga2 reassigns the GA2 pivot row and re-projects the new operator', function () {
    $actor = inlineEditorsActor(['viewAny', 'update', 'viewAll']);
    $opportunity = inlineEditorsRequest($actor);
    $newOperator = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$opportunity->id}", [
        'column' => 'operator_ga2',
        'value' => $newOperator->id,
    ])->assertOk()->assertJsonPath('data.operator_ga2.id', $newOperator->id);

    expect($opportunity->fresh()->operatorManager()?->id)->toBe($newOperator->id);
});

it('AC-008: an operator id the actor could not pick -> 422', function () {
    $actor = inlineEditorsActor(['viewAny', 'update']);
    $opportunity = inlineEditorsRequest($actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$opportunity->id}", [
        'column' => 'operator_ga2',
        'value' => 999999,
    ])->assertStatus(422);
});

// ---------------------------------------------------------------------------
// AC-011 — audit
// ---------------------------------------------------------------------------

it('AC-011: an anagraphic inline edit leaves an activity entry on the request', function () {
    $actor = inlineEditorsActor(['viewAny', 'update']);
    $opportunity = inlineEditorsRequest($actor);
    $opportunity->registry->personalData->update(['first_name' => 'Vecchio']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$opportunity->id}", [
        'column' => 'first_name',
        'value' => 'Mario',
    ])->assertOk();

    $entry = Activity::query()
        ->where('subject_type', 'opportunity')
        ->where('subject_id', $opportunity->id)
        ->latest('id')
        ->first();

    expect($entry)->not->toBeNull()
        ->and($entry->properties['attributes']['client_first_name'])->toBe('Mario')
        ->and($entry->properties['old']['client_first_name'])->toBe('Vecchio');
});

// ---------------------------------------------------------------------------
// The relation column does NOT depend on the target module's browse right
// ---------------------------------------------------------------------------

it('operator_ga2 is editable, pickable and savable for an actor without users.viewAny', function () {
    $actor = inlineEditorsActor(['viewAny', 'update']);
    $opportunity = inlineEditorsRequest($actor);
    $newOperator = User::factory()->create();
    Sanctum::actingAs($actor);

    // The real complaint (2026-07-31): a commercial actor holds no browse right
    // on the users module, so the cell was advertised read-only, the picker
    // 403'd and the save 422'd. Since ADR 0011 was amended the three layers
    // agree — config, picker and write all accept the same actor.
    expect(inlineEditorsColumns()['operator_ga2']['editable'])->toBeTrue();

    $this->getJson('/api/users/for-select')->assertOk();

    $this->patchJson("/api/tables/request-management/rows/{$opportunity->id}", [
        'column' => 'operator_ga2',
        'value' => $newOperator->id,
    ])->assertOk()->assertJsonPath('data.operator_ga2.id', $newOperator->id);
});
