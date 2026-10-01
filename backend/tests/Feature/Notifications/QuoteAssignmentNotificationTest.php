<?php

use App\Enums\AssignmentRoleEnum;
use App\Enums\AssignmentTargetEnum;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\User;
use App\Notifications\RecordAssignmentNotification;
use App\Support\ManagerPositions;
use App\Support\Notifications\RecordDetails;
use App\Support\Notifications\RecordLinkResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0186 — the assignment notification of an Offerta is an OFFERTA
// notification (title, card, link), and the Offerta form notifies every
// newly inserted Gestore Account. AC-001 -> AC-007.

uses(RefreshDatabase::class);

if (! function_exists('quoteNotificationUserWith')) {
    /**
     * @param  array<int, string>  $permissions  fully qualified, e.g. "quotes.view"
     */
    function quoteNotificationUserWith(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission));
        }

        return $user;
    }
}

if (! function_exists('quoteNotificationOfferLines')) {
    /**
     * POST /api/quotes requires one offer line (spec 0102), in a category
     * with an effective business function so the coverage guard holds.
     *
     * @return array<int, array<string, int>>
     */
    function quoteNotificationOfferLines(): array
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);
        $product = Product::factory()->create(['category_id' => $category->id]);

        return [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]];
    }
}

// ---------------------------------------------------------------------------
// AC-001 / AC-002 / AC-003 — the link follows the Offerta's own module order
// ---------------------------------------------------------------------------

it('opens the Offerte module for a recipient who may see it, even with opportunities and requests too (AC-001)', function () {
    $recipient = quoteNotificationUserWith(['quotes.view', 'opportunities.view', 'request-management.view']);

    expect(RecordLinkResolver::pathFor($recipient, AssignmentTargetEnum::Quote, 9))->toBe('/quotes/9');
});

it('falls back to request management for a recipient who may only see that module (AC-002)', function () {
    $recipient = quoteNotificationUserWith(['request-management.view', 'opportunities.view']);

    expect(RecordLinkResolver::pathFor($recipient, AssignmentTargetEnum::Quote, 9))->toBe('/request-management/9');
});

it('gives a recipient with neither module no link, no mail button and an explanation (AC-003)', function () {
    $recipient = quoteNotificationUserWith(['opportunities.view']);
    $notification = new RecordAssignmentNotification(
        target: AssignmentTargetEnum::Quote,
        role: AssignmentRoleEnum::Manager,
        recordId: 9,
        recordLabel: 'QUO-0009',
        position: 2,
        actorName: 'Actor',
    );

    $payload = $notification->toArray($recipient);

    expect($payload['action_url'])->toBeNull()
        ->and($payload['message'])->toContain(__('You cannot open this record: ask an administrator to grant you access to the module.'))
        ->and($notification->toMail($recipient)->actionUrl)->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-004 — Gestione Richieste notifies about the Offerta, not the Opportunity
// ---------------------------------------------------------------------------

it('notifies the GA2 assigned from request management with the Offerta title and link (AC-004)', function () {
    Notification::fake();

    Opportunity::factory()->count(2)->create();
    $actor = quoteNotificationUserWith(['request-management.view', 'request-management.viewAll', 'request-management.update', 'request-management.assignOperator']);
    $recipient = quoteNotificationUserWith(['quotes.view', 'opportunities.view', 'request-management.view']);
    $quote = Quote::factory()->create(['title' => 'QUO-0042 - Corso sicurezza']);
    Sanctum::actingAs($actor);

    expect($quote->id)->not->toBe($quote->opportunity_id);

    $this->patchJson("/api/request-management/{$quote->id}", ['manager_slots' => [null, $recipient->id]])->assertOk();

    Notification::assertSentTo($recipient, function (RecordAssignmentNotification $notification) use ($recipient, $quote): bool {
        $payload = $notification->toArray($recipient);

        return $payload['action_url'] === "/quotes/{$quote->id}"
            && str_contains((string) $payload['message'], 'QUO-0042 - Corso sicurezza');
    });
});

// ---------------------------------------------------------------------------
// AC-005 / AC-006 — the Offerta form notifies every newly inserted manager
// ---------------------------------------------------------------------------

it('notifies every manager newly inserted from the Offerta form, at its own slot (AC-005)', function () {
    Notification::fake();

    $actor = quoteNotificationUserWith(['quotes.view', 'quotes.update']);
    $ga1 = User::factory()->create();
    $ga3 = User::factory()->create();
    $quote = Quote::factory()->create();
    $quote->opportunity->managers()->sync([$ga1->id => ['position' => 1], $ga3->id => ['position' => 3]]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quotes/{$quote->id}", ['manager_slots' => [$ga1->id, null, $ga3->id]])->assertOk();

    foreach ([[$ga1, 1], [$ga3, 3]] as [$manager, $position]) {
        Notification::assertSentTo($manager, function (RecordAssignmentNotification $notification) use ($manager, $position): bool {
            return str_contains((string) $notification->toArray($manager)['message'], " {$position} ");
        });
    }
});

it('moving a manager between slots of the Offerta notifies nobody (AC-005)', function () {
    Notification::fake();

    $actor = quoteNotificationUserWith(['quotes.view', 'quotes.update']);
    $manager = User::factory()->create();
    $quote = Quote::factory()->create();
    $quote->opportunity->managers()->sync([$manager->id => ['position' => 1]]);
    $quote->managers()->sync([$manager->id => ['position' => 1]]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quotes/{$quote->id}", ['manager_slots' => [null, null, $manager->id]])->assertOk();

    Notification::assertNothingSent();
});

it('never notifies the actor who inserts themselves on the Offerta (AC-005)', function () {
    Notification::fake();

    $actor = quoteNotificationUserWith(['quotes.view', 'quotes.update']);
    $quote = Quote::factory()->create();
    $quote->opportunity->managers()->sync([$actor->id => ['position' => ManagerPositions::OPERATOR]]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quotes/{$quote->id}", ['manager_slots' => [null, $actor->id]])->assertOk();

    Notification::assertNothingSent();
});

it('notifies the managers submitted when the Offerta is created (AC-006)', function () {
    Notification::fake();

    $actor = quoteNotificationUserWith(['quotes.view', 'quotes.create']);
    $manager = quoteNotificationUserWith(['quotes.view']);
    $opportunity = Opportunity::factory()->create();
    $opportunity->managers()->sync([$manager->id => ['position' => 1]]);
    Sanctum::actingAs($actor);

    $quoteId = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => quoteNotificationOfferLines(),
        'manager_slots' => [$manager->id],
    ])->assertCreated()->json('data.id');

    Notification::assertSentTo($manager, function (RecordAssignmentNotification $notification) use ($manager, $quoteId): bool {
        return $notification->toArray($manager)['action_url'] === "/quotes/{$quoteId}";
    });
});

// Rev. 2026-10-01 (decisione utente, spec 0186 D-7): an Offerta created
// later notifies the managers it inherits from its Opportunity too.
it('notifies the managers an Offerta inherits from its Opportunity (AC-006)', function () {
    Notification::fake();

    $actor = quoteNotificationUserWith(['quotes.view', 'quotes.create']);
    $manager = quoteNotificationUserWith(['quotes.view']);
    $opportunity = Opportunity::factory()->create();
    $opportunity->managers()->sync([$manager->id => ['position' => 1]]);
    Sanctum::actingAs($actor);

    $quoteId = $this->postJson('/api/quotes', [
        'title' => 'Offerta',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => quoteNotificationOfferLines(),
    ])->assertCreated()->json('data.id');

    Notification::assertSentTo($manager, function (RecordAssignmentNotification $notification) use ($manager, $quoteId): bool {
        return $notification->toArray($manager)['action_url'] === "/quotes/{$quoteId}";
    });
});

// ---------------------------------------------------------------------------
// AC-007 — the detail card describes the Offerta
// ---------------------------------------------------------------------------

it('builds the detail card from the Offerta, not from its Opportunity (AC-007)', function () {
    $operator = User::factory()->create(['name' => 'Offer Operator']);
    $opportunityOperator = User::factory()->create(['name' => 'Opportunity Operator']);
    $quote = Quote::factory()->create(['title' => 'QUO-0007 - Offerta', 'operator_id' => $operator->id]);
    $quote->opportunity->managers()->sync([$opportunityOperator->id => ['position' => ManagerPositions::OPERATOR]]);

    $details = RecordDetails::for($quote);

    expect(RecordDetails::targetFor($quote))->toBe(AssignmentTargetEnum::Quote)
        ->and(RecordDetails::labelFor($quote))->toBe('QUO-0007 - Offerta')
        ->and($details['notifications.fields.title'])->toBe('QUO-0007 - Offerta')
        ->and($details['notifications.fields.operator'])->toBe('Offer Operator')
        ->and($details['notifications.fields.status'])->toBe($quote->quoteWorkflowStatus->name);
});
