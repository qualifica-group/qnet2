<?php

use App\Models\PersonalData;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Codice fiscale / partita IVA unique among ANAGRAFICHE (user directive
 * 2026-08-03). `registryUserWith()` is the RegistryCrudTest helper, guarded so
 * either file may be the one that loads it (running a single file loads only
 * that file).
 */
const REGISTRY_TAX_CODE = 'RSSMRA80A01H501U';
const REGISTRY_VAT_NUMBER = '00743110158';

if (! function_exists('registryUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function registryUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("registries.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("registries.{$ability}");
        }

        return $user;
    }
}

/**
 * @param  array<string, mixed>  $identity
 * @return array<string, mixed>
 */
function registryPayloadWith(array $identity): array
{
    return [
        'is_supplier' => false,
        'personal_data' => array_merge([
            'type' => 'individual',
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
        ], $identity),
    ];
}

// ---------------------------------------------------------------------------
// create
// ---------------------------------------------------------------------------

it('create: 422 when the tax code already belongs to another registry', function () {
    $actor = registryUserWith(['create']);
    $existing = Registry::factory()->create();
    PersonalData::factory()->individual()->for($existing, 'personable')->create(['tax_code' => REGISTRY_TAX_CODE]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/registries', registryPayloadWith(['tax_code' => REGISTRY_TAX_CODE]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('personal_data.tax_code');

    expect(Registry::count())->toBe(1);
});

it('create: 422 when the VAT number already belongs to another registry', function () {
    $actor = registryUserWith(['create']);
    $existing = Registry::factory()->create();
    PersonalData::factory()->individual()->for($existing, 'personable')->create(['vat_number' => REGISTRY_VAT_NUMBER]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/registries', registryPayloadWith(['vat_number' => REGISTRY_VAT_NUMBER]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('personal_data.vat_number');
});

it('create: the collision is case/spacing insensitive, not an exact match', function () {
    $actor = registryUserWith(['create']);
    $existing = Registry::factory()->create();
    // Stored as a migrated/factory row would be: never canonicalized by InputFormat.
    PersonalData::factory()->individual()->for($existing, 'personable')->create(['tax_code' => ' rssmra80a01h501u ']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/registries', registryPayloadWith(['tax_code' => REGISTRY_TAX_CODE]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('personal_data.tax_code');
});

it('create: 201 when the same tax code belongs to a REFERENT (scoped per module)', function () {
    $actor = registryUserWith(['create']);
    $referent = Referent::factory()->create();
    PersonalData::factory()->individual()->for($referent, 'personable')->create(['tax_code' => REGISTRY_TAX_CODE]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/registries', registryPayloadWith(['tax_code' => REGISTRY_TAX_CODE]))
        ->assertCreated();
});

it('create: 201 when no fiscal identifier is submitted at all', function () {
    $actor = registryUserWith(['create']);
    $existing = Registry::factory()->create();
    PersonalData::factory()->individual()->for($existing, 'personable')->create(['tax_code' => REGISTRY_TAX_CODE]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/registries', registryPayloadWith([]))->assertCreated();
});

// ---------------------------------------------------------------------------
// update
// ---------------------------------------------------------------------------

it('update: 200 when the registry keeps its own tax code (no self-collision)', function () {
    $actor = registryUserWith(['update']);
    $target = Registry::factory()->create();
    PersonalData::factory()->individual()->for($target, 'personable')->create(['tax_code' => REGISTRY_TAX_CODE]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/registries/{$target->id}", [
        'personal_data' => [
            'type' => 'individual',
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'tax_code' => REGISTRY_TAX_CODE,
        ],
    ])->assertOk();
});

it('update: 422 when taking another registry tax code', function () {
    $actor = registryUserWith(['update']);
    $other = Registry::factory()->create();
    PersonalData::factory()->individual()->for($other, 'personable')->create(['tax_code' => REGISTRY_TAX_CODE]);
    $target = Registry::factory()->create();
    PersonalData::factory()->individual()->for($target, 'personable')->create(['tax_code' => null]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/registries/{$target->id}", [
        'personal_data' => [
            'type' => 'individual',
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'tax_code' => REGISTRY_TAX_CODE,
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('personal_data.tax_code');
});
