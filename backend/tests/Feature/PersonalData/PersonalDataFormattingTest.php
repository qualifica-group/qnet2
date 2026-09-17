<?php

use App\Enums\PersonalDataTypeEnum;
use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Canonical storage of user-typed values (user directive 2026-07-23): however
// a name, a phone or a fiscal code is typed, the SAME value must land in the
// database. The algorithm itself is covered by tests/Unit/Support/InputFormatTest;
// what these assert is that every write surface actually applies it.

uses(RefreshDatabase::class);

if (! function_exists('formattingActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function formattingActor(string $resource, array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("{$resource}.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("{$resource}.{$ability}");
        }

        return $user;
    }
}

it('create: stores the card identity in its canonical shape', function () {
    $actor = formattingActor('personal_data', ['create']);
    $owner = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/personal-data', [
        'personable_type' => 'user',
        'personable_id' => $owner->id,
        'type' => PersonalDataTypeEnum::Individual->value,
        'first_name' => '  ada  ',
        'last_name' => 'LOVELACE',
        'tax_code' => 'lvl daa80a01h501v',
        'sdi_code' => 'abc-1234',
    ])
        ->assertCreated()
        ->assertJsonPath('data.full_name', 'Ada Lovelace');

    $this->assertDatabaseHas('personal_data', [
        'personable_id' => $owner->id,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'tax_code' => 'LVLDAA80A01H501V',
        'sdi_code' => 'ABC1234',
    ]);
});

it('create: title-cases a surname carrying an apostrophe', function () {
    $actor = formattingActor('personal_data', ['create']);
    $owner = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/personal-data', [
        'personable_type' => 'user',
        'personable_id' => $owner->id,
        'type' => PersonalDataTypeEnum::Individual->value,
        'first_name' => 'maria',
        'last_name' => "dell'acqua",
    ])->assertCreated();

    $this->assertDatabaseHas('personal_data', [
        'personable_id' => $owner->id,
        'first_name' => 'Maria',
        'last_name' => "Dell'Acqua",
    ]);
});

it('create: a company card keeps its own casing and drops the IT prefix', function () {
    $actor = formattingActor('personal_data', ['create']);
    $owner = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/personal-data', [
        'personable_type' => 'user',
        'personable_id' => $owner->id,
        'type' => PersonalDataTypeEnum::Company->value,
        'company_name' => '  ACME   S.R.L. ',
        'vat_number' => 'IT 12345678903',
    ])->assertCreated();

    $this->assertDatabaseHas('personal_data', [
        'personable_id' => $owner->id,
        'company_name' => 'ACME S.R.L.',
        'vat_number' => '12345678903',
    ]);
});

it('contact create: stores a phone as digits whatever separators were typed', function (string $typed, string $stored) {
    $actor = formattingActor('personal_data', ['create', 'update']);
    Permission::findOrCreate('contacts.create');
    $actor->givePermissionTo('contacts.create');
    $card = PersonalData::factory()->individual()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/contacts', [
        'contactable_type' => 'personal_data',
        'contactable_id' => $card->id,
        'type' => 'phone',
        'value' => $typed,
    ])->assertCreated()->assertJsonPath('data.value', $stored);
})->with([
    ['333 12 34 567', '3331234567'],
    ['(333) 1234-567', '3331234567'],
    ['+39 333 1234567', '+393331234567'],
]);

it('contact create: lowercases an email address', function () {
    $actor = formattingActor('personal_data', ['create']);
    Permission::findOrCreate('contacts.create');
    $actor->givePermissionTo('contacts.create');
    $card = PersonalData::factory()->individual()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/contacts', [
        'contactable_type' => 'personal_data',
        'contactable_id' => $card->id,
        'type' => 'email',
        'value' => '  Ada.Lovelace@Example.COM ',
    ])->assertCreated()->assertJsonPath('data.value', 'ada.lovelace@example.com');
});

it('contact update: re-formats an existing value', function () {
    $actor = formattingActor('personal_data', ['update']);
    Permission::findOrCreate('contacts.update');
    $actor->givePermissionTo('contacts.update');
    $card = PersonalData::factory()->individual()->create();
    $contact = Contact::factory()->for($card, 'contactable')->create(['type' => 'phone', 'value' => '333 0000000']);
    Sanctum::actingAs($actor);

    $this->putJson("/api/contacts/{$contact->id}", [
        'type' => 'phone',
        'value' => '+39 333 / 000 0000',
    ])->assertOk();

    expect($contact->fresh()->value)->toBe('+393330000000');
});
