<?php

use App\Models\Address;
use App\Models\City;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\PersonalData;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// The client anagraphic block of the work panel (spec 0049 amendment,
// migrated onto the Quote by spec 0086 D-2): `client_contacts` (authoritative
// sync) and `client_address` (single create-or-update row) written on the
// Registry's PersonalData card — the Registry hangs off the Quote's own
// Opportunity — through PATCH /api/request-management/{quote}.

uses(RefreshDatabase::class);

if (! function_exists('requestManagementUpdaterWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function requestManagementUpdaterWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'update', 'export', 'viewActivity', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

/** A quote operated by $operator whose client carries a personal-data card. */
function quoteWithClientCard(User $operator): Quote
{
    $registry = Registry::factory()->withPersonalData()->create();
    $opportunity = Opportunity::factory()->create(['registry_id' => $registry->id]);

    return Quote::factory()->for($opportunity)->create(['operator_id' => $operator->id]);
}

function clientCardOf(Quote $quote): PersonalData
{
    return $quote->opportunity->registry->personalData;
}

// ---------------------------------------------------------------------------
// Read surface
// ---------------------------------------------------------------------------

it('GET exposes the client primary address alongside the contacts block', function () {
    $actor = requestManagementUpdaterWith(['view']);
    $quote = quoteWithClientCard($actor);
    $card = clientCardOf($quote);
    Address::factory()->create(['addressable_type' => 'personal_data', 'addressable_id' => $card->id, 'line1' => 'Via Secondaria 2', 'is_primary' => false]);
    $primary = Address::factory()->create(['addressable_type' => 'personal_data', 'addressable_id' => $card->id, 'line1' => 'Via Primaria 1', 'is_primary' => true]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$quote->id}")
        ->assertOk()
        ->assertJsonPath('data.client_address.id', $primary->id)
        ->assertJsonPath('data.client_address.line1', 'Via Primaria 1')
        ->assertJsonPath('data.client_contacts.owner.type', 'personal_data');
});

it('GET returns a null client address when the client has none', function () {
    $actor = requestManagementUpdaterWith(['view']);
    $quote = quoteWithClientCard($actor);
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$quote->id}")
        ->assertOk()
        ->assertJsonPath('data.client_address', null);
});

// ---------------------------------------------------------------------------
// Identity — full replace of the card fields, `registries.name` re-derived
// ---------------------------------------------------------------------------

it('GET exposes the client card identity, fiscal identifiers included', function () {
    $actor = requestManagementUpdaterWith(['view']);
    $quote = quoteWithClientCard($actor);
    clientCardOf($quote)->update([
        'type' => 'company',
        'company_name' => 'Acme S.p.A.',
        'tax_code' => '01234567890',
        'vat_number' => 'IT01234567890',
    ]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$quote->id}")
        ->assertOk()
        ->assertJsonPath('data.client_identity.type', 'company')
        ->assertJsonPath('data.client_identity.company_name', 'Acme S.p.A.')
        ->assertJsonPath('data.client_identity.tax_code', '01234567890')
        ->assertJsonPath('data.client_identity.vat_number', 'IT01234567890');
});

it('GET returns a null client identity when the client has no card', function () {
    $actor = requestManagementUpdaterWith(['view']);
    $opportunity = Opportunity::factory()->create(['registry_id' => Registry::factory()->create()->id]);
    $quote = Quote::factory()->for($opportunity)->create(['operator_id' => $actor->id]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$quote->id}")
        ->assertOk()
        ->assertJsonPath('data.client_identity', null);
});

it('PATCH client_identity writes the card and re-derives the client display name', function () {
    $actor = requestManagementUpdaterWith(['update']);
    $quote = quoteWithClientCard($actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'client_identity' => [
            'type' => 'company',
            'company_name' => 'Nuova Ragione Sociale S.r.l.',
            'tax_code' => '09876543217',
            'vat_number' => 'IT09876543217',
            'sdi_code' => 'ABCDEFG',
        ],
        // Stored canonical (user directive 2026-07-23): the optional IT prefix is dropped.
    ])->assertOk()->assertJsonPath('data.client_identity.vat_number', '09876543217');

    $card = clientCardOf($quote->fresh());
    expect($card->company_name)->toBe('Nuova Ragione Sociale S.r.l.');
    expect($card->tax_code)->toBe('09876543217');
    // `registries.name` is denormalized from the card: it must follow the write.
    expect($quote->fresh()->opportunity->registry->name)->toBe('Nuova Ragione Sociale S.r.l.');
});

it('PATCH rejects an individual client identity without the names -> 422', function () {
    $actor = requestManagementUpdaterWith(['update']);
    $quote = quoteWithClientCard($actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'client_identity' => ['type' => 'individual', 'tax_code' => '01234567890'],
    ])->assertStatus(422)->assertJsonValidationErrors(['client_identity.first_name', 'client_identity.last_name']);
});

it('PATCH without client_identity leaves the client card untouched', function () {
    $actor = requestManagementUpdaterWith(['update']);
    $quote = quoteWithClientCard($actor);
    $card = clientCardOf($quote);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [])->assertOk();

    expect($card->fresh()->only(['type', 'first_name', 'last_name', 'company_name', 'tax_code']))
        ->toBe($card->only(['type', 'first_name', 'last_name', 'company_name', 'tax_code']));
});

// ---------------------------------------------------------------------------
// Contacts — authoritative sync
// ---------------------------------------------------------------------------

it('PATCH client_contacts creates, updates and deletes to match the submitted set', function () {
    $actor = requestManagementUpdaterWith(['update']);
    $quote = quoteWithClientCard($actor);
    $card = clientCardOf($quote);
    $kept = Contact::factory()->create(['contactable_type' => 'personal_data', 'contactable_id' => $card->id, 'type' => 'email', 'value' => 'old@example.test']);
    $dropped = Contact::factory()->create(['contactable_type' => 'personal_data', 'contactable_id' => $card->id, 'type' => 'phone', 'value' => '0100000000']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'client_contacts' => [
            ['id' => $kept->id, 'type' => 'email', 'value' => 'new@example.test', 'is_primary' => true],
            ['type' => 'mobile', 'value' => '3331234567'],
        ],
    ])->assertOk();

    expect($kept->fresh()->value)->toBe('new@example.test');
    expect(Contact::query()->whereKey($dropped->id)->exists())->toBeFalse();
    expect($card->contacts()->where('type', 'mobile')->value('value'))->toBe('3331234567');
});

it('PATCH without client_contacts leaves the client contacts untouched', function () {
    $actor = requestManagementUpdaterWith(['update']);
    $quote = quoteWithClientCard($actor);
    $card = clientCardOf($quote);
    Contact::factory()->create(['contactable_type' => 'personal_data', 'contactable_id' => $card->id, 'type' => 'email', 'value' => 'keep@example.test']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [])->assertOk();

    expect($card->contacts()->count())->toBe(1);
});

it('PATCH rejects a client contact whose value does not match its type -> 422', function () {
    $actor = requestManagementUpdaterWith(['update']);
    $quote = quoteWithClientCard($actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'client_contacts' => [['type' => 'email', 'value' => 'not-an-email']],
    ])->assertStatus(422)->assertJsonValidationErrors('client_contacts.0.value');
});

// ---------------------------------------------------------------------------
// Address — single create-or-update row, never an authoritative sync
// ---------------------------------------------------------------------------

it('PATCH client_address without an id creates the client first address', function () {
    $actor = requestManagementUpdaterWith(['update']);
    $quote = quoteWithClientCard($actor);
    $city = City::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'client_address' => ['line1' => 'Via Nuova 10', 'postal_code' => '20100', 'city_id' => $city->id],
    ])->assertOk()->assertJsonPath('data.client_address.line1', 'Via Nuova 10');

    $created = clientCardOf($quote)->addresses()->sole();
    expect($created->postal_code)->toBe('20100')
        ->and($created->is_primary)->toBeTrue();
});

it('PATCH client_address with an id updates that row and keeps the client other addresses', function () {
    $actor = requestManagementUpdaterWith(['update']);
    $quote = quoteWithClientCard($actor);
    $card = clientCardOf($quote);
    $primary = Address::factory()->create(['addressable_type' => 'personal_data', 'addressable_id' => $card->id, 'line1' => 'Via Vecchia 1', 'is_primary' => true, 'site_type' => 'legal_seat']);
    $other = Address::factory()->create(['addressable_type' => 'personal_data', 'addressable_id' => $card->id, 'line1' => 'Via Altra 2', 'is_primary' => false]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'client_address' => ['id' => $primary->id, 'line1' => 'Via Aggiornata 3'],
    ])->assertOk();

    $updated = $primary->fresh();
    expect($updated->line1)->toBe('Via Aggiornata 3')
        // The panel never shows these two: a save here must not reset them.
        ->and($updated->is_primary)->toBeTrue()
        ->and($updated->site_type->value)->toBe('legal_seat');
    expect(Address::query()->whereKey($other->id)->exists())->toBeTrue();
});

it('PATCH client_address with an id owned by another card creates instead of hijacking it', function () {
    $actor = requestManagementUpdaterWith(['update']);
    $quote = quoteWithClientCard($actor);
    $foreign = Address::factory()->create([
        'addressable_type' => 'personal_data',
        'addressable_id' => Registry::factory()->withPersonalData()->create()->personalData->id,
        'line1' => 'Via Estranea 9',
    ]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'client_address' => ['id' => $foreign->id, 'line1' => 'Via Iniettata 1'],
    ])->assertOk();

    expect($foreign->fresh()->line1)->toBe('Via Estranea 9');
    expect(clientCardOf($quote)->addresses()->where('line1', 'Via Iniettata 1')->exists())->toBeTrue();
});

it('PATCH client_address without line1 -> 422', function () {
    $actor = requestManagementUpdaterWith(['update']);
    $quote = quoteWithClientCard($actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'client_address' => ['postal_code' => '20100'],
    ])->assertStatus(422)->assertJsonValidationErrors('client_address.line1');
});

it('PATCH client_contacts on a quote whose client has no card -> 422', function () {
    $actor = requestManagementUpdaterWith(['update']);
    $opportunity = Opportunity::factory()->create(['registry_id' => Registry::factory()->create()->id]);
    $quote = Quote::factory()->for($opportunity)->create(['operator_id' => $actor->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'client_contacts' => [['type' => 'email', 'value' => 'a@example.test']],
    ])->assertStatus(422)->assertJsonValidationErrors('client_contacts');
});
