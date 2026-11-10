<?php

use App\Enums\AssignmentRoleEnum;
use App\Enums\AssignmentTargetEnum;
use App\Enums\TransferRecipientRoleEnum;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use App\Notifications\RecordAssignmentNotification;
use App\Notifications\RequestTransferredNotification;
use App\Support\Notifications\DetailsTable;
use App\Support\Notifications\RecordDetails;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App as AppFacade;

// Direttiva utente 2026-08-04: la scheda dettagli del dominio nell'email, e
// l'email nella lingua dell'utente (italiano o inglese).

uses(RefreshDatabase::class);

if (! function_exists('renderedEmailBody')) {
    /**
     * The text of the email body, tags stripped, whitespace collapsed — what
     * the recipient actually reads.
     */
    function renderedEmailBody(object $notification, User $recipient): string
    {
        AppFacade::setLocale($recipient->locale ?? 'en');

        $html = $notification->toMail($recipient)->render();
        $html = (string) preg_replace('/<style.*?<\/style>/s', '', $html);

        return (string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html)));
    }
}

// ---------------------------------------------------------------------------
// La scheda dettagli
// ---------------------------------------------------------------------------

it('builds the anagrafica detail card from the record, skipping empty fields', function () {
    $supervisor = User::factory()->create(['name' => 'Rosa Falzarano']);
    $manager = User::factory()->create(['name' => 'Ada Lovelace']);
    $registry = Registry::factory()->create([
        'name' => 'Acme S.p.A.',
        'supervisor_id' => $supervisor->id,
        'source_id' => null,
        'is_supplier' => false,
    ]);
    $registry->managers()->sync([$manager->id => ['position' => 2]]);

    $details = RecordDetails::for($registry->fresh());

    expect($details)->toHaveKey('notifications.fields.name')
        ->and($details['notifications.fields.name'])->toBe('Acme S.p.A.')
        ->and($details['notifications.fields.supervisor'])->toBe('Rosa Falzarano')
        ->and($details['notifications.fields.account_managers'])->toBe('2. Ada Lovelace')
        // The value is the i18n KEY: it is translated per recipient, later.
        ->and($details['notifications.fields.type'])->toBe('notifications.values.client')
        // No source on this registry: the row is omitted, not left blank.
        ->and($details)->not->toHaveKey('notifications.fields.source');
});

it('builds the opportunity detail card from the record', function () {
    $registry = Registry::factory()->create(['name' => 'Acme S.p.A.']);
    $operator = User::factory()->create(['name' => 'Mario Rossi']);
    $opportunity = Opportunity::factory()->create([
        'name' => 'OPP_1',
        'registry_id' => $registry->id,
    ]);
    $opportunity->managers()->attach($operator->id, ['position' => Opportunity::OPERATOR_MANAGER_POSITION]);

    $details = RecordDetails::for($opportunity->fresh());

    expect($details['notifications.fields.title'])->toBe('OPP_1')
        ->and($details['notifications.fields.client'])->toBe('Acme S.p.A.')
        // REQUIREMENT CHANGED: spec 0332 — the Fonte is shown on the offer card only.
        ->and($details)->not->toHaveKey('notifications.fields.source')
        ->and($details['notifications.fields.operator'])->toBe('Mario Rossi');
});

it('builds the offer detail card with its own Fonte', function () {
    $source = Source::factory()->create(['name' => 'Fiera 2026']);
    $quote = Quote::factory()->create(['source_id' => $source->id]);

    $details = RecordDetails::for($quote->fresh());

    expect($details['notifications.fields.source'])->toBe('Fiera 2026');
});

// REQUIREMENT CHANGED (spec 0381, D-1): the card is no longer a markdown
// table inside a MailMessage line but rows the mail views print themselves.
it('turns the detail card into translated rows, verbatim values included', function () {
    AppFacade::setLocale('it');

    expect(DetailsTable::rows([
        'notifications.fields.name' => 'Acme | Beta',
        'notifications.fields.type' => 'notifications.values.supplier',
    ]))->toBe([
        ['label' => 'Denominazione', 'value' => 'Acme | Beta'],
        ['label' => 'Tipo', 'value' => 'Fornitore'],
    ]);
});

it('has no rows when there is nothing to show', function () {
    expect(DetailsTable::rows([]))->toBe([]);
});

it('puts a real HTML table in the assignment email', function () {
    $recipient = User::factory()->create(['locale' => 'en']);
    $registry = Registry::factory()->create(['name' => 'Acme S.p.A.']);

    // render() returns an HtmlString, not a string.
    $html = (string) (new RecordAssignmentNotification(
        target: AssignmentTargetEnum::Registry,
        role: AssignmentRoleEnum::Manager,
        recordId: $registry->id,
        recordLabel: $registry->name,
        position: 2,
        actorName: 'Rosa Falzarano',
        details: RecordDetails::for($registry),
    ))->toMail($recipient)->render();

    expect($html)->toContain('<table')
        ->and($html)->toContain('Acme S.p.A.')
        // The pipe syntax must NOT survive as literal text.
        ->and(str_contains(strip_tags($html), '| :--- |'))->toBeFalse();
});

// ---------------------------------------------------------------------------
// La lingua dell'utente
// ---------------------------------------------------------------------------

it('writes the same transfer email in Italian or in English, per recipient locale', function () {
    $notification = new RequestTransferredNotification(
        requestId: 7,
        contactLabel: 'Acme deal',
        originSiteLabel: 'Via Roma 1 - Napoli',
        destinationSiteLabel: 'Via Milano 2 - Milano',
        previousOperatorName: 'Old Operator',
        newOperatorName: 'New Operator',
        actorName: 'Rosa Falzarano',
        transferredAt: now(),
        recipientRole: TransferRecipientRoleEnum::PreviousOperator,
    );

    $italian = renderedEmailBody($notification, User::factory()->create(['name' => 'Mario', 'locale' => 'it']));
    $english = renderedEmailBody($notification, User::factory()->create(['name' => 'Mario', 'locale' => 'en']));

    expect($italian)->toContain('Ciao Mario')
        ->toContain('Non sei piu\' l\'operatore')
        // Etichette della scheda dettagli.
        ->toContain('Contatto')
        ->toContain('Sede di provenienza')
        ->toContain('Operatore precedente')
        // REQUIREMENT CHANGED (spec 0381, D-8): the shared layout's footer
        // replaces the framework salutation.
        ->toContain('Questo è un messaggio automatico');

    expect($english)->toContain('Hello Mario')
        ->toContain('no longer the operator')
        ->toContain('Contact')
        ->toContain('Origin site')
        ->toContain('Previous operator')
        ->toContain('This is an automated message');
});

it('translates the assignment email and its detail card per recipient locale', function () {
    $registry = Registry::factory()->create(['name' => 'Acme S.p.A.', 'is_supplier' => true]);

    $notification = new RecordAssignmentNotification(
        target: AssignmentTargetEnum::Registry,
        role: AssignmentRoleEnum::Supervisor,
        recordId: $registry->id,
        recordLabel: $registry->name,
        position: null,
        actorName: 'Rosa Falzarano',
        details: RecordDetails::for($registry),
    );

    $italian = renderedEmailBody($notification, User::factory()->create(['name' => 'Mario', 'locale' => 'it']));
    $english = renderedEmailBody($notification, User::factory()->create(['name' => 'Mario', 'locale' => 'en']));

    expect($italian)->toContain('inserito come Supervisore')
        ->toContain('Denominazione')
        // Anche il VALORE traducibile segue il destinatario, non chi ha scritto.
        ->toContain('Fornitore');

    expect($english)->toContain('assigned you as Supervisor')
        ->toContain('Name')
        ->toContain('Supplier');
});
