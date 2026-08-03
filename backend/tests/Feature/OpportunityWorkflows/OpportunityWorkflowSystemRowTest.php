<?php

use App\Models\Source;
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
// AC-006 — a system row (open/closed_won/closed_lost) never changes group/
// system_key, never deletes
// ---------------------------------------------------------------------------

it('update: 422 when a system row\'s group is changed via statuses sync (AC-006)', function () {
    $actor = opportunityWorkflowUserWith(['create', 'update']);
    Sanctum::actingAs($actor);

    $source = Source::factory()->create();
    $created = $this->postJson('/api/opportunity-workflows', [
        'name' => 'Guarded',
        'criteria' => [['field' => 'source_id', 'value_id' => $source->id]],
    ])->assertCreated()->json('data');

    $openId = collect($created['statuses'])->firstWhere('system_key', 'open')['id'];

    $this->patchJson("/api/opportunity-workflows/{$created['id']}", [
        'statuses' => [
            ['id' => $openId, 'name' => 'Aperta', 'group' => 'closed_won'],
        ],
    ])->assertStatus(422);

    $this->assertDatabaseHas('opportunity_workflow_statuses', ['id' => $openId, 'group' => 'open']);
});

it('update: a system row\'s name/color update IS accepted (no group change)', function () {
    $actor = opportunityWorkflowUserWith(['create', 'update']);
    Sanctum::actingAs($actor);

    $source = Source::factory()->create();
    $created = $this->postJson('/api/opportunity-workflows', [
        'name' => 'Renameable',
        'criteria' => [['field' => 'source_id', 'value_id' => $source->id]],
    ])->assertCreated()->json('data');

    $openId = collect($created['statuses'])->firstWhere('system_key', 'open')['id'];

    $this->patchJson("/api/opportunity-workflows/{$created['id']}", [
        'statuses' => [
            ['id' => $openId, 'name' => 'Aperta rinominata', 'color' => 'green', 'group' => 'open'],
        ],
    ])->assertOk();

    $this->assertDatabaseHas('opportunity_workflow_statuses', [
        'id' => $openId,
        'name' => 'Aperta rinominata',
        'color' => 'green',
        'system_key' => 'open',
    ]);
});

it('update: omitting a system row from the statuses sync does NOT delete it (AC-006)', function () {
    $actor = opportunityWorkflowUserWith(['create', 'update']);
    Sanctum::actingAs($actor);

    $source = Source::factory()->create();
    $created = $this->postJson('/api/opportunity-workflows', [
        'name' => 'Never Deleted',
        'criteria' => [['field' => 'source_id', 'value_id' => $source->id]],
        'statuses' => [['name' => 'Custom', 'group' => 'open']],
    ])->assertCreated()->json('data');

    $openId = collect($created['statuses'])->firstWhere('system_key', 'open')['id'];
    $closedId = collect($created['statuses'])->firstWhere('system_key', 'closed_won')['id'];
    $customId = collect($created['statuses'])->firstWhere('name', 'Custom')['id'];

    // The sync payload omits BOTH system rows entirely — only the custom row
    // is resubmitted. Unlike a custom row (deleted when left out, AC-007),
    // a system row is structurally excluded from that diff
    // (WorkflowStatusWriter::partitionSubmitted/applyCustomSync) and must
    // survive untouched.
    $response = $this->patchJson("/api/opportunity-workflows/{$created['id']}", [
        'statuses' => [
            ['id' => $customId, 'name' => 'Custom', 'group' => 'open'],
        ],
    ])->assertOk();

    expect($response->json('data.statuses'))->toHaveCount(4);

    $this->assertDatabaseHas('opportunity_workflow_statuses', ['id' => $openId, 'system_key' => 'open']);
    $this->assertDatabaseHas('opportunity_workflow_statuses', ['id' => $closedId, 'system_key' => 'closed_won']);
});

// ---------------------------------------------------------------------------
// AC-007 — statuses sync resequences only customs, open first / closed_won +
// closed_lost last
// ---------------------------------------------------------------------------

it('update: statuses sync resequences customs, open stays first and closed_won/closed_lost stay last (AC-007)', function () {
    $actor = opportunityWorkflowUserWith(['create', 'update']);
    Sanctum::actingAs($actor);

    $source = Source::factory()->create();
    $created = $this->postJson('/api/opportunity-workflows', [
        'name' => 'Reorder Me',
        'criteria' => [['field' => 'source_id', 'value_id' => $source->id]],
        'statuses' => [
            ['name' => 'Step A', 'group' => 'open'],
            ['name' => 'Step B', 'group' => 'pending'],
        ],
    ])->assertCreated()->json('data');

    $stepA = collect($created['statuses'])->firstWhere('name', 'Step A');
    $stepB = collect($created['statuses'])->firstWhere('name', 'Step B');

    // Re-submit in REVERSED order + a brand-new custom row.
    $response = $this->patchJson("/api/opportunity-workflows/{$created['id']}", [
        'statuses' => [
            ['id' => $stepB['id'], 'name' => 'Step B', 'group' => 'pending'],
            ['id' => $stepA['id'], 'name' => 'Step A', 'group' => 'open'],
            ['name' => 'Step C', 'group' => 'closed_won'],
        ],
    ])->assertOk();

    $statuses = collect($response->json('data.statuses'))->sortBy('sort_order')->values();

    expect($statuses->first()['system_key'])->toBe('open')
        ->and($statuses->last()['system_key'])->toBe('closed_lost')
        ->and($statuses->pluck('name')->slice(1, 3)->values()->all())->toBe(['Step B', 'Step A', 'Step C']);
});

it('update: a custom row omitted from the statuses sync is deleted (AC-007)', function () {
    $actor = opportunityWorkflowUserWith(['create', 'update']);
    Sanctum::actingAs($actor);

    $source = Source::factory()->create();
    $created = $this->postJson('/api/opportunity-workflows', [
        'name' => 'Prune Me',
        'criteria' => [['field' => 'source_id', 'value_id' => $source->id]],
        'statuses' => [
            ['name' => 'Keep', 'group' => 'open'],
            ['name' => 'Drop', 'group' => 'pending'],
        ],
    ])->assertCreated()->json('data');

    $keep = collect($created['statuses'])->firstWhere('name', 'Keep');

    $this->patchJson("/api/opportunity-workflows/{$created['id']}", [
        'statuses' => [
            ['id' => $keep['id'], 'name' => 'Keep', 'group' => 'open'],
        ],
    ])->assertOk()->assertJsonCount(4, 'data.statuses');

    $this->assertDatabaseMissing('opportunity_workflow_statuses', ['name' => 'Drop']);
    $this->assertDatabaseHas('opportunity_workflow_statuses', ['id' => $keep['id']]);
});
