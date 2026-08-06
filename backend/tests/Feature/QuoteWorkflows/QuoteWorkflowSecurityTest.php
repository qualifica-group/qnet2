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
// AC-020 — every endpoint applies server-side authz (403 without the
// matching permission, no write persisted)
// ---------------------------------------------------------------------------

it('GET show: 403 without quote-workflows.view (AC-020)', function () {
    $actor = quoteWorkflowUserWith([]);
    $target = QuoteWorkflow::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/quote-workflows/{$target->id}")->assertForbidden();
});

it('POST create: 403 without quote-workflows.create, no row created (AC-020)', function () {
    $actor = quoteWorkflowUserWith([]);
    $source = Source::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/quote-workflows', [
        'name' => 'Nope',
        'criteria' => [['field' => 'source_id', 'value_id' => $source->id]],
    ])->assertForbidden();

    expect(QuoteWorkflow::count())->toBe(0);
});

it('PATCH update: 403 without quote-workflows.update, no change persisted (AC-020)', function () {
    $actor = quoteWorkflowUserWith([]);
    $target = QuoteWorkflow::factory()->create(['name' => 'Untouched']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quote-workflows/{$target->id}", ['name' => 'Nope'])->assertForbidden();

    $this->assertDatabaseHas('quote_workflows', ['id' => $target->id, 'name' => 'Untouched']);
});

it('DELETE destroy: 403 without quote-workflows.delete, record still exists (AC-020)', function () {
    $actor = quoteWorkflowUserWith([]);
    $target = QuoteWorkflow::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/quote-workflows/{$target->id}")->assertForbidden();

    $this->assertDatabaseHas('quote_workflows', ['id' => $target->id]);
});

it('GET criterion-fields: 403 without quote-workflows.view (AC-020)', function () {
    $actor = quoteWorkflowUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/quote-workflows/criterion-fields')->assertForbidden();
});

it('GET default-statuses: 403 without quote-workflows.view (AC-020)', function () {
    $actor = quoteWorkflowUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/quote-workflows/default-statuses')->assertForbidden();
});

it('PUT default-statuses: 403 without quote-workflows.update (AC-020)', function () {
    $actor = quoteWorkflowUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->putJson('/api/quote-workflows/default-statuses', [
        'statuses' => [['name' => 'X', 'group' => 'open']],
    ])->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-021 — create/update/delete are registered in the Activity Log
// ---------------------------------------------------------------------------

it('create is recorded in the activity log (AC-021)', function () {
    $actor = quoteWorkflowUserWith(['create']);
    $source = Source::factory()->create();
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/quote-workflows', [
        'name' => 'Logged Create',
        'criteria' => [['field' => 'source_id', 'value_id' => $source->id]],
    ])->assertCreated()->json('data');

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'quote_workflows',
        'subject_type' => 'quote_workflow',
        'subject_id' => $created['id'],
        'event' => 'created',
        'causer_id' => $actor->id,
    ]);
});

it('update is recorded in the activity log (AC-021)', function () {
    $actor = quoteWorkflowUserWith(['create', 'update']);
    $source = Source::factory()->create();
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/quote-workflows', [
        'name' => 'Logged Update',
        'criteria' => [['field' => 'source_id', 'value_id' => $source->id]],
    ])->assertCreated()->json('data');

    $this->patchJson("/api/quote-workflows/{$created['id']}", ['name' => 'Logged Update Renamed'])->assertOk();

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'quote_workflows',
        'subject_type' => 'quote_workflow',
        'subject_id' => $created['id'],
        'event' => 'updated',
        'causer_id' => $actor->id,
    ]);
});

it('delete is recorded in the activity log (AC-021)', function () {
    $actor = quoteWorkflowUserWith(['create', 'delete']);
    $source = Source::factory()->create();
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/quote-workflows', [
        'name' => 'Logged Delete',
        'criteria' => [['field' => 'source_id', 'value_id' => $source->id]],
    ])->assertCreated()->json('data');

    $this->deleteJson("/api/quote-workflows/{$created['id']}")->assertNoContent();

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'quote_workflows',
        'subject_type' => 'quote_workflow',
        'subject_id' => $created['id'],
        'event' => 'deleted',
        'causer_id' => $actor->id,
    ]);
});
