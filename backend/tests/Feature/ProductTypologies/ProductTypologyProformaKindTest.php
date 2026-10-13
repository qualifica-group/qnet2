<?php

use App\Enums\ProformaRequestKind;
use App\Models\ProductTypology;
use App\Models\User;
use Database\Seeders\ProductTypologySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Proforma kind of a product typology (spec 0247, D-6, AC-010/AC-011).
 */
uses(RefreshDatabase::class);

/**
 * @param  array<int, string>  $abilities
 */
function proformaKindActor(array $abilities = ['viewAny', 'view', 'create', 'update']): User
{
    $user = User::factory()->create();

    foreach ($abilities as $ability) {
        Permission::findOrCreate("product-typologies.{$ability}");
        $user->givePermissionTo("product-typologies.{$ability}");
    }

    return $user;
}

it('AC-011: PATCH accepts a valid proforma kind and null, rejects a value outside the enum', function () {
    Sanctum::actingAs(proformaKindActor());
    $typology = ProductTypology::factory()->create();

    $this->getJson("/api/product-typologies/{$typology->id}")
        ->assertOk()
        ->assertJsonPath('data.proforma_kind', null)
        ->assertJsonPath('permissions.fields.proforma_kind.editable', true);

    $this->patchJson("/api/product-typologies/{$typology->id}", ['proforma_kind' => 'institution'])
        ->assertOk()->assertJsonPath('data.proforma_kind', 'institution');
    expect($typology->fresh()->proforma_kind)->toBe(ProformaRequestKind::Institution);

    $this->patchJson("/api/product-typologies/{$typology->id}", ['proforma_kind' => 'supplier'])
        ->assertUnprocessable()->assertJsonValidationErrors('proforma_kind');
    expect($typology->fresh()->proforma_kind)->toBe(ProformaRequestKind::Institution);

    $this->patchJson("/api/product-typologies/{$typology->id}", ['proforma_kind' => null])
        ->assertOk()->assertJsonPath('data.proforma_kind', null);
});

it('AC-011: create stores the proforma kind, absent means none', function () {
    Sanctum::actingAs(proformaKindActor());

    $this->postJson('/api/product-typologies', ['name' => 'Ente pubblico', 'code' => 'public_body', 'color' => 'gray', 'proforma_kind' => 'institution'])
        ->assertCreated()->assertJsonPath('data.proforma_kind', 'institution');

    $this->postJson('/api/product-typologies', ['name' => 'Formazione', 'code' => 'training', 'color' => 'gray'])
        ->assertCreated()->assertJsonPath('data.proforma_kind', null);

    $this->postJson('/api/product-typologies', ['name' => 'Altro', 'code' => 'other', 'color' => 'gray', 'proforma_kind' => 'both'])
        ->assertUnprocessable()->assertJsonValidationErrors('proforma_kind');
});

it('AC-011: a role without update permission cannot change the proforma kind and sees it readonly', function () {
    Sanctum::actingAs(proformaKindActor(['viewAny', 'view']));
    $typology = ProductTypology::factory()->create();

    $this->getJson("/api/product-typologies/{$typology->id}")
        ->assertOk()->assertJsonPath('permissions.fields.proforma_kind.editable', false);

    $this->patchJson("/api/product-typologies/{$typology->id}", ['proforma_kind' => 'consultancy'])->assertForbidden();
    expect($typology->fresh()->proforma_kind)->toBeNull();
});

it('AC-010: after the migration institution feeds institution requests, the seeder gives consultancy its kind idempotently', function () {
    expect(ProductTypology::where('code', 'institution')->sole()->proforma_kind)->toBe(ProformaRequestKind::Institution);

    $this->seed(ProductTypologySeeder::class);
    $this->seed(ProductTypologySeeder::class);

    expect(ProductTypology::where('code', 'consultancy')->sole()->proforma_kind)->toBe(ProformaRequestKind::Consultancy)
        ->and(ProductTypology::where('code', 'institution')->sole()->proforma_kind)->toBe(ProformaRequestKind::Institution);
});
