<?php

use App\Models\OpportunityWorkflowStatus;
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
// AC-022 — GET /api/opportunity-workflows/criterion-fields
// ---------------------------------------------------------------------------

it('criterion-fields: 200 with the 4 allow-listed fields and correct for_select_resource (AC-022)', function () {
    $actor = opportunityWorkflowUserWith(['view']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/opportunity-workflows/criterion-fields')->assertOk()->json('data');

    expect($data)->toHaveCount(4);

    $byField = collect($data)->keyBy('field');
    expect($byField['state_id']['for_select_resource'])->toBe('states')
        ->and($byField['state_id']['source'])->toBe('native')
        ->and($byField['source_id']['for_select_resource'])->toBe('sources')
        ->and($byField['business_function_id']['for_select_resource'])->toBe('business-functions')
        ->and($byField['business_function_id']['multi_valued'])->toBeTrue()
        ->and($byField['product_category_id']['for_select_resource'])->toBe('product-categories');

    foreach ($data as $field) {
        expect($field['source'])->toBe('native');
    }
});

// ---------------------------------------------------------------------------
// default-statuses — GET/PUT (AC-005/AC-010, happy + system-row guard)
// ---------------------------------------------------------------------------

it('default-statuses: GET 200 always exposes the 4 global system rows, ordered (AC-005)', function () {
    $actor = opportunityWorkflowUserWith(['view']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/opportunity-workflows/default-statuses')->assertOk()->json('data');

    expect(collect($data)->pluck('system_key')->all())->toBe(['open', 'validated', 'closed_won', 'closed_lost']);
});

it('default-statuses: PUT syncs custom rows, pinning open first / validated + closed_won + closed_lost last', function () {
    $actor = opportunityWorkflowUserWith(['view', 'update']);
    Sanctum::actingAs($actor);

    $response = $this->putJson('/api/opportunity-workflows/default-statuses', [
        'statuses' => [
            ['name' => 'In corso', 'color' => 'blue', 'group' => 'open'],
        ],
    ])->assertOk();

    $data = collect($response->json('data'));

    expect($data)->toHaveCount(5)
        ->and($data->first()['system_key'])->toBe('open')
        ->and($data->last()['system_key'])->toBe('closed_lost')
        ->and($data->firstWhere('name', 'In corso'))->not->toBeNull();

    $this->assertDatabaseHas('opportunity_workflow_statuses', [
        'opportunity_workflow_id' => null,
        'name' => 'In corso',
        'color' => 'blue',
    ]);
});

it('default-statuses: PUT 422 when attempting to change a global system row\'s group', function () {
    $actor = opportunityWorkflowUserWith(['view', 'update']);
    Sanctum::actingAs($actor);

    $globalOpen = OpportunityWorkflowStatus::whereNull('opportunity_workflow_id')->where('system_key', 'open')->sole();

    $this->putJson('/api/opportunity-workflows/default-statuses', [
        'statuses' => [
            ['id' => $globalOpen->id, 'name' => 'Aperta', 'group' => 'closed_won'],
        ],
    ])->assertStatus(422);

    $this->assertDatabaseHas('opportunity_workflow_statuses', ['id' => $globalOpen->id, 'group' => 'open']);
});

it('default-statuses: PUT 422 when statuses is missing', function () {
    $actor = opportunityWorkflowUserWith(['update']);
    Sanctum::actingAs($actor);

    $this->putJson('/api/opportunity-workflows/default-statuses', [])
        ->assertStatus(422)->assertJsonValidationErrors('statuses');
});
