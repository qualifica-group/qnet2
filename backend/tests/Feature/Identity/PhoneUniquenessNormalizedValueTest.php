<?php

use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// AC-008 (spec 0136 D-6) — `ValidatesPhoneUniqueness::phoneValueTaken` now
// reads the indexed `contacts.normalized_value` column instead of hydrating
// every phone row in the namespace and comparing in PHP
// (`RegistryIdentityUniquenessTest` already covers the field-error shape;
// this file pins the "already assigned" message on
// the value that column comparison must produce).

uses(RefreshDatabase::class);

function phoneUniquenessRegistryUser(): User
{
    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
        Permission::findOrCreate("registries.{$ability}");
    }

    $user = User::factory()->create();
    $user->givePermissionTo(['registries.create', 'registries.update']);

    return $user;
}

/**
 * @param  array<string, mixed>  $contact
 * @return array<string, mixed>
 */
function phoneUniquenessRegistryPayload(array $contact): array
{
    return [
        'is_supplier' => false,
        'personal_data' => [
            'type' => 'individual',
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'contacts' => [array_merge(['is_primary' => true], $contact)],
        ],
    ];
}

it('AC-008: refuses a phone already held, in a completely different format, with the "already assigned" message', function () {
    $actor = phoneUniquenessRegistryUser();
    $holder = Registry::factory()->create();
    $card = PersonalData::factory()->individual()->for($holder, 'personable')->create();
    Contact::factory()->for($card, 'contactable')->create(['type' => 'phone', 'value' => '06.76.54.321']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/registries', phoneUniquenessRegistryPayload([
        'type' => 'phone',
        'value' => '06 (76) 54-321',
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'personal_data.contacts.0.value' => 'The phone number is already assigned to another record.',
        ]);
});

it('AC-008: 200 when a card keeps its own number, submitted in a different format, on update', function () {
    $actor = phoneUniquenessRegistryUser();
    $target = Registry::factory()->create();
    $card = PersonalData::factory()->individual()->for($target, 'personable')->create();
    Contact::factory()->for($card, 'contactable')->create(['type' => 'phone', 'value' => '06.76.54.321']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/registries/{$target->id}", [
        'personal_data' => [
            'type' => 'individual',
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'contacts' => [['type' => 'phone', 'value' => '06 (76) 54-321']],
        ],
    ])->assertOk();
});
