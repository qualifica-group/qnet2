<?php

use App\Models\Note;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use App\Notifications\NoteMentionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

// The per-recipient deep link of a mention notification (decisione utente
// 2026-09-07). Built directly instead of through the endpoint: what is under
// test is the link resolution, not the dispatch — NoteMentionNotificationTest
// already covers the after-commit path, at the cost of a migrate:fresh per
// test.

uses(RefreshDatabase::class);

if (! function_exists('mentionRecipientWith')) {
    /**
     * @param  array<int, string>  $permissions
     */
    function mentionRecipientWith(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission));
        }

        return $user;
    }
}

if (! function_exists('mentionNotificationOn')) {
    function mentionNotificationOn(Opportunity $opportunity, ?Quote $quote): NoteMentionNotification
    {
        $author = User::factory()->create(['name' => 'Author']);

        $note = Note::factory()->create([
            'notable_type' => $opportunity->getMorphClass(),
            'notable_id' => $opportunity->id,
            'user_id' => $author->id,
            'quote_id' => $quote?->id,
        ]);

        return new NoteMentionNotification($note, $author, $opportunity->name, 'request-management', $opportunity);
    }
}

it('points a scoped note at its own Offerta, not at the host Opportunity', function () {
    $opportunity = Opportunity::factory()->create();
    // Two Offerte so the Quote id can never coincide with the Opportunity id:
    // the whole point of this assertion is that the two are different records.
    Quote::factory()->for($opportunity)->create();
    $quote = Quote::factory()->for($opportunity)->create();
    $recipient = mentionRecipientWith(['request-management.view']);

    $payload = mentionNotificationOn($opportunity, $quote)->toArray($recipient);

    // The SPA route (and its API counterpart) is keyed on the Quote, not on
    // the Opportunity: spec 0086, D-1/D-2.
    expect($payload['action_url'])->toBe('/request-management/'.$quote->id)
        ->and($payload['action_url'])->not->toBe('/request-management/'.$opportunity->id);
});

it('points a general note at the Opportunity, the only screen that shows it', function () {
    $opportunity = Opportunity::factory()->create();
    $recipient = mentionRecipientWith(['request-management.view', 'opportunities.view']);

    $payload = mentionNotificationOn($opportunity, null)->toArray($recipient);

    expect($payload['action_url'])->toBe('/opportunities/'.$opportunity->id);
});

it('falls back to the request-management list for a general note the recipient cannot reach otherwise', function () {
    $opportunity = Opportunity::factory()->create();
    $recipient = mentionRecipientWith(['request-management.view']);

    $payload = mentionNotificationOn($opportunity, null)->toArray($recipient);

    expect($payload['action_url'])->toBe('/request-management');
});

it('prefers the Opportunity when the recipient may not work requests', function () {
    $opportunity = Opportunity::factory()->create();
    $quote = Quote::factory()->for($opportunity)->create();
    $recipient = mentionRecipientWith(['opportunities.view']);

    $payload = mentionNotificationOn($opportunity, $quote)->toArray($recipient);

    expect($payload['action_url'])->toBe('/opportunities/'.$opportunity->id);
});

it('links nothing, and says so, when no module is reachable', function () {
    $opportunity = Opportunity::factory()->create();
    $quote = Quote::factory()->for($opportunity)->create();
    $recipient = User::factory()->create();

    $payload = mentionNotificationOn($opportunity, $quote)->toArray($recipient);

    expect($payload['action_url'])->toBeNull()
        ->and($payload['message'])->toContain('ask an administrator');
});

it('never stores an absolute URL, whichever branch resolves', function () {
    $opportunity = Opportunity::factory()->create();
    $quote = Quote::factory()->for($opportunity)->create();

    foreach ([
        ['request-management.view'],
        ['opportunities.view'],
        ['request-management.view', 'opportunities.view'],
    ] as $permissions) {
        $payload = mentionNotificationOn($opportunity, $quote)->toArray(mentionRecipientWith($permissions));

        expect($payload['action_url'])->toStartWith('/')
            ->and($payload['action_url'])->not->toStartWith('//');
    }
});
