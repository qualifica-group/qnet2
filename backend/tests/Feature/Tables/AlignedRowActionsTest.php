<?php

use App\Models\Attachment;
use App\Models\Contract;
use App\Models\Note;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/*
 * Spec 0363: the row actions shared by Opportunita', Offerte, Contratti and
 * Commesse sit in the same order (view, documents, notes, specific, activity,
 * delete), each `documents`/`notes` gated like its detail tab and carrying
 * its badge count.
 */

uses(RefreshDatabase::class);

/**
 * @return array<int, string>
 */
function alignedActionKeys(string $domain): array
{
    return collect(test()->getJson("/api/tables/{$domain}/columns")->assertOk()->json('data.actions'))
        ->pluck('key')
        ->all();
}

/**
 * @return array<string, mixed>
 */
function alignedRow(string $domain, int $id): array
{
    $items = collect(test()->postJson("/api/tables/{$domain}/rows", ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));

    return $items->firstWhere('id', $id);
}

function attachDocuments(object $owner, int $count): void
{
    Attachment::factory()->for($owner, 'attachable')->count($count)->create(['collection' => 'documents']);
    // Another collection of the same owner never counts.
    Attachment::factory()->for($owner, 'attachable')->create(['collection' => 'other']);
}

it('AC-1: the four catalogs share the order of the common actions', function () {
    // The catalog hides the actions the actor lacks: a super-admin sees them all.
    Role::findOrCreate('super-admin', 'web');
    Sanctum::actingAs(tap(User::factory()->create())->assignRole('super-admin'));

    expect(alignedActionKeys('opportunities'))->toBe(['view', 'documents', 'notes', 'activity', 'delete'])
        ->and(alignedActionKeys('quotes'))->toBe(['view', 'documents', 'notes', 'generate_document', 'contract', 'activity', 'delete'])
        ->and(alignedActionKeys('contracts'))->toBe(['view', 'documents', 'validate', 'program', 'renew', 'change_status', 'terminate', 'reactivate', 'activity'])
        ->and(alignedActionKeys('work-orders'))->toBe(['view', 'documents', 'notes', 'proforma', 'progress', 'request_update', 'force_close', 'reopen', 'activity', 'delete']);
});

it('AC-2: a work order offers documents only with viewDocuments, notes with view, both counted', function () {
    Permission::findOrCreate('work-orders.viewDocuments');
    $reader = workOrderUserWith(['viewAny', 'view']);
    $documentsReader = workOrderUserWith(['viewAny', 'view']);
    $documentsReader->givePermissionTo('work-orders.viewDocuments');
    $workOrder = WorkOrder::factory()->create();
    attachDocuments($workOrder, 2);
    Note::factory()->for($workOrder, 'notable')->count(3)->create();

    Sanctum::actingAs($reader);
    $row = alignedRow('work-orders', $workOrder->id);
    expect($row['actions'])->toContain('notes')->not->toContain('documents')
        ->and($row['notes_count'])->toBe(3)
        ->and($row['documents_count'])->toBe(2);

    Sanctum::actingAs($documentsReader);
    expect(alignedRow('work-orders', $workOrder->id)['actions'])->toContain('documents', 'notes');
});

it('AC-2: a contract offers its own documents to whoever may view it, counted', function () {
    $actor = contractScopedUserWith(['viewAny', 'view', 'viewAll']);
    $contract = Contract::factory()->create();
    attachDocuments($contract, 2);

    Sanctum::actingAs($actor);
    $row = alignedRow('contracts', $contract->id);

    expect($row['actions'])->toContain('view', 'documents')->not->toContain('notes')
        ->and($row['documents_count'])->toBe(2);
});

it('AC-2: an offer counts its parent opportunity documents and offers them only when the opportunity is reachable', function () {
    Permission::findOrCreate('opportunities.view');
    Permission::findOrCreate('opportunities.viewAll');
    $quotesOnly = quoteTableUserWith(['viewAny', 'view']);
    $withOpportunities = quoteTableUserWith(['viewAny', 'view']);
    $withOpportunities->givePermissionTo('opportunities.view', 'opportunities.viewAll');
    $opportunity = Opportunity::factory()->create();
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    attachDocuments($opportunity, 2);

    Sanctum::actingAs($quotesOnly);
    $row = alignedRow('quotes', $quote->id);
    expect($row['actions'])->not->toContain('documents')
        ->and($row['documents_count'])->toBe(2);

    Sanctum::actingAs($withOpportunities);
    expect(alignedRow('quotes', $quote->id)['actions'])->toContain('documents');
});
