<?php

use App\Models\CompanySite;
use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('identityDuplicateCheckUserWith')) {
    /**
     * @param  array<int, string>  $permissions  fully qualified, e.g. "referents.create"
     */
    function identityDuplicateCheckUserWith(array $permissions): User
    {
        foreach (['referents', 'registries'] as $resource) {
            foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
                Permission::findOrCreate("{$resource}.{$ability}");
            }
        }

        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission);
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-001 — email, case-insensitive
// ---------------------------------------------------------------------------

it('AC-001: matches an existing referent by case-insensitive email', function () {
    $actor = identityDuplicateCheckUserWith(['referents.create']);
    $referent = Referent::factory()->create();
    $card = PersonalData::factory()->individual()->for($referent, 'personable')->create();
    Contact::factory()->email()->for($card, 'contactable')->create(['value' => 'Mario.Rossi@Example.com']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/identity/duplicate-check', [
        'contacts' => [['type' => 'email', 'value' => 'mario.rossi@example.com']],
    ])->assertOk();

    expect($response->json('data.matches'))->toHaveCount(1)
        ->and($response->json('data.matches.0'))->toMatchArray([
            'owner_type' => 'referent',
            'owner_id' => $referent->id,
            'name' => $referent->name,
            'matched_on' => ['email'],
        ]);
});

// ---------------------------------------------------------------------------
// AC-002 — phone, normalized digits regardless of formatting; different
// digits do not match
// ---------------------------------------------------------------------------

it('AC-002: matches a phone regardless of formatting; different digits do not match', function () {
    $actor = identityDuplicateCheckUserWith(['referents.create']);
    $referent = Referent::factory()->create();
    $card = PersonalData::factory()->individual()->for($referent, 'personable')->create();
    Contact::factory()->phone()->for($card, 'contactable')->create(['value' => '+39 02 1234-567']);
    Sanctum::actingAs($actor);

    $match = $this->postJson('/api/identity/duplicate-check', [
        'contacts' => [['type' => 'phone', 'value' => '+3902 1234567']],
    ])->assertOk();

    expect($match->json('data.matches.0.matched_on'))->toBe(['phone']);

    $noMatch = $this->postJson('/api/identity/duplicate-check', [
        'contacts' => [['type' => 'phone', 'value' => '+39 02 1234-000']],
    ])->assertOk();

    expect($noMatch->json('data.matches'))->toBe([]);
});

// ---------------------------------------------------------------------------
// AC-003 — tax_code, case/whitespace-insensitive
// ---------------------------------------------------------------------------

it('AC-003: matches an existing referent by case/whitespace-insensitive tax_code', function () {
    $actor = identityDuplicateCheckUserWith(['referents.create']);
    $referent = Referent::factory()->create();
    PersonalData::factory()->individual()->for($referent, 'personable')->create(['tax_code' => 'rssmra80a01h501u']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/identity/duplicate-check', [
        'tax_code' => 'RSSMRA80A01H501U ',
    ])->assertOk();

    expect($response->json('data.matches.0'))->toMatchArray([
        'owner_type' => 'referent',
        'owner_id' => $referent->id,
        'matched_on' => ['tax_code'],
    ]);
});

// ---------------------------------------------------------------------------
// AC-101 (directive 2026-09-09) — vat_number is a criterion of its own
// ---------------------------------------------------------------------------

it('AC-101: matches an existing anagrafica by case/whitespace-insensitive vat_number', function () {
    $actor = identityDuplicateCheckUserWith(['registries.create']);
    $registry = Registry::factory()->create();
    PersonalData::factory()->company()->for($registry, 'personable')->create(['vat_number' => '01234567890']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/identity/duplicate-check', [
        'vat_number' => ' 01234567890 ',
    ])->assertOk();

    expect($response->json('data.matches'))->toHaveCount(1)
        ->and($response->json('data.matches.0'))->toMatchArray([
            'owner_type' => 'registry',
            'owner_id' => $registry->id,
            'name' => $registry->name,
            'matched_on' => ['vat_number'],
        ]);
});

it('AC-101: a vat_number nobody carries does not match', function () {
    $actor = identityDuplicateCheckUserWith(['registries.create']);
    PersonalData::factory()->company()->for(Registry::factory()->create(), 'personable')->create(['vat_number' => '01234567890']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/identity/duplicate-check', [
        'vat_number' => '09876543210',
    ])->assertOk();

    expect($response->json('data.matches'))->toBe([]);
});

// ---------------------------------------------------------------------------
// AC-102 (directive 2026-09-09) — the search spans the WHOLE identity
// namespace (users + anagrafiche + referenti), like the blocking write gate
// ---------------------------------------------------------------------------

it('AC-102: a contact belonging to a USER card matches, with owner_type "user"', function () {
    $actor = identityDuplicateCheckUserWith(['referents.create']);
    $holder = User::factory()->create();
    $card = PersonalData::factory()->individual()->for($holder, 'personable')->create();
    Contact::factory()->email()->for($card, 'contactable')->create(['value' => 'account@example.com']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/identity/duplicate-check', [
        'contacts' => [['type' => 'email', 'value' => 'account@example.com']],
    ])->assertOk();

    expect($response->json('data.matches.0'))->toMatchArray([
        'owner_type' => 'user',
        'owner_id' => $holder->id,
        'name' => $holder->name,
        'matched_on' => ['email'],
    ]);
});

it('AC-102: a card owned OUTSIDE the namespace (a company site) never matches', function () {
    $actor = identityDuplicateCheckUserWith(['referents.create']);
    $site = CompanySite::factory()->create();
    $card = PersonalData::factory()->company()->for($site, 'personable')->create(['vat_number' => '01234567890']);
    Contact::factory()->email()->for($card, 'contactable')->create(['value' => 'site@example.com']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/identity/duplicate-check', [
        'vat_number' => '01234567890',
        'contacts' => [['type' => 'email', 'value' => 'site@example.com']],
    ])->assertOk();

    expect($response->json('data.matches'))->toBe([]);
});

// ---------------------------------------------------------------------------
// AC-004 — cumulative matched_on, max 5 / most recent card first
// ---------------------------------------------------------------------------

it('AC-004: a holder matching on multiple criteria appears once with cumulative matched_on', function () {
    $actor = identityDuplicateCheckUserWith(['referents.create']);
    $referent = Referent::factory()->create();
    $card = PersonalData::factory()->individual()->for($referent, 'personable')->create([
        'tax_code' => 'LVLDAA80A01H501V',
        'vat_number' => '01234567890',
    ]);
    Contact::factory()->email()->for($card, 'contactable')->create(['value' => 'dup@example.com']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/identity/duplicate-check', [
        'tax_code' => 'lvldaa80a01h501v',
        'vat_number' => '01234567890',
        'contacts' => [['type' => 'email', 'value' => 'DUP@example.com']],
    ])->assertOk();

    expect($response->json('data.matches'))->toHaveCount(1)
        ->and($response->json('data.matches.0.matched_on'))->toBe(['email', 'tax_code', 'vat_number']);
});

it('AC-004: caps at 5 matches, most recent card first', function () {
    $actor = identityDuplicateCheckUserWith(['referents.create']);
    $referents = Referent::factory()->count(6)->create();

    foreach ($referents as $referent) {
        $card = PersonalData::factory()->individual()->for($referent, 'personable')->create();
        Contact::factory()->email()->for($card, 'contactable')->create(['value' => 'shared@example.com']);
    }

    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/identity/duplicate-check', [
        'contacts' => [['type' => 'email', 'value' => 'shared@example.com']],
    ])->assertOk();

    $expectedIds = $referents->sortByDesc('id')->take(5)->pluck('id')->values()->all();

    expect($response->json('data.matches'))->toHaveCount(5)
        ->and(collect($response->json('data.matches'))->pluck('owner_id')->all())->toBe($expectedIds);
});

// ---------------------------------------------------------------------------
// AC-005 — auth/authz/validation + no PII leak
// ---------------------------------------------------------------------------

it('requires authentication (401)', function () {
    $this->postJson('/api/identity/duplicate-check', ['tax_code' => 'X'])->assertUnauthorized();
});

it('forbids actors who can create neither an anagrafica nor a referente (403)', function () {
    $actor = identityDuplicateCheckUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/identity/duplicate-check', ['tax_code' => 'X'])->assertForbidden();
});

it('allows an actor holding only registries.create (the anagrafica form)', function () {
    $actor = identityDuplicateCheckUserWith(['registries.create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/identity/duplicate-check', ['tax_code' => 'X'])->assertOk();
});

it('rejects a payload with no criteria at all (422)', function () {
    $actor = identityDuplicateCheckUserWith(['referents.create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/identity/duplicate-check', [])->assertStatus(422);
});

it('rejects a payload with only blank criteria (422)', function () {
    $actor = identityDuplicateCheckUserWith(['referents.create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/identity/duplicate-check', [
        'tax_code' => '   ',
        'vat_number' => '  ',
        'contacts' => [['type' => 'email', 'value' => '  ']],
    ])->assertStatus(422);
});

it('rejects an unknown contact type (422)', function () {
    $actor = identityDuplicateCheckUserWith(['referents.create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/identity/duplicate-check', [
        'contacts' => [['type' => 'fax', 'value' => '+39021234567']],
    ])->assertStatus(422);
});

it('the response never contains a contact value, tax code or VAT number', function () {
    $actor = identityDuplicateCheckUserWith(['referents.create']);
    $referent = Referent::factory()->create();
    $card = PersonalData::factory()->individual()->for($referent, 'personable')->create([
        'tax_code' => 'RSSMRA80A01H501U',
        'vat_number' => '01234567890',
    ]);
    Contact::factory()->email()->for($card, 'contactable')->create(['value' => 'leak@example.com']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/identity/duplicate-check', [
        'tax_code' => 'RSSMRA80A01H501U',
    ])->assertOk();

    $body = $response->getContent();

    expect($body)->not->toContain('leak@example.com')
        ->and($body)->not->toContain('RSSMRA80A01H501U')
        ->and($body)->not->toContain('01234567890')
        ->and(array_keys($response->json('data.matches.0')))->toEqualCanonicalizing(['owner_type', 'owner_id', 'name', 'matched_on']);
});
