<?php

use App\Models\CompanySite;
use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Codice fiscale, partita IVA and phone number unique across the shared
 * identity namespace — users, anagrafiche and referenti (user directive
 * 2026-08-06, superseding the per-module scope of 2026-08-03, under which an
 * anagrafica was checked only against other anagrafiche and its phone was not
 * checked at all).
 *
 * `registryUserWith()` is the RegistryCrudTest helper, guarded so either file
 * may be the one that loads it (running a single file loads only that file).
 */
const REGISTRY_TAX_CODE = 'RSSMRA80A01H501U';
const REGISTRY_VAT_NUMBER = '00743110158';
const REGISTRY_PHONE = '+39 06 7654321';

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
            // Mandatory at creation (user directive 2026-09-07). Deliberately
            // NOT REGISTRY_PHONE: that number is the collision fixture, and a
            // default sharing it would fail every payload on uniqueness.
            'contacts' => [['type' => 'phone', 'value' => '+39 02 1112223', 'is_primary' => true]],
        ], $identity),
    ];
}

/** A card owned by $owner, carrying one contact of the given channel/value. */
function registryHolderWithContact(Model $owner, string $type, string $value): void
{
    $card = PersonalData::factory()->individual()->for($owner, 'personable')->create();
    Contact::factory()->for($card, 'contactable')->create(['type' => $type, 'value' => $value]);
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

it('create: 422 when the same tax code belongs to a REFERENT', function () {
    $actor = registryUserWith(['create']);
    $referent = Referent::factory()->create();
    PersonalData::factory()->individual()->for($referent, 'personable')->create(['tax_code' => REGISTRY_TAX_CODE]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/registries', registryPayloadWith(['tax_code' => REGISTRY_TAX_CODE]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('personal_data.tax_code');
});

it('create: 422 when the same tax code belongs to a USER account', function () {
    $actor = registryUserWith(['create']);
    $holder = User::factory()->create();
    PersonalData::factory()->individual()->for($holder, 'personable')->create(['tax_code' => REGISTRY_TAX_CODE]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/registries', registryPayloadWith(['tax_code' => REGISTRY_TAX_CODE]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('personal_data.tax_code');
});

it('create: 422 when the same VAT number belongs to a USER account', function () {
    $actor = registryUserWith(['create']);
    $holder = User::factory()->create();
    PersonalData::factory()->individual()->for($holder, 'personable')->create(['vat_number' => REGISTRY_VAT_NUMBER]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/registries', registryPayloadWith(['vat_number' => REGISTRY_VAT_NUMBER]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('personal_data.vat_number');
});

it('create: 201 when the tax code belongs to a COMPANY SITE (outside the namespace)', function () {
    $actor = registryUserWith(['create']);
    $site = CompanySite::factory()->create();
    PersonalData::factory()->individual()->for($site, 'personable')->create(['tax_code' => REGISTRY_TAX_CODE]);
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
// create — phone
// ---------------------------------------------------------------------------

it('create: 422 when the phone number already belongs to another anagrafica', function () {
    $actor = registryUserWith(['create']);
    registryHolderWithContact(Registry::factory()->create(), 'phone', REGISTRY_PHONE);
    Sanctum::actingAs($actor);

    $this->postJson('/api/registries', registryPayloadWith([
        'contacts' => [['type' => 'phone', 'value' => REGISTRY_PHONE]],
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('personal_data.contacts.0.value');

    expect(Registry::count())->toBe(1);
});

it('create: 422 when the number belongs to a REFERENT, on the other channel', function () {
    $actor = registryUserWith(['create']);
    registryHolderWithContact(Referent::factory()->create(), 'mobile', REGISTRY_PHONE);
    Sanctum::actingAs($actor);

    $this->postJson('/api/registries', registryPayloadWith([
        'contacts' => [['type' => 'phone', 'value' => REGISTRY_PHONE]],
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('personal_data.contacts.0.value');
});

it('create: 422 when the number belongs to a USER account, formatting aside', function () {
    $actor = registryUserWith(['create']);
    registryHolderWithContact(User::factory()->create(), 'phone', REGISTRY_PHONE);
    Sanctum::actingAs($actor);

    $this->postJson('/api/registries', registryPayloadWith([
        'contacts' => [['type' => 'phone', 'value' => '+39-06/765.43.21']],
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('personal_data.contacts.0.value');
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

it('update: 200 when the anagrafica keeps its own phone number', function () {
    $actor = registryUserWith(['update']);
    $target = Registry::factory()->create();
    registryHolderWithContact($target, 'phone', REGISTRY_PHONE);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/registries/{$target->id}", [
        'personal_data' => [
            'type' => 'individual',
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'contacts' => [['type' => 'phone', 'value' => REGISTRY_PHONE]],
        ],
    ])->assertOk();
});
