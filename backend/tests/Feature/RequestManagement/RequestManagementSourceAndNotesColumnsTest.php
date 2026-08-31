<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// GET /api/tables/request-management/columns + POST .../rows + PATCH
// .../rows/{row} — the two worklist columns added by the user directive
// 2026-07-31: "Fonte" (`source`), an inline-editable relation opening the
// grid, and "Note generali" (`general_notes`), the opportunity's own free
// text beside the products, display-only exactly like the work panel's
// RequestGeneralNotesCallout. Spec 0086, D-1: the row is now a `quotes`
// record.

uses(RefreshDatabase::class);

if (! function_exists('worklistColumnsActor')) {
    /**
     * @param  array<int, string>  $abilities  request-management abilities
     */
    function worklistColumnsActor(array $abilities): User
    {
        // updateSource included in the catalogue below: source_id became a
        // protected field (spec 0078, ProtectedFieldAwareAuthorization) —
        // callers that pass it in $abilities exercise the source column's
        // write path, not the new restriction.
        foreach (['viewAny', 'view', 'update', 'viewAll', 'updateSource'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('worklistColumnsRequest')) {
    /** A quote the actor supervises (the module's own row scope). */
    function worklistColumnsRequest(User $operator): Quote
    {
        $opportunity = Opportunity::factory()->create();
        $opportunity->managers()->sync([$operator->id => ['position' => 2]]);

        return Quote::factory()->for($opportunity)->create(['operator_id' => $operator->id]);
    }
}

if (! function_exists('worklistColumns')) {
    /** @return Collection<string, array<string, mixed>> */
    function worklistColumns(): Collection
    {
        return collect(test()->getJson('/api/tables/request-management/columns')->assertOk()->json('data.columns'))
            ->keyBy('id');
    }
}

// ---------------------------------------------------------------------------
// The config the grid builds its columns from
// ---------------------------------------------------------------------------

it('source opens the worklist and advertises a relation editor over sources', function () {
    Sanctum::actingAs(worklistColumnsActor(['viewAny', 'update']));

    $columns = worklistColumns();
    $source = $columns['source'];

    expect($source['editable'])->toBeTrue()
        ->and($source['editor'])->toBe('relation')
        ->and($source['relation']['resource'])->toBe('sources')
        ->and($source['sortable'])->toBeTrue()
        ->and($source['filterType'])->toBe('set')
        // "tra le prime colonne": declared before every other worklist column.
        ->and($source['order'])->toBeLessThan($columns['product_categories']['order']);
});

// User directive 2026-08-31: `quote_workflow_status` ("Stato di lavorazione")
// now sits BETWEEN the two — the operator reads what the offer contains, then
// where it stands. `general_notes` keeps its place right after that block, and
// its display-only contract is untouched.
it('general_notes follows the offer_lines block and stays display-only', function () {
    Sanctum::actingAs(worklistColumnsActor(['viewAny', 'update']));

    $columns = worklistColumns();
    $notes = $columns['general_notes'];

    expect($notes['editable'])->toBeFalse()
        ->and($notes)->not->toHaveKey('editor')
        ->and($notes['filterType'])->toBe('text')
        ->and($columns['quote_workflow_status']['order'])->toBe($columns['offer_lines']['order'] + 1)
        ->and($notes['order'])->toBe($columns['quote_workflow_status']['order'] + 1);
});

// ---------------------------------------------------------------------------
// Row projection
// ---------------------------------------------------------------------------

it('rows: source surfaces as an {id, name} ref and general_notes as raw text', function () {
    $actor = worklistColumnsActor(['viewAny', 'view']);
    $source = Source::factory()->create(['name' => 'Fiera di settore']);
    $quote = worklistColumnsRequest($actor);
    $quote->opportunity->update(['source_id' => $source->id, 'general_notes' => "riga 1\nriga 2"]);
    Sanctum::actingAs($actor);

    $row = collect($this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'))->firstWhere('id', $quote->id);

    expect($row['source'])->toBe(['id' => $source->id, 'name' => 'Fiera di settore'])
        ->and($row['general_notes'])->toBe("riga 1\nriga 2");
});

it('rows: a quote without a source projects a null ref', function () {
    $actor = worklistColumnsActor(['viewAny', 'view']);
    $quote = worklistColumnsRequest($actor);
    Sanctum::actingAs($actor);

    $row = collect($this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'))->firstWhere('id', $quote->id);

    expect($row['source'])->toBeNull()
        ->and($row['general_notes'])->toBeNull();
});

// ---------------------------------------------------------------------------
// The write path — through RequestManagementService::updateWork()'s
// applyOpportunitySource(), never a plain $row->update()
// ---------------------------------------------------------------------------

it('PATCH source persists the FK and returns the row with the new ref', function () {
    $actor = worklistColumnsActor(['viewAny', 'update', 'updateSource']);
    $quote = worklistColumnsRequest($actor);
    $source = Source::factory()->create(['name' => 'Passaparola']);
    Sanctum::actingAs($actor);

    $row = $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'source',
        'value' => $source->id,
    ])->assertOk()->json('data');

    expect($quote->opportunity->fresh()->source_id)->toBe($source->id)
        ->and($row['source'])->toBe(['id' => $source->id, 'name' => 'Passaparola']);
});

// `source_id` is mandatory in RequestManagementAuthorization (user directive
// 2026-07-29): the resolved field's `required` wins over any column-level
// nullability, so the cell can be changed but never emptied.
it('PATCH source with null -> 422, the previous source untouched', function () {
    $actor = worklistColumnsActor(['viewAny', 'update', 'updateSource']);
    $source = Source::factory()->create();
    $quote = worklistColumnsRequest($actor);
    $quote->opportunity->update(['source_id' => $source->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'source',
        'value' => null,
    ])->assertStatus(422);

    expect($quote->opportunity->fresh()->source_id)->toBe($source->id);
});

it('PATCH source with an unknown id -> 422, nothing written', function () {
    $actor = worklistColumnsActor(['viewAny', 'update', 'updateSource']);
    $quote = worklistColumnsRequest($actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'source',
        'value' => 999999,
    ])->assertStatus(422);

    expect($quote->opportunity->fresh()->source_id)->toBeNull();
});

// 422, not 403: the column is absent from the engine's editable allow-list
// altogether (a STRUCTURAL rejection, before any field-permission check) —
// this module never owns `general_notes`, the opportunities form does.
it('general_notes is not writable inline', function () {
    $actor = worklistColumnsActor(['viewAny', 'update']);
    $quote = worklistColumnsRequest($actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'general_notes',
        'value' => 'scritta dalla griglia',
    ])->assertStatus(422);

    expect($quote->opportunity->fresh()->general_notes)->toBeNull();
});

// ---------------------------------------------------------------------------
// The derived-column machinery the new relation registers (filter/sort/values)
// ---------------------------------------------------------------------------

it('the source set filter matches by the related row name', function () {
    $actor = worklistColumnsActor(['viewAny', 'viewAll']);
    $wanted = Source::factory()->create(['name' => 'Fiera']);
    $other = Source::factory()->create(['name' => 'Telemarketing']);
    $matching = Quote::factory()->for(Opportunity::factory()->state(['source_id' => $wanted->id]))->create();
    $excluded = Quote::factory()->for(Opportunity::factory()->state(['source_id' => $other->id]))->create();
    Sanctum::actingAs($actor);

    $ids = collect($this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['source' => ['filterType' => 'set', 'values' => ['Fiera']]],
    ])->assertOk()->json('items'))->pluck('id');

    expect($ids->all())->toContain($matching->id)
        ->and($ids->all())->not->toContain($excluded->id);
});

it('values: source enumerates the distinct related names in scope', function () {
    $actor = worklistColumnsActor(['viewAny', 'viewAll']);
    $used = Source::factory()->create(['name' => 'Fiera']);
    Source::factory()->create(['name' => 'Mai referenziata']);
    Quote::factory()->for(Opportunity::factory()->state(['source_id' => $used->id]))->create();
    Sanctum::actingAs($actor);

    $values = $this->postJson('/api/tables/request-management/values', ['columnId' => 'source'])
        ->assertOk()->json('data.values');

    expect($values)->toContain('Fiera')
        ->and($values)->not->toContain('Mai referenziata');
});
