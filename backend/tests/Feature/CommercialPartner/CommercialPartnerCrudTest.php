<?php

use App\Models\Opportunity;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0417 AC-002 / AC-003 / AC-005: the "Partner commerciale"
 * (`commercial_partner_id`) is a Referent written and read exactly like the
 * Commerciale on registries and opportunities, and exposed in the for-select
 * meta.
 */
uses(RefreshDatabase::class);

/**
 * @param  array<int, string>  $abilities
 * @param  array<string, mixed>|null  $matrixRow  a single role_field_permissions row
 */
function partnerCrudActor(string $resource, array $abilities, ?array $matrixRow = null): User
{
    $role = Role::create(['name' => 'partner-crud-role-'.uniqid()]);

    foreach (['viewAny', 'view', 'create', 'update', 'viewAll'] as $ability) {
        Permission::findOrCreate("{$resource}.{$ability}");
    }

    $role->givePermissionTo(array_map(static fn (string $ability): string => "{$resource}.{$ability}", [...$abilities, 'view', 'viewAll']));

    if ($matrixRow !== null) {
        $role->fieldPermissions()->create($matrixRow);
    }

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

// ---------------------------------------------------------------------------
// AC-002 — registries
// ---------------------------------------------------------------------------

it('AC-002: POST registry saves the partner and returns it', function () {
    $partner = Referent::factory()->create(['name' => 'Paola Partner']);
    Sanctum::actingAs(partnerCrudActor('registries', ['create']));

    $this->postJson('/api/registries', [
        'is_supplier' => false,
        'commercial_partner_id' => $partner->id,
        'personal_data' => minimalRegistryProfilePayload(),
    ])->assertCreated()
        ->assertJsonPath('data.commercial_partner_id', $partner->id)
        ->assertJsonPath('data.commercial_partner.id', $partner->id);

    expect(Registry::sole()->commercial_partner_id)->toBe($partner->id);
});

it('AC-002: PATCH registry sets, leaves untouched when absent, and clears the partner', function () {
    $partner = Referent::factory()->create();
    $registry = Registry::factory()->create();
    Sanctum::actingAs(partnerCrudActor('registries', ['update']));

    $this->patchJson("/api/registries/{$registry->id}", ['commercial_partner_id' => $partner->id])
        ->assertOk()
        ->assertJsonPath('data.commercial_partner.id', $partner->id);

    $this->patchJson("/api/registries/{$registry->id}", ['vat_group' => 'VG-1'])->assertOk();
    expect($registry->fresh()->commercial_partner_id)->toBe($partner->id);

    $this->patchJson("/api/registries/{$registry->id}", ['commercial_partner_id' => null])
        ->assertOk()
        ->assertJsonPath('data.commercial_partner', null);
    expect($registry->fresh()->commercial_partner_id)->toBeNull();
});

it('AC-002: GET registry exposes the partner with its primary contacts shape', function () {
    $partner = Referent::factory()->create();
    $registry = Registry::factory()->create(['commercial_partner_id' => $partner->id]);
    Sanctum::actingAs(partnerCrudActor('registries', []));

    $this->getJson("/api/registries/{$registry->id}")
        ->assertOk()
        ->assertJsonPath('data.commercial_partner_id', $partner->id)
        ->assertJsonPath('data.commercial_partner.id', $partner->id)
        ->assertJsonStructure(['data' => ['commercial_partner' => ['id', 'name', 'primary_contacts']]]);
});

it('AC-002: registry 422 on an unknown partner id, without writes', function () {
    $registry = Registry::factory()->create();
    Sanctum::actingAs(partnerCrudActor('registries', ['update']));

    $this->patchJson("/api/registries/{$registry->id}", ['commercial_partner_id' => 999999])
        ->assertStatus(422)
        ->assertJsonValidationErrors('commercial_partner_id');

    expect($registry->fresh()->commercial_partner_id)->toBeNull();
});

it('AC-002: registry 422 when the partner field is not editable for the actor', function () {
    $current = Referent::factory()->create();
    $registry = Registry::factory()->create(['commercial_partner_id' => $current->id]);
    Sanctum::actingAs(partnerCrudActor(
        'registries',
        ['update'],
        ['resource' => 'registries', 'field' => 'commercial_partner_id', 'visible' => true, 'editable' => false, 'required' => false],
    ));

    $this->patchJson("/api/registries/{$registry->id}", ['commercial_partner_id' => Referent::factory()->create()->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('commercial_partner_id');

    expect($registry->fresh()->commercial_partner_id)->toBe($current->id);
});

// ---------------------------------------------------------------------------
// AC-003 — opportunities
// ---------------------------------------------------------------------------

it('AC-003: POST opportunity saves the partner and returns it', function () {
    $partner = Referent::factory()->create(['name' => 'Paola Partner']);
    Sanctum::actingAs(partnerCrudActor('opportunities', ['create']));

    $response = $this->postJson('/api/opportunities', [
        'registry_id' => Registry::factory()->create()->id,
        ...nonDerivableOpportunityFks(),
        'commercial_partner_id' => $partner->id,
    ])->assertCreated()
        ->assertJsonPath('data.commercial_partner_id', $partner->id)
        ->assertJsonPath('data.commercial_partner.name', 'Paola Partner');

    expect(Opportunity::findOrFail($response->json('data.id'))->commercial_partner_id)->toBe($partner->id);
});

it('AC-003: PATCH opportunity sets, leaves untouched when absent, and clears the partner', function () {
    $partner = Referent::factory()->create();
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs(partnerCrudActor('opportunities', ['update']));

    $this->patchJson("/api/opportunities/{$opportunity->id}", ['commercial_partner_id' => $partner->id])
        ->assertOk()
        ->assertJsonPath('data.commercial_partner.id', $partner->id);

    $this->patchJson("/api/opportunities/{$opportunity->id}", ['general_notes' => 'note'])->assertOk();
    expect($opportunity->fresh()->commercial_partner_id)->toBe($partner->id);

    $this->patchJson("/api/opportunities/{$opportunity->id}", ['commercial_partner_id' => null])
        ->assertOk()
        ->assertJsonPath('data.commercial_partner', null);
    expect($opportunity->fresh()->commercial_partner_id)->toBeNull();
});

it('AC-003: opportunity 422 on an unknown partner id, without writes', function () {
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs(partnerCrudActor('opportunities', ['update']));

    $this->patchJson("/api/opportunities/{$opportunity->id}", ['commercial_partner_id' => 999999])
        ->assertStatus(422)
        ->assertJsonValidationErrors('commercial_partner_id');

    expect($opportunity->fresh()->commercial_partner_id)->toBeNull();
});

it('AC-003: opportunity 422 when the partner field is not editable for the actor', function () {
    $current = Referent::factory()->create();
    $opportunity = Opportunity::factory()->create(['commercial_partner_id' => $current->id]);
    Sanctum::actingAs(partnerCrudActor(
        'opportunities',
        ['update'],
        ['resource' => 'opportunities', 'field' => 'commercial_partner_id', 'visible' => true, 'editable' => false, 'required' => false],
    ));

    $this->patchJson("/api/opportunities/{$opportunity->id}", ['commercial_partner_id' => Referent::factory()->create()->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('commercial_partner_id');

    expect($opportunity->fresh()->commercial_partner_id)->toBe($current->id);
});

// ---------------------------------------------------------------------------
// AC-005 — for-select meta
// ---------------------------------------------------------------------------

it('AC-005: registries/for-select exposes meta.commercial_partner, or null', function () {
    $partner = Referent::factory()->create(['name' => 'Paola Partner']);
    $with = Registry::factory()->create(['name' => 'Meta With', 'commercial_partner_id' => $partner->id]);
    $without = Registry::factory()->create(['name' => 'Meta Without']);
    Sanctum::actingAs(partnerCrudActor('registries', []));

    $items = collect($this->getJson('/api/registries/for-select?search=Meta')->assertOk()->json('items'));

    expect($items->firstWhere('id', $with->id)['meta']['commercial_partner'])->toBe(['id' => $partner->id, 'name' => 'Paola Partner'])
        ->and($items->firstWhere('id', $without->id)['meta']['commercial_partner'])->toBeNull();
});

it('AC-005: opportunities/for-select exposes meta.commercial_partner, or null', function () {
    $partner = Referent::factory()->create(['name' => 'Paola Partner']);
    $with = Opportunity::factory()->create(['commercial_partner_id' => $partner->id]);
    $without = Opportunity::factory()->create();
    Sanctum::actingAs(partnerCrudActor('opportunities', []));

    $items = collect($this->getJson('/api/opportunities/for-select')->assertOk()->json('items'));

    expect($items->firstWhere('id', $with->id)['meta']['commercial_partner'])->toBe(['id' => $partner->id, 'name' => 'Paola Partner'])
        ->and($items->firstWhere('id', $without->id)['meta']['commercial_partner'])->toBeNull();
});
