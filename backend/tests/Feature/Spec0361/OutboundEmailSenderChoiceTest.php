<?php

use App\Jobs\SendOutboundEmailJob;
use App\Models\Contact;
use App\Models\OutboundEmail;
use App\Models\PersonalData;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| Sender choice in the email composer (spec 0361)
|--------------------------------------------------------------------------
*/

uses(RefreshDatabase::class);

/**
 * The actor's card holds: a work email, a primary personal email, a PEC
 * (excluded) and a case-only duplicate of the login email (dropped).
 */
function senderChoiceActor(WorkOrder $workOrder): User
{
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $actor->forceFill(['email' => 'mario.rossi@example.com'])->save();

    $card = PersonalData::factory()->for($actor, 'personable')->create();
    Contact::factory()->email()->for($card, 'contactable')->create(['value' => 'shared@example.com', 'is_primary' => false, 'is_work' => true]);
    Contact::factory()->email()->for($card, 'contactable')->create(['value' => 'mario.personal@example.com', 'is_primary' => true]);
    Contact::factory()->pec()->for($card, 'contactable')->create(['value' => 'mario@pec.example.com']);
    Contact::factory()->email()->for($card, 'contactable')->create(['value' => 'Mario.Rossi@example.com', 'is_primary' => false]);

    return $actor;
}

function senderChoiceDraft(WorkOrder $workOrder, User $actor, array $attributes = []): OutboundEmail
{
    return OutboundEmail::factory()->forEmailable($workOrder)->create([
        'sender_user_id' => $actor->id,
        'to_recipients' => ['dest@example.com'],
        'subject' => 'Oggetto',
        'body' => '<p>Corpo</p>',
        ...$attributes,
    ]);
}

it('AC-001: compose-context lists the login email first, then the card emails, no PEC, no duplicates', function () {
    $workOrder = WorkOrder::factory()->create();
    $actor = senderChoiceActor($workOrder);
    Sanctum::actingAs($actor);

    $this->getJson("/api/work-orders/{$workOrder->id}/emails/compose-context")
        ->assertOk()
        ->assertJsonPath('data.sender.email', 'mario.rossi@example.com')
        ->assertJsonPath('data.sender_options', ['mario.rossi@example.com', 'mario.personal@example.com', 'shared@example.com']);
});

it('AC-001: without a personal-data card the only option is the login email', function () {
    $workOrder = WorkOrder::factory()->create();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/work-orders/{$workOrder->id}/emails/compose-context")
        ->assertOk()
        ->assertJsonPath('data.sender_options', [$actor->email]);
});

it('AC-002: a draft saves one of the actor\'s own addresses as sender', function () {
    $workOrder = WorkOrder::factory()->create();
    $actor = senderChoiceActor($workOrder);
    $email = senderChoiceDraft($workOrder, $actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/work-orders/{$workOrder->id}/emails/{$email->id}", ['from_address' => 'shared@example.com'])
        ->assertOk()
        ->assertJsonPath('data.from_address', 'shared@example.com');

    $this->postJson("/api/work-orders/{$workOrder->id}/emails", ['from_address' => 'mario.personal@example.com'])
        ->assertCreated()
        ->assertJsonPath('data.from_address', 'mario.personal@example.com');
});

it('AC-002: an address that is not the actor\'s own (or a PEC) is 422 from_address', function (string $address) {
    $workOrder = WorkOrder::factory()->create();
    $actor = senderChoiceActor($workOrder);
    $email = senderChoiceDraft($workOrder, $actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/work-orders/{$workOrder->id}/emails/{$email->id}", ['from_address' => $address])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['from_address']);

    expect($email->fresh()->from_address)->toBeNull();
})->with(['ceo@example.com', 'mario@pec.example.com']);

it('AC-003: send uses the sender saved on the draft', function () {
    Queue::fake();
    $workOrder = WorkOrder::factory()->create();
    $actor = senderChoiceActor($workOrder);
    $email = senderChoiceDraft($workOrder, $actor, ['from_address' => 'shared@example.com']);
    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/emails/{$email->id}/send")
        ->assertStatus(202)
        ->assertJsonPath('data.from_address', 'shared@example.com');

    Queue::assertPushed(SendOutboundEmailJob::class);
});

it('AC-003: send without a chosen sender uses the login email', function () {
    Queue::fake();
    $workOrder = WorkOrder::factory()->create();
    $actor = senderChoiceActor($workOrder);
    $email = senderChoiceDraft($workOrder, $actor);
    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/emails/{$email->id}/send")
        ->assertStatus(202)
        ->assertJsonPath('data.from_address', 'mario.rossi@example.com');
});

it('AC-003: a saved sender no longer among the actor\'s addresses blocks the send with 422', function () {
    Queue::fake();
    $workOrder = WorkOrder::factory()->create();
    $actor = senderChoiceActor($workOrder);
    $email = senderChoiceDraft($workOrder, $actor, ['from_address' => 'shared@example.com']);
    Contact::query()->where('value', 'shared@example.com')->delete();
    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/emails/{$email->id}/send")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['from_address']);

    Queue::assertNotPushed(SendOutboundEmailJob::class);
});
