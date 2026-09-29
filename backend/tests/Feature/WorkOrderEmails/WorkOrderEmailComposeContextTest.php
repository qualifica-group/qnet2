<?php

use App\Models\Attachment;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/*
|--------------------------------------------------------------------------
| GET .../emails/compose-context (spec 0175, D-5/D-6/D-7/D-9, AC-009)
|--------------------------------------------------------------------------
*/

uses(RefreshDatabase::class);

function composeContextWorkOrder(): WorkOrder
{
    $registry = Registry::factory()->withPersonalData()->create();
    Contact::factory()->email()->for($registry->personalData, 'contactable')->create(['value' => 'client@example.com']);

    $referent = Referent::factory()->withPersonalData()->create();
    Contact::factory()->pec()->for($referent->personalData, 'contactable')->create(['value' => 'referent@pec.example.com']);
    $registry->referents()->attach($referent->id);

    $opportunity = Opportunity::factory()->create(['registry_id' => $registry->id, 'referent_id' => null]);
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);

    return WorkOrder::factory()->create(['quote_id' => $quote->id]);
}

it('AC-009: sender is the actor, suggestions include registry/referent contacts, supervisors and participants', function () {
    foreach (['registries.view', 'referents.view', 'work-orders.viewDocuments', 'registries.viewDocuments'] as $permission) {
        Permission::findOrCreate($permission);
    }

    $workOrder = composeContextWorkOrder();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $actor->forceFill(['name' => 'Mario Rossi'])->save();
    $actor->givePermissionTo(['registries.view', 'referents.view']);

    $supervisor = $workOrder->supervisors()->first();
    $participant = User::factory()->create();
    $workOrder->participants()->attach($participant->id, ['position' => 1]);

    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/work-orders/{$workOrder->id}/emails/compose-context")
        ->assertOk()
        ->assertJsonPath('data.sender.name', 'Mario Rossi')
        ->assertJsonPath('data.sender.email', $actor->email);

    $emails = collect($response->json('data.recipient_suggestions'))->pluck('email');

    expect($emails)->toContain('client@example.com')
        ->toContain('referent@pec.example.com')
        ->toContain($supervisor->email)
        ->toContain($participant->email);

    $sources = collect($response->json('data.recipient_suggestions'))->pluck('source', 'email');
    expect($sources['client@example.com'])->toBe('registry')
        ->and($sources['referent@pec.example.com'])->toBe('referent')
        ->and($sources[$supervisor->email])->toBe('supervisor')
        ->and($sources[$participant->email])->toBe('participant');
});

it('AC-009: registry/referent suggestions are absent without registries.view/referents.view', function () {
    $workOrder = composeContextWorkOrder();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/work-orders/{$workOrder->id}/emails/compose-context")->assertOk();

    $emails = collect($response->json('data.recipient_suggestions'))->pluck('email');
    expect($emails)->not->toContain('client@example.com')->not->toContain('referent@pec.example.com');
});

it('AC-009: documents lists only work_order/registry documents, gated by their own viewDocuments', function () {
    foreach (['work-orders.viewDocuments', 'registries.viewDocuments'] as $permission) {
        Permission::findOrCreate($permission);
    }

    $workOrder = composeContextWorkOrder();
    $registry = $workOrder->quote->opportunity->registry;
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $actor->givePermissionTo('work-orders.viewDocuments');

    $workOrderDoc = Attachment::factory()->for($workOrder, 'attachable')->create(['collection' => 'documents']);
    $registryDoc = Attachment::factory()->for($registry, 'attachable')->create(['collection' => 'documents']);
    // Not a "documents" collection: must never leak into compose-context.
    Attachment::factory()->for($workOrder, 'attachable')->create(['collection' => 'rich_text']);

    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/work-orders/{$workOrder->id}/emails/compose-context")->assertOk();
    $documents = collect($response->json('data.documents'));

    expect($documents->pluck('id'))->toContain($workOrderDoc->id)->not->toContain($registryDoc->id);
    expect($documents->firstWhere('id', $workOrderDoc->id)['source'])->toBe('work_order');
});

it('AC-009: 403 without work-orders.sendEmail (not viewEmails)', function () {
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['viewEmails']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/work-orders/{$workOrder->id}/emails/compose-context")->assertForbidden();
});
