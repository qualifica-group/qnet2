<?php

use App\Models\OpportunityWorkflow;
use App\Models\Source;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('opportunityWorkflowUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function opportunityWorkflowUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("opportunity-workflows.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("opportunity-workflows.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// create — POST /api/opportunity-workflows (AC-004)
// ---------------------------------------------------------------------------

it('create: 201, persists, and auto-creates exactly the 4 system rows open/validated/closed_won/closed_lost (AC-004)', function () {
    $actor = opportunityWorkflowUserWith(['create']);
    $source = Source::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/opportunity-workflows', [
        'name' => 'Regione Nord',
        'is_active' => true,
        'criteria' => [
            ['field' => 'source_id', 'value_id' => $source->id],
        ],
        'statuses' => [
            ['name' => 'In lavorazione', 'color' => 'blue', 'group' => 'open'],
        ],
    ])->assertCreated();

    $response->assertJsonPath('data.name', 'Regione Nord')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonCount(1, 'data.criteria')
        ->assertJsonPath('data.criteria.0.field', 'source_id')
        ->assertJsonPath('data.criteria.0.value_id', $source->id)
        ->assertJsonCount(5, 'data.statuses');

    $workflow = OpportunityWorkflow::where('name', 'Regione Nord')->sole();

    expect($workflow->statuses()->count())->toBe(5)
        ->and($workflow->statuses()->where('system_key', 'open')->sole()->sort_order)->toBe(0)
        ->and($workflow->statuses()->where('system_key', 'validated')->sole()->sort_order)->toBeGreaterThan(0)
        ->and($workflow->statuses()->where('system_key', 'closed_won')->sole()->sort_order)->toBeGreaterThan(0)
        ->and($workflow->statuses()->where('system_key', 'closed_lost')->sole()->sort_order)->toBeGreaterThan(0)
        ->and($workflow->statuses()->whereNull('system_key')->sole()->name)->toBe('In lavorazione')
        ->and($workflow->criteria_signature)->toBe("source_id:{$source->id}");
});

it('create: 201 with statuses omitted still creates the 4 system rows only', function () {
    $actor = opportunityWorkflowUserWith(['create']);
    $source = Source::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunity-workflows', [
        'name' => 'No customs',
        'criteria' => [['field' => 'source_id', 'value_id' => $source->id]],
    ])->assertCreated()->assertJsonCount(4, 'data.statuses');

    $workflow = OpportunityWorkflow::where('name', 'No customs')->sole();
    expect($workflow->statuses()->count())->toBe(4)
        ->and($workflow->statuses()->pluck('system_key')->sort()->values()->all())->toBe(['closed_lost', 'closed_won', 'open', 'validated']);
});

it('create: seeds the pinned rows with the names/colors the client tagged with system_key (AC-004)', function () {
    $actor = opportunityWorkflowUserWith(['create']);
    $source = Source::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunity-workflows', [
        'name' => 'Named systems',
        'criteria' => [['field' => 'source_id', 'value_id' => $source->id]],
        'statuses' => [
            ['name' => 'Aperto (in corso)', 'color' => 'green', 'group' => 'open', 'system_key' => 'open'],
            ['name' => 'Custom', 'color' => 'blue', 'group' => 'pending', 'system_key' => null],
            ['name' => 'Chiuso vinto', 'color' => 'green', 'group' => 'closed_won', 'system_key' => 'closed_won'],
            ['name' => 'Chiuso perso', 'color' => 'red', 'group' => 'closed_lost', 'system_key' => 'closed_lost'],
        ],
    ])->assertCreated()->assertJsonCount(5, 'data.statuses');

    $workflow = OpportunityWorkflow::where('name', 'Named systems')->sole();

    $open = $workflow->statuses()->where('system_key', 'open')->sole();
    $closedWon = $workflow->statuses()->where('system_key', 'closed_won')->sole();
    $closedLost = $workflow->statuses()->where('system_key', 'closed_lost')->sole();

    expect($open->name)->toBe('Aperto (in corso)')
        ->and($open->color)->toBe('green')
        ->and($open->sort_order)->toBe(0)
        ->and($closedWon->name)->toBe('Chiuso vinto')
        ->and($closedWon->color)->toBe('green')
        ->and($closedWon->sort_order)->toBeGreaterThan(0)
        ->and($closedLost->name)->toBe('Chiuso perso')
        ->and($closedLost->color)->toBe('red')
        ->and($closedLost->sort_order)->toBeGreaterThan($closedWon->sort_order)
        ->and($workflow->statuses()->whereNull('system_key')->sole()->name)->toBe('Custom');
});

// ---------------------------------------------------------------------------
// create — AC-008 (criteria validation)
// ---------------------------------------------------------------------------

it('create: 422 when criteria is empty/missing (AC-008)', function () {
    $actor = opportunityWorkflowUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunity-workflows', ['name' => 'X', 'criteria' => []])
        ->assertStatus(422)->assertJsonValidationErrors('criteria');

    $this->postJson('/api/opportunity-workflows', ['name' => 'Y'])
        ->assertStatus(422)->assertJsonValidationErrors('criteria');
});

it('create: 422 when a criterion field is not in the allow-list (AC-008)', function () {
    $actor = opportunityWorkflowUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunity-workflows', [
        'name' => 'X',
        'criteria' => [['field' => 'operational_site_id', 'value_id' => 1]],
    ])->assertStatus(422)->assertJsonValidationErrors('criteria.0.field');
});

it('create: 422 when a criterion value_id does not exist for that field (AC-008)', function () {
    $actor = opportunityWorkflowUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunity-workflows', [
        'name' => 'X',
        'criteria' => [['field' => 'source_id', 'value_id' => 999999]],
    ])->assertStatus(422)->assertJsonValidationErrors('criteria.0.value_id');
});

it('create: 422 when two criteria share the same field (AC-008)', function () {
    $actor = opportunityWorkflowUserWith(['create']);
    $sourceA = Source::factory()->create();
    $sourceB = Source::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunity-workflows', [
        'name' => 'X',
        'criteria' => [
            ['field' => 'source_id', 'value_id' => $sourceA->id],
            ['field' => 'source_id', 'value_id' => $sourceB->id],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('criteria.1.field');
});

// ---------------------------------------------------------------------------
// create — AC-009 (criteria_signature uniqueness, order-independent)
// ---------------------------------------------------------------------------

it('create: 422 when another workflow already has the exact same criteria combination (AC-009)', function () {
    $actor = opportunityWorkflowUserWith(['create']);
    $source = Source::factory()->create();
    $state = State::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunity-workflows', [
        'name' => 'First',
        'criteria' => [
            ['field' => 'source_id', 'value_id' => $source->id],
            ['field' => 'state_id', 'value_id' => $state->id],
        ],
    ])->assertCreated();

    // Same pair, SUBMITTED IN THE OPPOSITE ORDER: still a duplicate (AC-009).
    $this->postJson('/api/opportunity-workflows', [
        'name' => 'Second',
        'criteria' => [
            ['field' => 'state_id', 'value_id' => $state->id],
            ['field' => 'source_id', 'value_id' => $source->id],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('criteria');

    expect(OpportunityWorkflow::count())->toBe(1);
});

it('create: 422 when name duplicates an existing workflow', function () {
    $actor = opportunityWorkflowUserWith(['create']);
    $sourceA = Source::factory()->create();
    $sourceB = Source::factory()->create();
    OpportunityWorkflow::factory()->create(['name' => 'Taken']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunity-workflows', [
        'name' => 'Taken',
        'criteria' => [['field' => 'source_id', 'value_id' => $sourceA->id]],
    ])->assertStatus(422)->assertJsonValidationErrors('name');
});

// ---------------------------------------------------------------------------
// show — GET /api/opportunity-workflows/{opportunityWorkflow}
// ---------------------------------------------------------------------------

it('show: 200 with the full data shape including resolved value_label', function () {
    $actor = opportunityWorkflowUserWith(['view', 'create']);
    $source = Source::factory()->create(['name' => 'Referral']);
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/opportunity-workflows', [
        'name' => 'Show Me',
        'criteria' => [['field' => 'source_id', 'value_id' => $source->id]],
    ])->assertCreated()->json('data');

    $this->getJson("/api/opportunity-workflows/{$created['id']}")
        ->assertOk()
        ->assertJsonPath('data.id', $created['id'])
        ->assertJsonPath('data.name', 'Show Me')
        ->assertJsonPath('data.criteria.0.value_label', 'Referral')
        ->assertJsonStructure(['permissions']);
});

it('show: 404 for a non-existent workflow', function () {
    $actor = opportunityWorkflowUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/opportunity-workflows/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// update — PUT/PATCH /api/opportunity-workflows/{opportunityWorkflow}
// ---------------------------------------------------------------------------

it('update: PATCH partial {name, is_active} leaves criteria/statuses untouched', function () {
    $actor = opportunityWorkflowUserWith(['create', 'update']);
    $source = Source::factory()->create();
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/opportunity-workflows', [
        'name' => 'Before',
        'criteria' => [['field' => 'source_id', 'value_id' => $source->id]],
        'statuses' => [['name' => 'Custom', 'group' => 'open']],
    ])->assertCreated()->json('data');

    $this->patchJson("/api/opportunity-workflows/{$created['id']}", ['name' => 'After', 'is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.name', 'After')
        ->assertJsonPath('data.is_active', false)
        ->assertJsonCount(1, 'data.criteria')
        ->assertJsonCount(5, 'data.statuses');
});

it('update: submitting criteria re-syncs and recomputes criteria_signature, revalidates uniqueness excluding self', function () {
    $actor = opportunityWorkflowUserWith(['create', 'update']);
    $sourceA = Source::factory()->create();
    $sourceB = Source::factory()->create();
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/opportunity-workflows', [
        'name' => 'Editable',
        'criteria' => [['field' => 'source_id', 'value_id' => $sourceA->id]],
    ])->assertCreated()->json('data');

    // Re-submitting the SAME criteria on itself must NOT 422 (self-exclusion).
    $this->patchJson("/api/opportunity-workflows/{$created['id']}", [
        'criteria' => [['field' => 'source_id', 'value_id' => $sourceA->id]],
    ])->assertOk();

    $this->patchJson("/api/opportunity-workflows/{$created['id']}", [
        'criteria' => [['field' => 'source_id', 'value_id' => $sourceB->id]],
    ])->assertOk()->assertJsonPath('data.criteria.0.value_id', $sourceB->id);

    expect(OpportunityWorkflow::find($created['id'])->criteria_signature)->toBe("source_id:{$sourceB->id}");
});

it('update: 422 when criteria is resynced to a combination another workflow already owns', function () {
    $actor = opportunityWorkflowUserWith(['create', 'update']);
    $sourceA = Source::factory()->create();
    $sourceB = Source::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunity-workflows', [
        'name' => 'Owner',
        'criteria' => [['field' => 'source_id', 'value_id' => $sourceA->id]],
    ])->assertCreated();

    $other = $this->postJson('/api/opportunity-workflows', [
        'name' => 'Other',
        'criteria' => [['field' => 'source_id', 'value_id' => $sourceB->id]],
    ])->assertCreated()->json('data');

    $this->patchJson("/api/opportunity-workflows/{$other['id']}", [
        'criteria' => [['field' => 'source_id', 'value_id' => $sourceA->id]],
    ])->assertStatus(422)->assertJsonValidationErrors('criteria');
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/opportunity-workflows/{opportunityWorkflow}
// ---------------------------------------------------------------------------

it('delete: 204, removes the workflow and cascades its criteria/statuses', function () {
    $actor = opportunityWorkflowUserWith(['create', 'delete']);
    $source = Source::factory()->create();
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/opportunity-workflows', [
        'name' => 'To Delete',
        'criteria' => [['field' => 'source_id', 'value_id' => $source->id]],
    ])->assertCreated()->json('data');

    $this->deleteJson("/api/opportunity-workflows/{$created['id']}")->assertNoContent();

    $this->assertDatabaseMissing('opportunity_workflows', ['id' => $created['id']]);
    $this->assertDatabaseMissing('opportunity_workflow_criteria', ['opportunity_workflow_id' => $created['id']]);
    $this->assertDatabaseMissing('opportunity_workflow_statuses', ['opportunity_workflow_id' => $created['id']]);
});
