<?php

use App\Models\User;
use App\Notifications\PurchaseRequestSubmittedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(fn () => Notification::fake());

it('AC-013: creating a request notifies the function manager by database and mail', function () {
    Sanctum::actingAs($author = purchaseRequestUserWith(['create']));
    $manager = User::factory()->create();

    $this->postJson('/api/purchase-requests', purchaseRequestPayload(['function_manager_id' => $manager->id]))->assertCreated();

    Notification::assertSentTo($manager, PurchaseRequestSubmittedNotification::class, function ($notification, array $channels) use ($manager): bool {
        $payload = $notification->toArray($manager);

        return $channels === ['database', 'mail']
            && $payload['title'] === 'Purchase request to review'
            && str_contains($payload['message'], 'Laptops')
            && $payload['action_url'] === null;
    });
    Notification::assertNotSentTo($author, PurchaseRequestSubmittedNotification::class);
});

it('AC-013: the notification links the request to a recipient who can open it', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['create']));
    $manager = purchaseRequestUserWith(['view']);

    $id = $this->postJson('/api/purchase-requests', purchaseRequestPayload(['function_manager_id' => $manager->id]))
        ->assertCreated()->json('data.id');

    Notification::assertSentTo($manager, PurchaseRequestSubmittedNotification::class, fn ($notification): bool => $notification->toArray($manager)['action_url'] === "/purchase-requests/{$id}");
});

it('AC-013: an author who is also the function manager is not notified', function () {
    Sanctum::actingAs($author = purchaseRequestUserWith(['create']));

    $this->postJson('/api/purchase-requests', purchaseRequestPayload(['function_manager_id' => $author->id]))->assertCreated();

    Notification::assertNothingSent();
});

it('AC-013: notify-manager sends the notification again', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll', 'update']));
    $manager = User::factory()->create(['name' => 'Giulia Verdi']);
    $request = purchaseRequestWithLines(attributes: ['function_manager_id' => $manager->id]);

    $this->postJson("/api/purchase-requests/{$request->id}/notify-manager")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Purchase request sent to Giulia Verdi');

    Notification::assertSentToTimes($manager, PurchaseRequestSubmittedNotification::class, 1);
});

it('AC-013: notify-manager answers 422 when the manager has no email', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll', 'update']));
    $manager = User::factory()->create();
    $manager->forceFill(['email' => ''])->save();
    $request = purchaseRequestWithLines(attributes: ['function_manager_id' => $manager->id]);

    $this->postJson("/api/purchase-requests/{$request->id}/notify-manager")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('function_manager_id');

    Notification::assertNothingSent();
});

it('AC-013: notify-manager needs the update permission', function () {
    Sanctum::actingAs(purchaseRequestUserWith(['view', 'viewAll']));
    $request = purchaseRequestWithLines();

    $this->postJson("/api/purchase-requests/{$request->id}/notify-manager")->assertForbidden();
    Notification::assertNothingSent();
});
