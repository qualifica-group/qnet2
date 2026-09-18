<?php

declare(strict_types=1);

use App\Enums\WorkflowStatusGroup;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// A status change made from the Offerte module is recorded on the parent
// Opportunity's activity trail with the SAME shape Gestione Richieste writes
// (user directive 2026-09-18): the report's transition indicators read that
// trail, so a change made here must count exactly like one made there.

uses(RefreshDatabase::class);

function quoteStatusActivityActor(): User
{
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("quotes.{$ability}");
    }

    $actor = User::factory()->create();
    $actor->givePermissionTo(['quotes.viewAny', 'quotes.view', 'quotes.update']);

    return $actor;
}

function quoteStatusEntries(Quote $quote): Collection
{
    return DB::table('activity_log')
        ->where('log_name', 'opportunities')
        ->where('subject_type', 'opportunity')
        ->where('subject_id', $quote->opportunity_id)
        ->whereNotNull('properties->attributes->quote_workflow_status_id')
        ->get();
}

it('records a status change made from the Offerte module on the opportunity trail', function () {
    $actor = quoteStatusActivityActor();
    $quote = Quote::factory()->create();
    $previousStatusId = $quote->quote_workflow_status_id;
    $pending = QuoteWorkflowStatus::factory()->global()->create(['system_key' => null, 'group' => WorkflowStatusGroup::Pending, 'requires_note' => false]);

    Sanctum::actingAs($actor);
    $this->patchJson("/api/quotes/{$quote->id}", ['quote_workflow_status_id' => $pending->id])->assertOk();

    $entries = quoteStatusEntries($quote);
    $properties = json_decode($entries->first()->properties, true);

    expect($entries)->toHaveCount(1)
        ->and($entries->first()->causer_id)->toBe($actor->id)
        ->and($properties['attributes']['quote_workflow_status_id'])->toBe($pending->id)
        ->and($properties['old']['quote_workflow_status_id'])->toBe($previousStatusId);
});

it('records nothing when the status does not change', function () {
    $actor = quoteStatusActivityActor();
    $quote = Quote::factory()->create();

    Sanctum::actingAs($actor);
    $this->patchJson("/api/quotes/{$quote->id}", ['title' => 'Nuovo titolo'])->assertOk();

    expect(quoteStatusEntries($quote))->toHaveCount(0);
});
