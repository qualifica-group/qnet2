<?php

use App\Models\QuoteWorkflowStatus;
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
// AC-016 — GET /api/quote-workflows/criterion-fields
// ---------------------------------------------------------------------------

it('criterion-fields: 200 with the 4 allow-listed fields, correct for_select_resource and inherited flag (AC-016)', function () {
    $actor = quoteWorkflowUserWith(['view']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/quote-workflows/criterion-fields')->assertOk()->json('data');

    // 4, not 3, since spec 0092 added `product_category_branch_id`.
    expect($data)->toHaveCount(4);

    $byField = collect($data)->keyBy('field');
    expect($byField['source_id']['for_select_resource'])->toBe('sources')
        ->and($byField['source_id']['source'])->toBe('native')
        ->and($byField['source_id']['inherited'])->toBeTrue()
        ->and($byField['business_function_id']['for_select_resource'])->toBe('business-functions')
        ->and($byField['business_function_id']['multi_valued'])->toBeTrue()
        ->and($byField['business_function_id']['inherited'])->toBeFalse()
        ->and($byField['product_category_id']['for_select_resource'])->toBe('product-categories')
        ->and($byField['product_category_id']['inherited'])->toBeFalse()
        ->and($byField['product_category_branch_id']['for_select_resource'])->toBe('product-category-branches')
        ->and($byField['product_category_branch_id']['inherited'])->toBeFalse();

    foreach ($data as $field) {
        expect($field['source'])->toBe('native');
    }
});

// ---------------------------------------------------------------------------
// default-statuses — GET/PUT (AC-005/AC-010, happy + system-row guard)
// ---------------------------------------------------------------------------

it('default-statuses: GET 200 exposes the global set ordered, only open/closed_won/closed_lost pinned (AC-005)', function () {
    $actor = quoteWorkflowUserWith(['view']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/quote-workflows/default-statuses')->assertOk()->json('data');

    expect(collect($data)->pluck('system_key')->all())->toBe(['open', null, 'closed_won', 'closed_lost'])
        ->and(collect($data)->pluck('group')->all())->toBe(['open', 'validated', 'closed_won', 'closed_lost']);
});

it('default-statuses: PUT syncs custom rows (the validated one included), pinning open first / closed_won + closed_lost last', function () {
    $actor = quoteWorkflowUserWith(['view', 'update']);
    Sanctum::actingAs($actor);

    // Authoritative sync: the seeded "Validato" row is now a plain custom one,
    // so leaving it out of the payload deletes it like any other custom row.
    $response = $this->putJson('/api/quote-workflows/default-statuses', [
        'statuses' => [
            ['name' => 'In corso', 'color' => 'blue', 'group' => 'open'],
        ],
    ])->assertOk();

    $data = collect($response->json('data'));

    expect($data)->toHaveCount(4)
        ->and($data->first()['system_key'])->toBe('open')
        ->and($data->last()['system_key'])->toBe('closed_lost')
        ->and($data->firstWhere('name', 'Validato'))->toBeNull()
        ->and($data->firstWhere('name', 'In corso'))->not->toBeNull();

    $this->assertDatabaseHas('quote_workflow_statuses', [
        'quote_workflow_id' => null,
        'name' => 'In corso',
        'color' => 'blue',
    ]);
});

it('default-statuses: PUT 422 when attempting to change a global system row\'s group', function () {
    $actor = quoteWorkflowUserWith(['view', 'update']);
    Sanctum::actingAs($actor);

    $globalOpen = QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'open')->sole();

    $this->putJson('/api/quote-workflows/default-statuses', [
        'statuses' => [
            ['id' => $globalOpen->id, 'name' => 'Aperta', 'group' => 'closed_won'],
        ],
    ])->assertStatus(422);

    $this->assertDatabaseHas('quote_workflow_statuses', ['id' => $globalOpen->id, 'group' => 'open']);
});

it('default-statuses: PUT 422 when statuses is missing', function () {
    $actor = quoteWorkflowUserWith(['update']);
    Sanctum::actingAs($actor);

    $this->putJson('/api/quote-workflows/default-statuses', [])
        ->assertStatus(422)->assertJsonValidationErrors('statuses');
});
