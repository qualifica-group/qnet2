<?php

use App\Models\QuoteWorkflow;
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
// AC-019 — GET /api/tables/quote-workflows/rows: 403 without viewAny;
// rows shaped with name/criteria_fields/criteria_values/statuses_count/
// is_active/updated_at/actions.
// ---------------------------------------------------------------------------

it('rows: 403 without quote-workflows.viewAny (AC-019)', function () {
    $actor = quoteWorkflowUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/quote-workflows/rows', ['startRow' => 0, 'endRow' => 25])->assertForbidden();
});

it('rows: 200 with the expected row shape when the actor has viewAny (AC-019)', function () {
    $actor = quoteWorkflowUserWith(['viewAny', 'view', 'update', 'delete', 'create']);
    Sanctum::actingAs($actor);

    $source = Source::factory()->create(['name' => 'Referral']);
    $created = $this->postJson('/api/quote-workflows', [
        'name' => 'Table Row Target',
        'is_active' => true,
        'criteria' => [['field' => 'source_id', 'value_id' => $source->id]],
        'statuses' => [['name' => 'Custom', 'group' => 'open']],
    ])->assertCreated()->json('data');

    $response = $this->postJson('/api/tables/quote-workflows/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $created['id']);

    expect($row)->not->toBeNull()
        ->and($row['name'])->toBe('Table Row Target')
        ->and($row['criteria_fields'])->toBe(['quoteWorkflows.criterionFields.source_id'])
        ->and($row['criteria_values'])->toBe(['Referral'])
        ->and($row['statuses_count'])->toBe(4)
        ->and($row['is_active'])->toBeTrue()
        ->and($row)->toHaveKey('updated_at')
        ->and($row['actions'])->toEqualCanonicalizing(['view', 'edit', 'delete']);
});

it('columns: 200 with the declared columns, 403 without viewAny', function () {
    $actor = quoteWorkflowUserWith([]);
    Sanctum::actingAs($actor);
    $this->getJson('/api/tables/quote-workflows/columns')->assertForbidden();

    $actor = quoteWorkflowUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/quote-workflows/columns')->assertOk()->json('data');

    expect($data['resource'])->toBe('quote-workflows')
        ->and($data['searchable'])->toBe(['name']);

    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe(['id', 'name', 'criteria_fields', 'criteria_values', 'statuses_count', 'is_active', 'updated_at']);
});

it('row actions respect the actor\'s permissions (edit hidden without update)', function () {
    $actor = quoteWorkflowUserWith(['viewAny', 'view']);
    QuoteWorkflow::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/quote-workflows/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->first();

    expect($row['actions'])->toContain('view')
        ->and($row['actions'])->not->toContain('edit')
        ->and($row['actions'])->not->toContain('delete');
});
