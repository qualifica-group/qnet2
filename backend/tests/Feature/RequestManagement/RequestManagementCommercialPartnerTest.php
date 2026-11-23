<?php

use App\Models\BusinessFunction;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Role;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0417 AC-013: the Offerta's "Partner commerciale" is read, patched and
 * created from Gestione richieste exactly like the Commerciale (spec 0357).
 */
uses(RefreshDatabase::class);

/**
 * @param  array<int, string>  $abilities
 * @param  array<string, mixed>|null  $matrixRow
 */
function partnerRequestActor(array $abilities = ['viewAny', 'view', 'update', 'viewAll'], ?array $matrixRow = null): User
{
    foreach (['viewAny', 'view', 'create', 'update', 'viewAll', 'assignOperator'] as $ability) {
        Permission::findOrCreate("request-management.{$ability}");
    }

    $role = Role::create(['name' => 'partner-request-role-'.uniqid()]);
    $role->givePermissionTo(array_map(static fn (string $ability): string => "request-management.{$ability}", $abilities));

    if ($matrixRow !== null) {
        $role->fieldPermissions()->create($matrixRow);
    }

    $actor = User::factory()->create();
    $actor->assignRole($role);

    return $actor;
}

/**
 * @return array<string, mixed>
 */
function partnerRequestCreatePayload(): array
{
    $businessFunction = BusinessFunction::factory()->create();

    return [
        'registry_id' => Registry::factory()->create()->id,
        'source_id' => Source::factory()->create()->id,
        'product_lines' => [[
            'business_function_id' => $businessFunction->id,
            'product_category_id' => ProductCategory::factory()->create(['business_function_id' => $businessFunction->id])->id,
        ]],
    ];
}

it('AC-013: GET exposes the Offerta partner, null when absent', function () {
    $partner = Referent::factory()->create();
    $with = Quote::factory()->create(['commercial_partner_id' => $partner->id]);
    $without = Quote::factory()->create();
    Sanctum::actingAs(partnerRequestActor());

    $this->getJson("/api/request-management/{$with->id}")
        ->assertOk()
        ->assertJsonPath('data.commercial_partner_id', $partner->id)
        ->assertJsonPath('data.commercial_partner.name', $partner->name);

    $this->getJson("/api/request-management/{$without->id}")
        ->assertOk()
        ->assertJsonPath('data.commercial_partner_id', null)
        ->assertJsonPath('data.commercial_partner', null);
});

it('AC-013: PATCH writes, keeps when absent and clears the partner', function () {
    $partner = Referent::factory()->create();
    $quote = Quote::factory()->create();
    Sanctum::actingAs(partnerRequestActor());

    $this->patchJson("/api/request-management/{$quote->id}", ['commercial_partner_id' => $partner->id])
        ->assertOk()
        ->assertJsonPath('data.commercial_partner.name', $partner->name);
    expect($quote->fresh()->commercial_partner_id)->toBe($partner->id);

    $this->patchJson("/api/request-management/{$quote->id}", ['general_notes' => 'x'])->assertOk();
    expect($quote->fresh()->commercial_partner_id)->toBe($partner->id);

    $this->patchJson("/api/request-management/{$quote->id}", ['commercial_partner_id' => null])->assertOk();
    expect($quote->fresh()->commercial_partner_id)->toBeNull();
});

it('AC-013: PATCH rejects an unknown referent', function () {
    $quote = Quote::factory()->create();
    Sanctum::actingAs(partnerRequestActor());

    $this->patchJson("/api/request-management/{$quote->id}", ['commercial_partner_id' => 999999])
        ->assertStatus(422)
        ->assertJsonValidationErrors('commercial_partner_id');
});

it('AC-013: PATCH rejects a non-editable field', function () {
    $current = Referent::factory()->create();
    $locked = Quote::factory()->create(['commercial_partner_id' => $current->id]);
    Sanctum::actingAs(partnerRequestActor(
        ['viewAny', 'view', 'update', 'viewAll'],
        ['resource' => 'request-management', 'field' => 'commercial_partner_id', 'visible' => true, 'editable' => false, 'required' => false],
    ));

    $this->patchJson("/api/request-management/{$locked->id}", ['commercial_partner_id' => Referent::factory()->create()->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('commercial_partner_id');
    expect($locked->fresh()->commercial_partner_id)->toBe($current->id);
});

it('AC-013: the panel envelope carries the partner field permission', function () {
    $quote = Quote::factory()->create();
    Sanctum::actingAs(partnerRequestActor());

    $fields = $this->getJson("/api/request-management/{$quote->id}")->assertOk()->json('permissions.fields');

    expect($fields['commercial_partner_id']['visible'])->toBeTrue()
        ->and($fields['commercial_partner_id']['editable'])->toBeTrue();
});

it('AC-013: POST with an existing registry stores the partner on Offerta and Opportunity, not the registry', function () {
    $partner = Referent::factory()->create();
    Sanctum::actingAs(partnerRequestActor(['viewAny', 'view', 'create', 'update', 'viewAll']));
    $payload = partnerRequestCreatePayload();

    $response = $this->postJson('/api/request-management', [...$payload, 'commercial_partner_id' => $partner->id])
        ->assertCreated()
        ->assertJsonPath('data.commercial_partner_id', $partner->id);

    $quote = Quote::with('opportunity')->findOrFail($response->json('data.id'));

    expect($quote->commercial_partner_id)->toBe($partner->id)
        ->and($quote->opportunity->commercial_partner_id)->toBe($partner->id)
        ->and(Registry::findOrFail($payload['registry_id'])->commercial_partner_id)->toBeNull();
});

it('AC-013: POST without the key leaves the partner empty, and rejects an unknown id', function () {
    Sanctum::actingAs(partnerRequestActor(['viewAny', 'view', 'create', 'update', 'viewAll']));

    $response = $this->postJson('/api/request-management', partnerRequestCreatePayload())
        ->assertCreated()
        ->assertJsonPath('data.commercial_partner_id', null);

    expect(Quote::with('opportunity')->findOrFail($response->json('data.id'))->opportunity->commercial_partner_id)->toBeNull();

    $this->postJson('/api/request-management', [...partnerRequestCreatePayload(), 'commercial_partner_id' => 999999])
        ->assertStatus(422)
        ->assertJsonValidationErrors('commercial_partner_id');
});

it('AC-013: POST with a new client registry writes the partner on the registry too', function () {
    $partner = Referent::factory()->create();
    Sanctum::actingAs(requestManagementCreatorWith(['create']));

    $response = $this->postJson('/api/request-management', [
        'client_identity' => ['type' => 'individual', 'first_name' => 'Mario', 'last_name' => 'Rossi'],
        'client_contacts' => [['type' => 'phone', 'value' => '3331234567', 'is_primary' => true]],
        'product_lines' => oneProductLine(),
        'source_id' => aSourceId(),
        'commercial_partner_id' => $partner->id,
    ])->assertCreated();

    $quote = Quote::with('opportunity.registry')->findOrFail($response->json('data.id'));

    expect($quote->commercial_partner_id)->toBe($partner->id)
        ->and($quote->opportunity->commercial_partner_id)->toBe($partner->id)
        ->and($quote->opportunity->registry->commercial_partner_id)->toBe($partner->id);
});
