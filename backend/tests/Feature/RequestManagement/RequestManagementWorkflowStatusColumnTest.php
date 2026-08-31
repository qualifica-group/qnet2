<?php

declare(strict_types=1);

use App\Models\Note;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// The "Stato di lavorazione" GRID column (user directive 2026-08-31): the
// Offerta's own working state, displayed, sorted, set-filtered and edited
// IN-CELL — the same `note`-carrying path spec 0054 D-5 built for the
// Opportunity's former `workflow_status` column, retargeted at the record
// this module IS since spec 0086. The panel channel (already covered by
// RequestManagementWorkflowStatusTest) and this one share the single choke
// point RequestManagementService::updateWork() -> QuoteWorkflowStatusWriter,
// so the resolved-set rule and the mandatory transition note hold on both.

uses(RefreshDatabase::class);

if (! function_exists('workflowColumnActor')) {
    /**
     * @param  array<int, string>  $abilities  request-management abilities, unprefixed
     */
    function workflowColumnActor(array $abilities = ['viewAny', 'view', 'update', 'viewAll'], bool $canCreateNotes = true): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }
        Permission::findOrCreate('notes.create');

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        if ($canCreateNotes) {
            $user->givePermissionTo('notes.create');
        }

        return $user;
    }
}

if (! function_exists('workflowColumnQuote')) {
    /**
     * A request with no offer lines and no matching active workflow: its
     * resolved set is deterministically the GLOBAL default one.
     */
    function workflowColumnQuote(User $supervisor): Quote
    {
        $opportunity = Opportunity::factory()->create();
        $opportunity->managers()->sync([$supervisor->id => ['position' => 2]]);

        return Quote::factory()->for($opportunity)->create(['supervisor_id' => $supervisor->id]);
    }
}

if (! function_exists('workflowColumnGlobalStatus')) {
    function workflowColumnGlobalStatus(bool $requiresNote = false, int $sortOrder = 99, ?string $name = null): QuoteWorkflowStatus
    {
        return QuoteWorkflowStatus::factory()->global()->create([
            'requires_note' => $requiresNote,
            'sort_order' => $sortOrder,
            ...($name === null ? [] : ['name' => $name]),
        ]);
    }
}

// ---------------------------------------------------------------------------
// GET /columns — the column contract
// ---------------------------------------------------------------------------

it('declares quote_workflow_status as an editable select carrying the requires_note options', function () {
    $actor = workflowColumnActor();
    $target = workflowColumnGlobalStatus(requiresNote: true, name: 'Da richiamare');
    Sanctum::actingAs($actor);

    $columns = collect($this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.columns'))
        ->keyBy('id');

    expect($columns)->toHaveKey('quote_workflow_status');

    $column = $columns['quote_workflow_status'];

    expect($column['editable'])->toBeTrue()
        ->and($column['editor'])->toBe('select')
        ->and($column['sortable'])->toBeTrue()
        ->and($column['filterable'])->toBeTrue()
        ->and($column['filterType'])->toBe('set');

    $option = collect($column['options'])->firstWhere('value', $target->id);

    expect($option)->not->toBeNull()
        ->and($option['label'])->toBe('Da richiamare')
        ->and($option['requires_note'])->toBeTrue();
});

it('leaves quote_workflow_status read-only for an actor without request-management.update', function () {
    Sanctum::actingAs(workflowColumnActor(['viewAny', 'view', 'viewAll']));

    $columns = collect($this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.columns'))
        ->keyBy('id');

    expect($columns['quote_workflow_status']['editable'])->toBeFalse();
});

// ---------------------------------------------------------------------------
// POST /rows — the row projection
// ---------------------------------------------------------------------------

it('projects the current status with its color and the ids THIS offer may move to', function () {
    $actor = workflowColumnActor();
    $quote = workflowColumnQuote($actor);
    $extra = workflowColumnGlobalStatus();
    Sanctum::actingAs($actor);

    $row = collect($this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items'))
        ->firstWhere('id', $quote->id);

    $current = $quote->fresh()->quoteWorkflowStatus;

    expect($row['quote_workflow_status'])->toMatchArray([
        'id' => $current->id,
        'name' => $current->name,
        'color' => $current->color,
    ])
        ->and($row['quote_workflow_status_options'])->toContain($current->id)
        ->and($row['quote_workflow_status_options'])->toContain($extra->id);
});

// ---------------------------------------------------------------------------
// PATCH /rows/{row} — the inline write path
// ---------------------------------------------------------------------------

it('advances the working status in-cell', function () {
    $actor = workflowColumnActor();
    $quote = workflowColumnQuote($actor);
    $target = workflowColumnGlobalStatus();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'quote_workflow_status',
        'value' => $target->id,
    ])->assertOk()
        ->assertJsonPath('data.quote_workflow_status.id', $target->id);

    expect($quote->fresh()->quote_workflow_status_id)->toBe($target->id);
});

it('refuses a requires_note destination submitted with no note, leaving the status untouched', function () {
    $actor = workflowColumnActor();
    $quote = workflowColumnQuote($actor);
    $previousStatusId = $quote->quote_workflow_status_id;
    $target = workflowColumnGlobalStatus(requiresNote: true);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'quote_workflow_status',
        'value' => $target->id,
    ])->assertStatus(422)->assertJsonValidationErrors('note');

    expect($quote->fresh()->quote_workflow_status_id)->toBe($previousStatusId);
});

it('accepts a requires_note destination with a note and files it on the offer-scoped thread', function () {
    $actor = workflowColumnActor();
    $quote = workflowColumnQuote($actor);
    $target = workflowColumnGlobalStatus(requiresNote: true);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'quote_workflow_status',
        'value' => $target->id,
        'note' => 'Cliente da richiamare la prossima settimana',
    ])->assertOk();

    expect($quote->fresh()->quote_workflow_status_id)->toBe($target->id);

    $note = Note::query()->where('quote_id', $quote->id)->first();

    expect($note)->not->toBeNull()
        ->and($note->body)->toBe('Cliente da richiamare la prossima settimana')
        ->and($note->notable_id)->toBe($quote->opportunity_id);
});

it('refuses a status outside the set resolved for this offer', function () {
    $actor = workflowColumnActor();
    $quote = workflowColumnQuote($actor);
    $previousStatusId = $quote->quote_workflow_status_id;
    $foreign = QuoteWorkflowStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'quote_workflow_status',
        'value' => $foreign->id,
    ])->assertStatus(422);

    expect($quote->fresh()->quote_workflow_status_id)->toBe($previousStatusId);
});

it('refuses the inline write for an actor without request-management.update', function () {
    $actor = workflowColumnActor(['viewAny', 'view', 'viewAll']);
    $quote = workflowColumnQuote($actor);
    $target = workflowColumnGlobalStatus();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'quote_workflow_status',
        'value' => $target->id,
    ])->assertForbidden();

    expect($quote->fresh()->quote_workflow_status_id)->not->toBe($target->id);
});

// ---------------------------------------------------------------------------
// Sort / filter / distinct values — the derived-column hooks
// ---------------------------------------------------------------------------

it('sorts the grid by the related status name', function () {
    $actor = workflowColumnActor();
    $first = workflowColumnQuote($actor);
    $second = workflowColumnQuote($actor);
    $first->forceFill(['quote_workflow_status_id' => workflowColumnGlobalStatus(name: 'Zeta')->id])->save();
    $second->forceFill(['quote_workflow_status_id' => workflowColumnGlobalStatus(name: 'Alfa')->id])->save();
    Sanctum::actingAs($actor);

    $ids = collect($this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'quote_workflow_status', 'sort' => 'asc']],
    ])->assertOk()->json('items'))->pluck('id')->all();

    expect(array_slice($ids, 0, 2))->toBe([$second->id, $first->id]);
});

it('set-filters the grid by the related status name', function () {
    $actor = workflowColumnActor();
    $kept = workflowColumnQuote($actor);
    $dropped = workflowColumnQuote($actor);
    $kept->forceFill(['quote_workflow_status_id' => workflowColumnGlobalStatus(name: 'In lavorazione')->id])->save();
    Sanctum::actingAs($actor);

    $ids = collect($this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => [
            'quote_workflow_status' => ['filterType' => 'set', 'values' => ['In lavorazione']],
        ],
    ])->assertOk()->json('items'))->pluck('id')->all();

    expect($ids)->toBe([$kept->id])
        ->and($ids)->not->toContain($dropped->id);
});

it('serves the distinct status names of the rows in scope', function () {
    $actor = workflowColumnActor();
    $quote = workflowColumnQuote($actor);
    $quote->forceFill(['quote_workflow_status_id' => workflowColumnGlobalStatus(name: 'Da qualificare')->id])->save();
    Sanctum::actingAs($actor);

    $values = $this->postJson('/api/tables/request-management/values', [
        'columnId' => 'quote_workflow_status',
    ])->assertOk()->json('data.values');

    expect($values)->toContain('Da qualificare');
});
