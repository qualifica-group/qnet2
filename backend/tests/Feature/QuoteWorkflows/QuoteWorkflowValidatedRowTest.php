<?php

use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * The OPTIONAL 'validated' system row (user directive 2026-08-03): no set is
 * created with one, none is defaulted, and a row carries it only while the
 * client explicitly marks it — App\Services\QuoteWorkflows\
 * ValidatedStatusMarker.
 */
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

/**
 * A workflow with two custom rows and no validated one.
 *
 * @return array<string, mixed>
 */
function workflowWithoutValidatedRow(string $name): array
{
    $source = Source::factory()->create();

    return test()->postJson('/api/quote-workflows', [
        'name' => $name,
        'criteria' => [['field' => 'source_id', 'value_id' => $source->id]],
        'statuses' => [
            ['name' => 'Step A', 'group' => 'open'],
            ['name' => 'Step B', 'group' => 'pending'],
        ],
    ])->assertCreated()->json('data');
}

it('update: marking a custom row makes it the validated system row, ordered before closed_won', function () {
    Sanctum::actingAs(quoteWorkflowUserWith(['create', 'update']));

    $created = workflowWithoutValidatedRow('Promote Me');
    $stepA = collect($created['statuses'])->firstWhere('name', 'Step A');
    $stepB = collect($created['statuses'])->firstWhere('name', 'Step B');

    $response = $this->patchJson("/api/quote-workflows/{$created['id']}", [
        'statuses' => [
            ['id' => $stepA['id'], 'name' => 'Step A', 'group' => 'open', 'system_key' => null],
            ['id' => $stepB['id'], 'name' => 'Step B', 'group' => 'validated', 'system_key' => 'validated'],
        ],
    ])->assertOk();

    $statuses = collect($response->json('data.statuses'))->sortBy('sort_order')->values();
    $validated = $statuses->firstWhere('system_key', 'validated');

    expect($statuses->pluck('system_key')->all())->toBe(['open', null, 'validated', 'closed_won', 'closed_lost'])
        ->and($validated['name'])->toBe('Step B')
        ->and($validated['group'])->toBe('validated');
});

it('update: dropping the mark demotes the validated row back to a custom one', function () {
    Sanctum::actingAs(quoteWorkflowUserWith(['create', 'update']));

    $created = workflowWithoutValidatedRow('Demote Me');
    $stepA = collect($created['statuses'])->firstWhere('name', 'Step A');
    $stepB = collect($created['statuses'])->firstWhere('name', 'Step B');

    $this->patchJson("/api/quote-workflows/{$created['id']}", [
        'statuses' => [
            ['id' => $stepA['id'], 'name' => 'Step A', 'group' => 'open', 'system_key' => null],
            ['id' => $stepB['id'], 'name' => 'Step B', 'group' => 'validated', 'system_key' => 'validated'],
        ],
    ])->assertOk();

    $response = $this->patchJson("/api/quote-workflows/{$created['id']}", [
        'statuses' => [
            ['id' => $stepA['id'], 'name' => 'Step A', 'group' => 'open', 'system_key' => null],
            ['id' => $stepB['id'], 'name' => 'Step B', 'group' => 'pending', 'system_key' => null],
        ],
    ])->assertOk();

    $statuses = collect($response->json('data.statuses'))->sortBy('sort_order')->values();

    expect($statuses->pluck('system_key')->all())->toBe(['open', null, null, 'closed_won', 'closed_lost'])
        ->and($statuses->firstWhere('name', 'Step B')['group'])->toBe('pending');
});

it('update: a payload that never mentions system_key leaves the validated row alone', function () {
    Sanctum::actingAs(quoteWorkflowUserWith(['create', 'update']));

    $created = workflowWithoutValidatedRow('Legacy Client');
    $stepA = collect($created['statuses'])->firstWhere('name', 'Step A');
    $stepB = collect($created['statuses'])->firstWhere('name', 'Step B');

    $this->patchJson("/api/quote-workflows/{$created['id']}", [
        'statuses' => [
            ['id' => $stepA['id'], 'name' => 'Step A', 'group' => 'open'],
            ['id' => $stepB['id'], 'name' => 'Step B', 'group' => 'validated', 'system_key' => 'validated'],
        ],
    ])->assertOk();

    // No `system_key` anywhere: the mark is NOT removed (pre-existing clients
    // and seeders must never demote it by omission).
    $response = $this->patchJson("/api/quote-workflows/{$created['id']}", [
        'statuses' => [
            ['id' => $stepA['id'], 'name' => 'Step A', 'group' => 'open'],
            ['id' => $stepB['id'], 'name' => 'Step B', 'group' => 'validated'],
        ],
    ])->assertOk();

    expect(collect($response->json('data.statuses'))->firstWhere('system_key', 'validated')['name'])->toBe('Step B');
});

it('update: 422 when two rows claim the validated mark', function () {
    Sanctum::actingAs(quoteWorkflowUserWith(['create', 'update']));

    $created = workflowWithoutValidatedRow('Two Marks');
    $stepA = collect($created['statuses'])->firstWhere('name', 'Step A');
    $stepB = collect($created['statuses'])->firstWhere('name', 'Step B');

    $this->patchJson("/api/quote-workflows/{$created['id']}", [
        'statuses' => [
            ['id' => $stepA['id'], 'name' => 'Step A', 'group' => 'validated', 'system_key' => 'validated'],
            ['id' => $stepB['id'], 'name' => 'Step B', 'group' => 'validated', 'system_key' => 'validated'],
        ],
    ])->assertStatus(422);
});

it('update: 422 when a mandatory system row is claimed as the validated one', function () {
    Sanctum::actingAs(quoteWorkflowUserWith(['create', 'update']));

    $created = workflowWithoutValidatedRow('Hands Off');
    $closedWon = collect($created['statuses'])->firstWhere('system_key', 'closed_won');

    $this->patchJson("/api/quote-workflows/{$created['id']}", [
        'statuses' => [
            ['id' => $closedWon['id'], 'name' => $closedWon['name'], 'group' => 'validated', 'system_key' => 'validated'],
        ],
    ])->assertStatus(422);
});

it('default statuses: the global set moves the mark onto another row', function () {
    Sanctum::actingAs(quoteWorkflowUserWith(['viewAny', 'view', 'update']));

    $rows = collect($this->getJson('/api/quote-workflows/default-statuses')->assertOk()->json('data'));

    // Every row resubmitted, the persisted validated one explicitly unmarked
    // and a brand-new row claiming the mark in its place.
    $payload = $rows
        ->map(fn (array $row): array => [
            'id' => $row['id'],
            'name' => $row['name'],
            'group' => $row['system_key'] === 'validated' ? 'pending' : $row['group'],
            'system_key' => $row['system_key'] === 'validated' ? null : $row['system_key'],
        ])
        ->values()
        ->push(['name' => 'Da caricare', 'group' => 'validated', 'system_key' => 'validated'])
        ->all();

    $data = collect($this->putJson('/api/quote-workflows/default-statuses', ['statuses' => $payload])->assertOk()->json('data'));

    expect($data->where('system_key', 'validated'))->toHaveCount(1)
        ->and($data->firstWhere('system_key', 'validated')['name'])->toBe('Da caricare')
        ->and($data->firstWhere('name', 'Validato')['system_key'])->toBeNull();
});
