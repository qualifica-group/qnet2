<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\Role;
use App\Models\Source;
use App\Models\User;
use App\Notifications\QuoteStatusNotification;
use App\Services\Notifications\AssignmentNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0419: a working status of the offer can send an email (subject,
 * message, recipients) when a user moves an offer into it.
 */
uses(RefreshDatabase::class);

if (! function_exists('statusNotifyConfigurator')) {
    function statusNotifyConfigurator(): User
    {
        foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
            Permission::findOrCreate("quote-workflows.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['quote-workflows.viewAny', 'quote-workflows.view', 'quote-workflows.create', 'quote-workflows.update']);

        return $user;
    }
}

if (! function_exists('statusNotifyQuoteActor')) {
    function statusNotifyQuoteActor(): User
    {
        foreach (['viewAny', 'view', 'update', 'viewAll'] as $ability) {
            Permission::findOrCreate("quotes.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['quotes.viewAny', 'quotes.view', 'quotes.update', 'quotes.viewAll']);

        return $user;
    }
}

if (! function_exists('statusNotifyPayload')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function statusNotifyPayload(array $overrides = []): array
    {
        return [
            'subject' => 'Offerta in attesa di preventivo',
            'message' => "Preparare il preventivo.\nGrazie.",
            'record_team' => false,
            'user_ids' => [],
            'role_ids' => [],
            'emails' => ['backoffice@example.com'],
            ...$overrides,
        ];
    }
}

if (! function_exists('statusNotifyDefaultRows')) {
    /**
     * The global default set as the PUT payload, the custom row named $name carrying $notify.
     *
     * @param  array<string, mixed>|null  $notify
     * @return array<int, array<string, mixed>>
     */
    function statusNotifyDefaultRows(QuoteWorkflowStatus $target, ?array $notify): array
    {
        return QuoteWorkflowStatus::query()->whereNull('quote_workflow_id')->orderBy('sort_order')->get()
            ->map(fn (QuoteWorkflowStatus $status): array => [
                'id' => $status->id,
                'name' => $status->name,
                'color' => $status->color,
                'group' => $status->group->value,
                'requires_note' => $status->requires_note,
                ...($status->is($target) && $notify !== null ? ['notify' => $notify] : []),
            ])->all();
    }
}

if (! function_exists('statusNotifyTarget')) {
    /**
     * A global status the factory quote (no lines, no workflow) can move into.
     *
     * @param  array<string, mixed>|null  $notify
     */
    function statusNotifyTarget(?array $notify): QuoteWorkflowStatus
    {
        return QuoteWorkflowStatus::factory()->global()->create([
            'name' => 'Attesa di preventivo',
            'group' => 'pending',
            'sort_order' => 99,
            'notify' => $notify,
        ]);
    }
}

it('AC-001: PUT default-statuses persists notify and returns it; omitting it clears it', function () {
    $target = statusNotifyTarget(null);
    Sanctum::actingAs(statusNotifyConfigurator());
    $notify = statusNotifyPayload(['record_team' => true]);

    $this->putJson('/api/quote-workflows/default-statuses', ['statuses' => statusNotifyDefaultRows($target, $notify)])
        ->assertOk()
        ->assertJsonFragment(['notify' => $notify]);

    expect($target->fresh()->notify)->toBe($notify);

    $this->putJson('/api/quote-workflows/default-statuses', ['statuses' => statusNotifyDefaultRows($target, null)])
        ->assertOk();

    expect($target->fresh()->notify)->toBeNull();
});

it('AC-002: an invalid notify is refused with the contract key', function (array $notify, string $key) {
    $target = statusNotifyTarget(null);
    Sanctum::actingAs(statusNotifyConfigurator());
    $rows = statusNotifyDefaultRows($target, $notify);
    $index = collect($rows)->search(fn (array $row): bool => $row['id'] === $target->id);

    $this->putJson('/api/quote-workflows/default-statuses', ['statuses' => $rows])
        ->assertStatus(422)
        ->assertJsonValidationErrors(str_replace('N', (string) $index, $key));
})->with([
    'no recipient' => [statusNotifyPayload(['emails' => []]), 'statuses.N.notify'],
    'blank subject' => [statusNotifyPayload(['subject' => '']), 'statuses.N.notify.subject'],
    'missing message' => [array_diff_key(statusNotifyPayload(), ['message' => true]), 'statuses.N.notify.message'],
    'unknown user' => [statusNotifyPayload(['user_ids' => [999999]]), 'statuses.N.notify.user_ids.0'],
    'duplicate role' => [statusNotifyPayload(['role_ids' => [1, 1]]), 'statuses.N.notify.role_ids'],
    'invalid email' => [statusNotifyPayload(['emails' => ['not-an-email']]), 'statuses.N.notify.emails.0'],
    'duplicate email' => [statusNotifyPayload(['emails' => ['a@example.com', 'A@example.com']]), 'statuses.N.notify.emails'],
]);

it('AC-003: POST quote-workflows stores notify on a custom row and on a pinned row', function () {
    Sanctum::actingAs(statusNotifyConfigurator());
    $notify = statusNotifyPayload();

    $response = $this->postJson('/api/quote-workflows', [
        'name' => 'Workflow con notifiche',
        'criteria' => [['field' => 'source_id', 'value_id' => Source::factory()->create()->id]],
        'statuses' => [
            ['name' => 'Aperta', 'group' => 'open', 'system_key' => 'open', 'notify' => $notify],
            ['name' => 'Attesa di preventivo', 'group' => 'pending', 'notify' => $notify],
            ['name' => 'Vinta', 'group' => 'closed_won', 'system_key' => 'closed_won'],
            ['name' => 'Persa', 'group' => 'closed_lost', 'system_key' => 'closed_lost'],
        ],
    ])->assertCreated();

    $statuses = QuoteWorkflowStatus::query()->where('quote_workflow_id', $response->json('data.id'))->get()->keyBy('name');

    expect($statuses['Aperta']->notify)->toBe($notify)
        ->and($statuses['Attesa di preventivo']->notify)->toBe($notify)
        ->and($statuses['Vinta']->notify)->toBeNull();
});

it('AC-004: the offer team receives the configured email, the author does not', function () {
    Notification::fake();
    $actor = statusNotifyQuoteActor();
    $supervisor = User::factory()->create();
    $manager = User::factory()->create();
    $quote = Quote::factory()->create(['opportunity_id' => Opportunity::factory(), 'supervisor_id' => $supervisor->id]);
    $quote->managers()->sync([$manager->id => ['position' => 1], $actor->id => ['position' => 3]]);
    $target = statusNotifyTarget(statusNotifyPayload(['record_team' => true, 'emails' => []]));
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quotes/{$quote->id}", ['quote_workflow_status_id' => $target->id])->assertOk();

    foreach ([$supervisor, $manager] as $recipient) {
        Notification::assertSentTo($recipient, QuoteStatusNotification::class, function (QuoteStatusNotification $notification, array $channels) use ($recipient): bool {
            $data = $notification->toArray($recipient);
            $mail = $notification->toMail($recipient);

            return $channels === ['database', 'mail']
                && $data['title'] === 'Offerta in attesa di preventivo'
                && str_contains((string) $data['message'], 'Attesa di preventivo')
                && $mail->subject === 'Offerta in attesa di preventivo'
                && str_contains((string) $mail->render(), 'Preparare il preventivo.<br>');
        });
    }
    Notification::assertNotSentTo($actor, QuoteStatusNotification::class);
});

it('AC-005: chosen users, role holders and free addresses, active users only, no double mail', function () {
    Notification::fake();
    $actor = statusNotifyQuoteActor();
    $active = User::factory()->create(['email' => 'attivo@example.com']);
    $inactive = User::factory()->create(['is_active' => false]);
    $role = Role::query()->create(['name' => 'back-office-notify', 'guard_name' => 'web']);
    $roleHolder = User::factory()->create();
    $roleHolder->assignRole($role);
    $quote = Quote::factory()->create(['opportunity_id' => Opportunity::factory()]);
    $target = statusNotifyTarget(statusNotifyPayload([
        'user_ids' => [$active->id, $inactive->id],
        'role_ids' => [$role->id],
        'emails' => ['esterno@example.com', 'ATTIVO@example.com'],
    ]));
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quotes/{$quote->id}", ['quote_workflow_status_id' => $target->id])->assertOk();

    Notification::assertSentTo($active, QuoteStatusNotification::class);
    Notification::assertSentTo($roleHolder, QuoteStatusNotification::class);
    Notification::assertNotSentTo($inactive, QuoteStatusNotification::class);
    Notification::assertSentOnDemand(QuoteStatusNotification::class, function (QuoteStatusNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool {
        return $notifiable->routes['mail'] === 'esterno@example.com' && $channels === ['mail'];
    });
    Notification::assertSentOnDemandTimes(QuoteStatusNotification::class, 1);
});

it('AC-006: nothing is sent without notify, on a resend, when muted or when the change is refused', function () {
    Notification::fake();
    $actor = statusNotifyQuoteActor();
    $quote = Quote::factory()->create(['opportunity_id' => Opportunity::factory()]);
    $plain = statusNotifyTarget(null);
    $notifying = QuoteWorkflowStatus::factory()->global()->create([
        'group' => 'pending', 'sort_order' => 98, 'notify' => statusNotifyPayload(),
    ]);
    $refusing = QuoteWorkflowStatus::factory()->global()->create([
        'group' => 'pending', 'sort_order' => 97, 'requires_note' => true, 'notify' => statusNotifyPayload(),
    ]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quotes/{$quote->id}", ['quote_workflow_status_id' => $plain->id])->assertOk();
    AssignmentNotifier::muted(fn () => $this->patchJson("/api/quotes/{$quote->id}", ['quote_workflow_status_id' => $notifying->id])->assertOk());
    $this->patchJson("/api/quotes/{$quote->id}", ['quote_workflow_status_id' => $notifying->id])->assertOk();
    $this->patchJson("/api/quotes/{$quote->id}", ['quote_workflow_status_id' => $refusing->id])->assertStatus(422);

    Notification::assertNothingSent();
});
