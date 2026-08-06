<?php

use App\Models\QuoteWorkflow;
use App\Models\QuoteWorkflowStatus;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('quoteWorkflowUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function quoteWorkflowUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("quote-workflows.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("quote-workflows.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// `description` + `requires_note` on a working status (spec 0047 amendment)
// ---------------------------------------------------------------------------

it('create: persists description/requires_note on a custom row and seeds them on the pinned system row', function () {
    $actor = quoteWorkflowUserWith(['create']);
    $source = Source::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/quote-workflows', [
        'name' => 'Descriptive workflow',
        'criteria' => [['field' => 'source_id', 'value_id' => $source->id]],
        'statuses' => [
            [
                'name' => 'Aperta',
                'description' => 'Prima presa in carico',
                'color' => null,
                'group' => 'open',
                'requires_note' => false,
                'system_key' => 'open',
            ],
            [
                'name' => 'Attesa documenti',
                'description' => 'In attesa dei documenti del cliente',
                'color' => 'orange',
                'group' => 'pending',
                'requires_note' => true,
            ],
        ],
    ])->assertCreated();

    $response->assertJsonPath('data.statuses.0.description', 'Prima presa in carico')
        ->assertJsonPath('data.statuses.0.requires_note', false)
        ->assertJsonPath('data.statuses.1.name', 'Attesa documenti')
        ->assertJsonPath('data.statuses.1.description', 'In attesa dei documenti del cliente')
        ->assertJsonPath('data.statuses.1.requires_note', true);

    $this->assertDatabaseHas('quote_workflow_statuses', [
        'name' => 'Attesa documenti',
        'description' => 'In attesa dei documenti del cliente',
        'requires_note' => true,
    ]);
});

it('create: omitted description/requires_note default to null/false', function () {
    $actor = quoteWorkflowUserWith(['create']);
    $source = Source::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/quote-workflows', [
        'name' => 'Bare workflow',
        'criteria' => [['field' => 'source_id', 'value_id' => $source->id]],
        'statuses' => [['name' => 'In lavorazione', 'color' => null, 'group' => 'open']],
    ])->assertCreated()
        ->assertJsonPath('data.statuses.1.description', null)
        ->assertJsonPath('data.statuses.1.requires_note', false);
});

it('update: a SYSTEM row accepts a description/requires_note change (its group still cannot change)', function () {
    $actor = quoteWorkflowUserWith(['update']);
    $workflow = QuoteWorkflow::factory()->create();
    $openRow = QuoteWorkflowStatus::factory()->system('open')->create(['quote_workflow_id' => $workflow->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quote-workflows/{$workflow->id}", [
        'statuses' => [
            [
                'id' => $openRow->id,
                'name' => $openRow->name,
                'description' => 'Stato iniziale, nessuna nota necessaria',
                'color' => null,
                'group' => 'open',
                'requires_note' => true,
            ],
        ],
    ])->assertOk();

    expect($openRow->fresh())
        ->description->toBe('Stato iniziale, nessuna nota necessaria')
        ->requires_note->toBeTrue();
});

it('update: a CUSTOM row round-trips both fields, and clearing the description writes null', function () {
    $actor = quoteWorkflowUserWith(['update']);
    $workflow = QuoteWorkflow::factory()->create();
    $customRow = QuoteWorkflowStatus::factory()->create([
        'quote_workflow_id' => $workflow->id,
        'description' => 'Vecchia descrizione',
        'requires_note' => true,
    ]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quote-workflows/{$workflow->id}", [
        'statuses' => [
            [
                'id' => $customRow->id,
                'name' => $customRow->name,
                'description' => null,
                'color' => null,
                'group' => 'pending',
                'requires_note' => false,
            ],
        ],
    ])->assertOk();

    expect($customRow->fresh())
        ->description->toBeNull()
        ->requires_note->toBeFalse();
});

it('default-statuses: PUT round-trips description/requires_note on the GLOBAL set', function () {
    $actor = quoteWorkflowUserWith(['view', 'update']);
    // The GLOBAL set is seeded by the migration itself — reuse its pinned
    // 'open' row rather than creating a second one.
    $globalOpen = QuoteWorkflowStatus::query()
        ->whereNull('quote_workflow_id')
        ->where('system_key', 'open')
        ->sole();
    Sanctum::actingAs($actor);

    $this->putJson('/api/quote-workflows/default-statuses', [
        'statuses' => [
            [
                'id' => $globalOpen->id,
                'name' => 'Aperta',
                'description' => 'Default globale',
                'color' => null,
                'group' => 'open',
                'requires_note' => false,
            ],
            [
                'name' => 'Da richiamare',
                'description' => 'Richiede sempre una nota di esito',
                'color' => 'orange',
                'group' => 'pending',
                'requires_note' => true,
            ],
        ],
    ])->assertOk()
        ->assertJsonPath('data.0.description', 'Default globale')
        ->assertJsonPath('data.1.name', 'Da richiamare')
        ->assertJsonPath('data.1.requires_note', true);
});

it('validation: a description over 500 chars and a non-boolean requires_note are rejected with 422', function () {
    $actor = quoteWorkflowUserWith(['create']);
    $source = Source::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/quote-workflows', [
        'name' => 'Invalid statuses',
        'criteria' => [['field' => 'source_id', 'value_id' => $source->id]],
        'statuses' => [
            ['name' => 'Troppo lunga', 'description' => str_repeat('a', 501), 'group' => 'open'],
            ['name' => 'Flag errato', 'group' => 'open', 'requires_note' => 'maybe'],
        ],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['statuses.0.description', 'statuses.1.requires_note']);
});
