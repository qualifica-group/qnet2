<?php

use App\Models\BusinessFunction;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\PersonalData;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// POST /api/request-management, new-client branch (user directive
// 2026-09-09): the Anagrafica this branch creates goes through the SAME
// identity gate the anagrafica form applies (directive 2026-08-06) — a codice
// fiscale, a partita IVA or a phone number already held by a user, an
// anagrafica or a referente is refused with a 422, so the operator attaches
// the request to the existing anagrafica instead of forking it.
//
// The shared fixture helpers are re-declared under `function_exists` exactly
// as RequestManagementCreateTest/CreateAttributionTest declare them, so this
// file also runs on its own.

uses(RefreshDatabase::class);

if (! function_exists('requestManagementCreatorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function requestManagementCreatorWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'export', 'viewActivity', 'viewAll', 'assignOperator'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('aSourceId')) {
    /** The Fonte every successful create must carry: mandatory since the user directive 2026-07-29. */
    function aSourceId(): int
    {
        return Source::factory()->create()->id;
    }
}

if (! function_exists('oneProductLine')) {
    /**
     * @return array<int, array{business_function_id: int, product_category_id: int}>
     */
    function oneProductLine(): array
    {
        $businessFunction = BusinessFunction::factory()->create();
        $category = ProductCategory::factory()->create(['business_function_id' => $businessFunction->id]);

        return [['business_function_id' => $businessFunction->id, 'product_category_id' => $category->id]];
    }
}

if (! function_exists('decoyOpportunity')) {
    /**
     * A throwaway Opportunity, created before the real POST under test so its
     * id can never coincide with the freshly-created Offerta's own id.
     */
    function decoyOpportunity(): void
    {
        Opportunity::factory()->create();
    }
}

if (! function_exists('newClientPayload')) {
    /**
     * The smallest accepted new-client payload, with the given overrides
     * merged into its `client_identity`/`client_contacts` blocks.
     *
     * @param  array<string, mixed>  $identity
     * @param  array<int, array<string, mixed>>  $contacts
     * @return array<string, mixed>
     */
    function newClientPayload(array $identity = [], array $contacts = []): array
    {
        return [
            'client_identity' => array_merge([
                'type' => 'individual',
                'first_name' => 'Mario',
                'last_name' => 'Rossi',
            ], $identity),
            'client_contacts' => $contacts,
            'product_lines' => oneProductLine(),
            'source_id' => aSourceId(),
        ];
    }
}

if (! function_exists('assertNoRequestCreated')) {
    function assertNoRequestCreated(): void
    {
        expect(Opportunity::count())->toBe(0)
            ->and(Quote::count())->toBe(0);
    }
}

// ---------------------------------------------------------------------------
// tax_code / vat_number
// ---------------------------------------------------------------------------

it('refuses a client_identity.tax_code already held by an anagrafica (422)', function () {
    $actor = requestManagementCreatorWith(['create']);
    PersonalData::factory()->individual()->for(Registry::factory()->create(), 'personable')
        ->create(['tax_code' => 'RSSMRA80A01H501U']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', newClientPayload(['tax_code' => 'rssmra80a01h501u']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['client_identity.tax_code']);

    assertNoRequestCreated();
});

it('refuses a client_identity.tax_code already held by a REFERENTE (422)', function () {
    $actor = requestManagementCreatorWith(['create']);
    PersonalData::factory()->individual()->for(Referent::factory()->create(), 'personable')
        ->create(['tax_code' => 'RSSMRA80A01H501U']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', newClientPayload(['tax_code' => 'RSSMRA80A01H501U']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['client_identity.tax_code']);

    assertNoRequestCreated();
});

it('refuses a client_identity.vat_number already held inside the namespace (422)', function () {
    $actor = requestManagementCreatorWith(['create']);
    PersonalData::factory()->company()->for(Registry::factory()->create(), 'personable')
        ->create(['vat_number' => '01234567890']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', newClientPayload([
        'type' => 'company',
        'first_name' => null,
        'last_name' => null,
        'company_name' => 'Acme Srl',
        'vat_number' => '01234567890',
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['client_identity.vat_number']);

    assertNoRequestCreated();
});

// ---------------------------------------------------------------------------
// phone / mobile — one pooled namespace, as on the anagrafica form
// ---------------------------------------------------------------------------

it('refuses a client phone already assigned to another record (422)', function () {
    $actor = requestManagementCreatorWith(['create']);
    $card = PersonalData::factory()->individual()->for(Registry::factory()->create(), 'personable')->create();
    Contact::factory()->phone()->for($card, 'contactable')->create(['value' => '+39 333 1234567']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', newClientPayload([], [
        ['type' => 'phone', 'value' => '+39 333 1234-567', 'is_primary' => true],
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['client_contacts.0.value']);

    assertNoRequestCreated();
});

it('refuses a client mobile colliding with another card PHONE (one pooled namespace) (422)', function () {
    $actor = requestManagementCreatorWith(['create']);
    $card = PersonalData::factory()->individual()->for(Referent::factory()->create(), 'personable')->create();
    Contact::factory()->phone()->for($card, 'contactable')->create(['value' => '3331234567']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', newClientPayload([], [
        ['type' => 'mobile', 'value' => '333 1234567', 'is_primary' => true],
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['client_contacts.0.value']);

    assertNoRequestCreated();
});

it('refuses the same number submitted twice on the client card (422)', function () {
    $actor = requestManagementCreatorWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', newClientPayload([], [
        ['type' => 'phone', 'value' => '3331234567', 'is_primary' => true],
        ['type' => 'mobile', 'value' => '333 1234-567'],
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['client_contacts.1.value']);

    assertNoRequestCreated();
});

// ---------------------------------------------------------------------------
// The gate never fires on a free value, nor on the existing-registry branch
// ---------------------------------------------------------------------------

it('accepts a client whose CF, P.IVA and phone are all free (201)', function () {
    $actor = requestManagementCreatorWith(['create']);
    // Another card in the namespace, holding a DIFFERENT codice fiscale: the
    // gate must not fire on a value nobody carries.
    PersonalData::factory()->individual()->for(Registry::factory()->create(), 'personable')
        ->create(['tax_code' => 'LVLDAA80A01H501V']);
    decoyOpportunity();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', newClientPayload([
        'tax_code' => 'RSSMRA80A01H501U',
    ], [
        ['type' => 'phone', 'value' => '3339998877', 'is_primary' => true],
    ]))->assertCreated();
});

it('leaves the existing-registry branch untouched even when that registry holds the values (201)', function () {
    $actor = requestManagementCreatorWith(['create']);
    $registry = Registry::factory()->create();
    $card = PersonalData::factory()->individual()->for($registry, 'personable')
        ->create(['tax_code' => 'RSSMRA80A01H501U']);
    Contact::factory()->phone()->for($card, 'contactable')->create(['value' => '3331234567']);
    decoyOpportunity();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', [
        'registry_id' => $registry->id,
        'product_lines' => oneProductLine(),
        'source_id' => aSourceId(),
    ])->assertCreated();
});
