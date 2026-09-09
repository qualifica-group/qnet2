<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// "Note generali" (`opportunities.general_notes`) written from the work panel
// (direttiva utente 2026-09-09: "se non c'e' una nota generale voglio che ci
// sia il componente e che possa essere inserita o modificata, come anche in
// creazione"). The column already existed and the create form already wrote
// it; what is new is the PATCH channel of this module, and with it the
// catalogue entry that gates who may edit it.
//
// The field lives on the parent Opportunity (spec 0086, D-2), so the
// field-permission gate reads its persisted value through `quote.opportunity`
// (UpdateRequestRequest::OPPORTUNITY_FIELDS) — the locked-field pair at the
// bottom is what proves it.

uses(RefreshDatabase::class);

if (! function_exists('generalNotesActor')) {
    /**
     * @param  array<int, string>  $abilities
     * @param  array<string, mixed>|null  $matrixRow  a single role_field_permissions row
     */
    function generalNotesActor(array $abilities = ['view', 'update', 'viewAll'], ?array $matrixRow = null): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $role = Role::create(['name' => 'general-notes-role-'.uniqid()]);
        $role->givePermissionTo(array_map(static fn (string $ability): string => "request-management.{$ability}", $abilities));

        if ($matrixRow !== null) {
            $role->fieldPermissions()->create($matrixRow);
        }

        $actor = User::factory()->create();
        $actor->assignRole($role);

        return $actor;
    }
}

if (! function_exists('quoteWithGeneralNotes')) {
    /** An Offerta whose Opportunity carries (or does not carry) a general note. */
    function quoteWithGeneralNotes(?string $notes = null): Quote
    {
        $opportunity = Opportunity::factory()->create(['general_notes' => $notes]);

        return Quote::factory()->for($opportunity)->create();
    }
}

it('GET exposes general_notes as a top-level field, not as read-only context', function () {
    $actor = generalNotesActor();
    $quote = quoteWithGeneralNotes('Il cliente richiama a settembre.');
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$quote->id}")
        ->assertOk()
        ->assertJsonPath('data.general_notes', 'Il cliente richiama a settembre.')
        ->assertJsonMissingPath('data.context.general_notes');
});

it('PATCH general_notes writes the note of a request that had none', function () {
    $actor = generalNotesActor();
    $quote = quoteWithGeneralNotes();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'general_notes' => 'Chiede il preventivo entro venerdi.',
    ])->assertOk()->assertJsonPath('data.general_notes', 'Chiede il preventivo entro venerdi.');

    expect($quote->opportunity->fresh()->general_notes)->toBe('Chiede il preventivo entro venerdi.');
});

it('PATCH general_notes rewrites an existing note', function () {
    $actor = generalNotesActor();
    $quote = quoteWithGeneralNotes('Prima nota');
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'general_notes' => 'Nota aggiornata',
    ])->assertOk();

    expect($quote->opportunity->fresh()->general_notes)->toBe('Nota aggiornata');
});

it('PATCH general_notes null clears the note', function () {
    $actor = generalNotesActor();
    $quote = quoteWithGeneralNotes('Da cancellare');
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", ['general_notes' => null])
        ->assertOk()
        ->assertJsonPath('data.general_notes', null);

    expect($quote->opportunity->fresh()->general_notes)->toBeNull();
});

it('PATCH without the general_notes key leaves the persisted note untouched — sparse', function () {
    $actor = generalNotesActor();
    $quote = quoteWithGeneralNotes('Da non toccare');
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [])->assertOk();

    expect($quote->opportunity->fresh()->general_notes)->toBe('Da non toccare');
});

it('PATCH general_notes longer than 5000 chars -> 422', function () {
    $actor = generalNotesActor();
    $quote = quoteWithGeneralNotes();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'general_notes' => str_repeat('a', 5001),
    ])->assertStatus(422)->assertJsonValidationErrors('general_notes');

    expect($quote->opportunity->fresh()->general_notes)->toBeNull();
});

// The field-permission gate, on a field that does NOT live on the route-bound
// Quote: without UpdateRequestRequest's OPPORTUNITY_FIELDS override the
// persisted value would read as null off the Quote and even an untouched
// resubmission would count as a change, 422-ing a locked-but-unmodified field.
it('a role with general_notes readonly cannot change it', function () {
    $actor = generalNotesActor(['view', 'update', 'viewAll'], [
        'resource' => 'request-management',
        'field' => 'general_notes',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);
    $quote = quoteWithGeneralNotes('Nota bloccata');
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", ['general_notes' => 'Tentativo'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('general_notes');

    expect($quote->opportunity->fresh()->general_notes)->toBe('Nota bloccata');
});

it('a role with general_notes readonly may still resubmit the persisted note unchanged', function () {
    $actor = generalNotesActor(['view', 'update', 'viewAll'], [
        'resource' => 'request-management',
        'field' => 'general_notes',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);
    $quote = quoteWithGeneralNotes('Nota bloccata');
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", ['general_notes' => 'Nota bloccata'])
        ->assertOk();

    expect($quote->opportunity->fresh()->general_notes)->toBe('Nota bloccata');
});
