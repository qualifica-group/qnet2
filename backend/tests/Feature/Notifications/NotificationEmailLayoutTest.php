<?php

use App\Enums\AssignmentRoleEnum;
use App\Enums\AssignmentTargetEnum;
use App\Models\FieldChangeRequest;
use App\Models\Note;
use App\Models\Opportunity;
use App\Models\ProformaRequest;
use App\Models\PurchaseRequest;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\AbsenceRequestNotification;
use App\Notifications\FieldChangeRequestedNotification;
use App\Notifications\FieldChangeRequestResolvedNotification;
use App\Notifications\NoteMentionNotification;
use App\Notifications\RecordAssignmentNotification;
use App\Notifications\TaskClosed;
use App\Notifications\TaskUpdateRequested;
use App\Notifications\WelcomeUserNotification;
use App\Notifications\WorkOrderLineDeliverableNotification;
use App\Notifications\WorkOrderUpdateRequested;
use App\Services\FieldChangeRequests\FieldChangeRequestResolutionNotifier;
use App\Support\Notifications\ModuleActionLabel;
use App\Support\Notifications\RecordDetails;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\App as AppFacade;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;

// Spec 0381 — the module button (top and bottom, named after the module),
// the detail card in every notification email, the tax data of the
// anagrafica, one layout for every email.

uses(RefreshDatabase::class);

if (! function_exists('layoutRecipient')) {
    /**
     * @param  array<int, string>  $permissions
     */
    function layoutRecipient(array $permissions = [], string $locale = 'it'): User
    {
        $user = User::factory()->create(['name' => 'Mario Rossi', 'locale' => $locale]);

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission));
        }

        AppFacade::setLocale($locale);

        return $user;
    }
}

if (! function_exists('registryAssignmentMail')) {
    function registryAssignmentMail(Registry $registry, User $recipient): MailMessage
    {
        return (new RecordAssignmentNotification(
            target: AssignmentTargetEnum::Registry,
            role: AssignmentRoleEnum::Supervisor,
            recordId: $registry->id,
            recordLabel: $registry->name,
            position: null,
            actorName: 'Rosa Falzarano',
            details: RecordDetails::for($registry->fresh()),
        ))->toMail($recipient);
    }
}

if (! function_exists('companyRegistry')) {
    function companyRegistry(): Registry
    {
        return Registry::factory()
            ->withPersonalData(fn ($card) => $card->company()->state([
                'vat_number' => '01234567890',
                'tax_code' => '01234567890',
                'sdi_code' => 'ABC1234',
            ]))
            ->create(['is_supplier' => false]);
    }
}

if (! function_exists('mailRows')) {
    /**
     * @return array<string, string> translated label => value
     */
    function mailRows(MailMessage $mail): array
    {
        return collect($mail->viewData['details'])->pluck('value', 'label')->all();
    }
}

if (! function_exists('noteMentionMail')) {
    function noteMentionMail(string $entityType, Model $record, User $recipient, ?int $quoteId = null): MailMessage
    {
        $note = Note::factory()->make(['body' => '<p>Ciao</p>']);
        $note->forceFill([
            'notable_type' => $record->getMorphClass(),
            'notable_id' => $record->getKey(),
            'quote_id' => $quoteId,
        ])->save();

        return (new NoteMentionNotification($note->fresh(), User::factory()->create(), 'Etichetta', $entityType, $record))
            ->toMail($recipient);
    }
}

// ---------------------------------------------------------------------------
// AC-003 — the button names the module it opens
// ---------------------------------------------------------------------------

it('names the button after the module of the destination path (AC-003)', function (string $path, string $italian, string $english) {
    AppFacade::setLocale('it');
    expect(__(ModuleActionLabel::for($path)))->toBe($italian);

    AppFacade::setLocale('en');
    expect(__(ModuleActionLabel::for($path)))->toBe($english);
})->with([
    ['/registries/5', 'Apri anagrafica', 'Open registry'],
    ['/referents/5', 'Apri referente', 'Open referent'],
    ['/opportunities/5', 'Apri opportunità', 'Open opportunity'],
    ['/quotes/5', 'Apri offerta', 'Open quote'],
    ['/request-management/5', 'Apri richiesta', 'Open request'],
    ['/enrollee-management/5', 'Apri iscritto', 'Open enrollee'],
    ['/work-orders/5', 'Apri commessa', 'Open work order'],
    ['/tasks/5', 'Apri attività', 'Open task'],
    ['/purchase-requests/5', 'Apri richiesta di acquisto', 'Open purchase request'],
    ['/proforma-requests/5', 'Apri richiesta proforma', 'Open proforma request'],
    ['/absences?request=5', 'Apri assenze', 'Open absences'],
    ['/field-change-requests/5', 'Apri richiesta di modifica', 'Open change request'],
    ['/somewhere-else/5', 'Apri in QNet', 'Open in QNet'],
]);

// ---------------------------------------------------------------------------
// AC-001 / AC-002 — button on top and at the bottom, none without access
// ---------------------------------------------------------------------------

// REQUIREMENT CHANGED (U-2 rev., 2026-11-10): one button, after the card.
it('shows one module button, after the detail card (AC-001)', function () {
    $registry = companyRegistry();
    $html = (string) registryAssignmentMail($registry, layoutRecipient(['registries.view']))->render();

    expect(substr_count($html, 'class="button"'))->toBe(1)
        ->and(strpos($html, 'class="button"'))->toBeGreaterThan(strpos($html, 'class="details"'))
        ->and($html)->toContain('Apri anagrafica')
        ->and($html)->toContain(rtrim((string) config('app.frontend_url'), '/')."/registries/{$registry->id}");
});

it('shows one button when there is no detail card', function () {
    $html = (string) (new AbsenceRequestNotification(
        event: AbsenceRequestNotification::EVENT_SUBMITTED,
        requestId: 3,
        personId: 99,
        personName: '',
        typeName: '',
        period: '',
        actorName: 'Rosa',
    ))->toMail(layoutRecipient(['absence-requests.viewAny']))->render();

    expect(substr_count($html, 'class="button"'))->toBe(1)
        ->and($html)->not->toContain('class="details"');
});

it('drops the button but keeps the card for a recipient who cannot open the record (AC-002)', function () {
    $mail = registryAssignmentMail(companyRegistry(), layoutRecipient());
    $html = (string) $mail->render();

    expect($mail->actionUrl)->toBeNull()
        ->and($html)->not->toContain('class="button"')
        ->and($html)->toContain('class="details"')
        ->and($html)->toContain('chiedi a un amministratore');
});

// ---------------------------------------------------------------------------
// AC-004 / AC-005 — the anagrafica card and its tax data
// ---------------------------------------------------------------------------

it('carries the tax data in the anagrafica card, in order (AC-004)', function () {
    $supervisor = User::factory()->create(['name' => 'Rosa Falzarano']);
    $registry = companyRegistry();
    $registry->update(['supervisor_id' => $supervisor->id]);

    $rows = mailRows(registryAssignmentMail($registry, layoutRecipient(['registries.view'])));

    expect(array_keys($rows))->toBe(['Denominazione', 'Tipo', 'Partita IVA', 'Codice fiscale', 'Codice SDI', 'Supervisore'])
        ->and($rows['Partita IVA'])->toBe('01234567890')
        ->and($rows['Codice SDI'])->toBe('ABC1234')
        ->and($rows['Tipo'])->toBe('Cliente');
});

it('omits an empty tax field', function () {
    $registry = Registry::factory()
        ->withPersonalData(fn ($card) => $card->company()->state(['vat_number' => '01234567890', 'tax_code' => null, 'sdi_code' => null]))
        ->create();

    $rows = mailRows(registryAssignmentMail($registry, layoutRecipient(['registries.view'])));

    expect($rows)->toHaveKey('Partita IVA')
        ->not->toHaveKey('Codice fiscale')
        ->not->toHaveKey('Codice SDI');
});

it('hides the tax data from a recipient who cannot open the anagrafica (AC-005)', function () {
    $rows = mailRows(registryAssignmentMail(companyRegistry(), layoutRecipient()));

    expect($rows)->toHaveKey('Denominazione')
        ->not->toHaveKey('Partita IVA')
        ->not->toHaveKey('Codice fiscale')
        ->not->toHaveKey('Codice SDI');
});

// ---------------------------------------------------------------------------
// AC-006 / AC-009 — the emails that had no card
// ---------------------------------------------------------------------------

it('describes the absence request (AC-006)', function () {
    $mail = (new AbsenceRequestNotification(
        event: AbsenceRequestNotification::EVENT_REJECTED,
        requestId: 3,
        personId: 99,
        personName: 'Anna Bianchi',
        typeName: 'Ferie',
        period: '01/12/2026 - 05/12/2026',
        actorName: 'Rosa',
        reason: 'Picco di lavoro',
    ))->toMail(layoutRecipient(['absence-requests.viewAny']));

    expect(mailRows($mail))->toBe([
        'Persona' => 'Anna Bianchi',
        'Tipo' => 'Ferie',
        'Periodo' => '01/12/2026 - 05/12/2026',
        'Motivazione' => 'Picco di lavoro',
    ])->and($mail->actionText)->toBe('Apri assenze');
});

it('omits the reason of an absence request that has none (AC-006)', function () {
    $mail = (new AbsenceRequestNotification(
        event: AbsenceRequestNotification::EVENT_SUBMITTED,
        requestId: 3,
        personId: 99,
        personName: 'Anna Bianchi',
        typeName: 'Ferie',
        period: '01/12/2026',
        actorName: 'Rosa',
    ))->toMail(layoutRecipient(['absence-requests.viewAny']));

    expect(mailRows($mail))->not->toHaveKey('Motivazione');
});

it('carries the Task card in the update request email (AC-009)', function () {
    $task = Task::factory()->create(['title' => 'Rinnovo contratto Acme']);
    $recipient = layoutRecipient(['tasks.viewAny']);

    $mail = (new TaskUpdateRequested($task, User::factory()->create(), 'A che punto siamo?'))->toMail($recipient);

    expect(mailRows($mail))->toHaveKey('Titolo')
        ->and(mailRows($mail)['Titolo'])->toBe('Rinnovo contratto Acme');
});

it('carries the work order card in the update request email (AC-009)', function () {
    $workOrder = WorkOrder::factory()->create(['title' => 'Formazione sicurezza']);

    $mail = (new WorkOrderUpdateRequested($workOrder, User::factory()->create(), 'Novita?'))
        ->toMail(layoutRecipient(['work-orders.view']));

    expect(mailRows($mail)['Titolo'])->toBe("{$workOrder->code} - Formazione sicurezza")
        ->and($mail->actionText)->toBe('Apri commessa');
});

it('puts the line before the work order card in the deliverable email (AC-009)', function () {
    $workOrder = WorkOrder::factory()->create(['title' => 'Formazione sicurezza']);

    $mail = (new WorkOrderLineDeliverableNotification(
        workOrderId: $workOrder->id,
        workOrderLabel: 'COM - Formazione sicurezza',
        productName: 'Corso RSPP',
        statusName: 'Pagato',
        actorName: 'Rosa',
        workOrderDetails: RecordDetails::for($workOrder),
    ))->toMail(layoutRecipient(['work-orders.view']));

    expect(array_slice(array_keys(mailRows($mail)), 0, 3))->toBe(['Prodotto', 'Stato pagamento', 'Titolo']);
});

// ---------------------------------------------------------------------------
// AC-007 — change requests: the change, then the record it is about
// ---------------------------------------------------------------------------

it('describes a new change request and the opportunity it is about (AC-007)', function () {
    $opportunity = Opportunity::factory()->create(['name' => 'OPP_7']);
    $request = FieldChangeRequest::factory()->create([
        'subject_id' => $opportunity->id,
        'current_label' => 'Web',
        'requested_label' => 'Fiera',
    ]);
    $requester = User::factory()->create(['name' => 'Anna Bianchi']);

    $mail = (new FieldChangeRequestedNotification($request, $requester, 'OPP_7', RecordDetails::for($opportunity)))
        ->toMail(layoutRecipient());
    $rows = mailRows($mail);

    expect($rows)->toHaveKeys(['Campo', 'Valore attuale', 'Valore richiesto', 'Richiedente', 'Titolo'])
        ->and($rows['Valore attuale'])->toBe('Web')
        ->and($rows['Valore richiesto'])->toBe('Fiera')
        ->and($rows['Titolo'])->toBe('OPP_7')
        ->and($mail->actionText)->toBe('Apri richiesta di modifica');
});

it('sends the outcome with the card of the opportunity (AC-007)', function () {
    Notification::fake();

    $opportunity = Opportunity::factory()->create(['name' => 'OPP_7']);
    $request = FieldChangeRequest::factory()->create([
        'subject_id' => $opportunity->id,
        'status' => 'approved',
        'handling_note' => 'Confermato',
    ]);
    $handler = User::factory()->create(['name' => 'Rosa Falzarano']);

    app(FieldChangeRequestResolutionNotifier::class)->notify($request, $handler);

    Notification::assertSentTo($request->requestedBy, FieldChangeRequestResolvedNotification::class, function ($notification) use ($request): bool {
        $mail = $notification->toMail(layoutRecipient());
        $rows = mailRows($mail);

        return $rows['Esito'] === 'Approvata'
            && $rows['Nota'] === 'Confermato'
            && $rows['Gestita da'] === 'Rosa Falzarano'
            && $rows['Titolo'] === 'OPP_7'
            // The protected field lives in Gestione Richieste (config/field-change-requests.php).
            && $mail->actionText === 'Apri richiesta'
            && str_ends_with((string) $mail->actionUrl, "/request-management/{$request->subject_id}");
    });
});

// ---------------------------------------------------------------------------
// AC-008 — the mention email carries the annotated record's card
// ---------------------------------------------------------------------------

it('carries the card of the annotated record in the mention email (AC-008)', function (string $entityType, Closure $record, string $label, Closure $value) {
    $model = $record();

    $rows = mailRows(noteMentionMail($entityType, $model, layoutRecipient()));

    expect($rows[$label])->toBe($value($model));
})->with([
    'anagrafica' => ['registries', fn () => companyRegistry(), 'Denominazione', fn (Registry $r) => $r->name],
    'referente' => ['referents', fn () => Referent::factory()->create(['name' => 'Luca Neri']), 'Denominazione', fn () => 'Luca Neri'],
    'commessa' => ['work-orders', fn () => WorkOrder::factory()->create(), 'Titolo', fn (WorkOrder $w) => "{$w->code} - {$w->title}"],
    'attivita' => ['tasks', fn () => Task::factory()->create(['title' => 'Chiamare Acme']), 'Titolo', fn () => 'Chiamare Acme'],
    'proforma' => ['proforma-requests', fn () => ProformaRequest::factory()->create(), 'Stato', fn () => 'Da evadere'],
    'richiesta di acquisto' => ['purchase-requests', fn () => PurchaseRequest::factory()->create(['subject' => 'Toner']), 'Oggetto', fn () => 'Toner'],
    'opportunita' => ['request-management', fn () => Opportunity::factory()->create(['name' => 'OPP_9']), 'Titolo', fn () => 'OPP_9'],
]);

it('carries the Offerta card when the note names one (AC-008)', function () {
    $opportunity = Opportunity::factory()->create(['name' => 'OPP_9']);
    $quote = Quote::factory()->for($opportunity)->create(['title' => 'Offerta corso']);

    $rows = mailRows(noteMentionMail('request-management', $opportunity, layoutRecipient(), $quote->id));

    expect($rows['Titolo'])->toBe('Offerta corso');
});

it('quotes the note on its own line and leaves the title to the card in the mention email', function () {
    $task = Task::factory()->create(['title' => 'Chiamare Acme']);

    // A recipient who cannot open the task: the access hint gets its own line too.
    $mail = noteMentionMail('tasks', $task, layoutRecipient());

    expect($mail->introLines)->toHaveCount(3)
        ->and($mail->introLines[0])->toEndWith('ti ha menzionato:')
        ->and($mail->introLines[0])->not->toContain('Etichetta')
        ->and($mail->introLines[1])->toBe('“Ciao”')
        ->and($mail->introLines[2])->toStartWith('Non puoi aprire')
        ->and(mailRows($mail)['Titolo'])->toBe('Chiamare Acme');
});

// ---------------------------------------------------------------------------
// AC-010 — the bell does not change
// ---------------------------------------------------------------------------

it('keeps the bell payload to title, message, level and link (AC-010)', function () {
    $task = Task::factory()->create();

    $payload = (new TaskClosed($task, User::factory()->create()))->toArray(layoutRecipient(['tasks.viewAny']));

    expect($payload)->toHaveKeys(['title', 'message', 'level', 'action_url'])
        ->not->toHaveKey('details');
});

// ---------------------------------------------------------------------------
// AC-011 — the recipient's locale
// ---------------------------------------------------------------------------

it('writes labels and button in the recipient language (AC-011)', function () {
    $mail = registryAssignmentMail(companyRegistry(), layoutRecipient(['registries.view'], 'en'));

    expect($mail->actionText)->toBe('Open registry')
        ->and(mailRows($mail))->toHaveKeys(['Name', 'Type', 'VAT number', 'Tax code', 'SDI code'])
        ->and(mailRows($mail)['Type'])->toBe('Client');
});

// ---------------------------------------------------------------------------
// AC-013 / AC-014 — one layout, readable text version
// ---------------------------------------------------------------------------

it('renders notifications in the same card as the welcome email (AC-013)', function () {
    $notification = (string) registryAssignmentMail(companyRegistry(), layoutRecipient(['registries.view']))->render();
    $welcome = (string) (new WelcomeUserNotification('Segreta-123'))->toMail(layoutRecipient())->render();

    foreach ([$notification, $welcome] as $html) {
        expect($html)->toContain('class="accent-bar"')
            ->toContain('class="header"')
            ->toContain('class="body"')
            ->toContain('class="footer"')
            ->not->toContain('inner-body')
            ->not->toContain('content-cell');
    }
});

it('writes a plain text version without markup (AC-014)', function () {
    $registry = companyRegistry();
    $text = view('emails.notification-plain', registryAssignmentMail($registry, layoutRecipient(['registries.view']))->data())->render();

    expect($text)->toContain('Ciao Mario Rossi,')
        ->toContain('Partita IVA: 01234567890')
        ->toContain('Apri anagrafica: '.rtrim((string) config('app.frontend_url'), '/')."/registries/{$registry->id}")
        ->not->toContain('<')
        ->not->toContain('|')
        // The link closes the text, after the card, like the HTML button.
        ->and(strpos($text, 'Partita IVA'))->toBeLessThan(strpos($text, 'Apri anagrafica:'));

    // Plain text is not HTML: an apostrophe stays an apostrophe.
    $noAccess = view('emails.notification-plain', registryAssignmentMail($registry, layoutRecipient())->data())->render();

    expect($noAccess)->toContain("all'accesso")
        ->not->toContain('&#039;');
});

// ---------------------------------------------------------------------------
// AC-012 — no lazy loading while building the cards
// ---------------------------------------------------------------------------

it('builds every card and sends the mail without lazy loading (AC-012)', function () {
    $records = [
        'registries' => companyRegistry(),
        'referents' => Referent::factory()->create(),
        'proforma-requests' => ProformaRequest::factory()->create(),
        'purchase-requests' => PurchaseRequest::factory()->create(),
        'work-orders' => WorkOrder::factory()->create(),
        'tasks' => Task::factory()->create(),
    ];
    $quote = Quote::factory()->create();
    $recipient = layoutRecipient();

    // The flag is static: restore it, or every later test in this process
    // inherits it.
    $previous = Model::preventsLazyLoading();
    Model::preventLazyLoading();

    try {
        foreach ($records as $entityType => $record) {
            expect(mailRows(noteMentionMail($entityType, $record->fresh(), $recipient)))->not->toBeEmpty();
        }

        expect(RecordDetails::for($quote->fresh()))->not->toBeEmpty();
    } finally {
        Model::preventLazyLoading($previous);
    }
});
