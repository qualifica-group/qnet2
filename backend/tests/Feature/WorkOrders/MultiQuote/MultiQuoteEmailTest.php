<?php

use App\Models\Contact;
use App\Models\OutboundEmail;
use App\Models\Registry;
use App\Models\User;
use App\Services\OutboundEmails\WorkOrderEmailVariableResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('D-12: compose-context of a multi-offer commessa prefills no customer contact and offers no quote PDF', function () {
    foreach (['registries.view', 'referents.view', 'quotes.view'] as $permission) {
        Permission::findOrCreate($permission);
    }
    [$workOrder, $quotes] = multiQuoteReadFixture();
    $registry = Registry::factory()->withPersonalData()->create();
    $quotes[0]->opportunity->update(['registry_id' => $registry->id]);
    Contact::factory()->email()->for($registry->personalData, 'contactable')->create(['value' => 'client@example.com']);
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $actor->givePermissionTo(['registries.view', 'referents.view', 'quotes.view']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/work-orders/{$workOrder->id}/emails/compose-context")->assertOk();

    $sources = collect($response->json('data.recipient_suggestions'))->pluck('source')->unique()->values()->all();

    expect($sources)->toBe(['supervisor'])
        ->and($response->json('data.quote_pdf_available'))->toBeFalse();
});

it('D-12: quote, client and opportunity variables resolve empty on a multi-offer commessa', function () {
    [$workOrder] = multiQuoteReadFixture();

    $result = app(WorkOrderEmailVariableResolver::class)->render(
        subject: 'Commessa {work_order.code} / {client.name} / {quote.code} / {opportunity.name}',
        body: '<p>{client.name}|{quote.code}|{work_order.code}</p>',
        workOrder: $workOrder,
        actor: User::factory()->create(),
    );

    expect($result['subject'])->toBe("Commessa {$workOrder->code} /  /  / ")
        ->and($result['body'])->toBe("<p>||{$workOrder->code}</p>");
});

it('D-12: importing the quote PDF into a multi-offer commessa email is refused, not a crash', function () {
    Permission::findOrCreate('quotes.view');
    [$workOrder] = multiQuoteReadFixture();
    $actor = workOrderEmailActor($workOrder, ['sendEmail']);
    $actor->givePermissionTo('quotes.view');
    $email = OutboundEmail::factory()->forEmailable($workOrder)->create(['sender_user_id' => $actor->id]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/emails/{$email->id}/attachments/import", ['source' => 'quote_pdf'])
        ->assertUnprocessable();
});

it('the multi-offer compose-context stays closed to an actor outside the commessa', function () {
    [$workOrder] = multiQuoteReadFixture();
    $actor = workOrderEmailActor($workOrder, ['sendEmail'], inScope: false);
    Sanctum::actingAs($actor);

    $this->getJson("/api/work-orders/{$workOrder->id}/emails/compose-context")->assertForbidden();
});
