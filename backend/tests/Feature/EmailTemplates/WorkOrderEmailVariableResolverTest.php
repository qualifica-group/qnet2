<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\OutboundEmails\WorkOrderEmailVariableResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
|--------------------------------------------------------------------------
| App\Services\OutboundEmails\WorkOrderEmailVariableResolver (spec 0175,
| D-4, AC-005 — service level: BE-05 wires the render-template endpoint)
|--------------------------------------------------------------------------
*/

uses(RefreshDatabase::class);

if (! function_exists('workOrderWithQuoteClient')) {
    function workOrderWithQuoteClient(string $clientName): WorkOrder
    {
        $registry = Registry::factory()->create(['name' => $clientName]);
        $opportunity = Opportunity::factory()->for($registry)->create();
        $quote = Quote::factory()->for($opportunity)->create();

        return WorkOrder::factory()->for($quote)->create(['title' => 'Manutenzione ascensore']);
    }
}

it('AC-005: resolves work_order/sender/delegated-quote tokens, {foo.bar} empty, subject plain/body escaped', function () {
    $workOrder = workOrderWithQuoteClient('Cliente <b>Rossi</b> S.r.l.');
    $sender = User::factory()->create(['name' => 'Mario Rossi', 'email' => 'mario.rossi@example.com']);

    $result = app(WorkOrderEmailVariableResolver::class)->render(
        subject: 'Commessa {work_order.code} per {client.name} — {foo.bar}',
        body: '<p>Gentile {client.name}, la commessa {work_order.code} e assegnata a {sender.name} ({sender.email}). {foo.bar}</p>',
        workOrder: $workOrder,
        actor: $sender,
    );

    // work_order.* / sender.* resolved with the real record's values.
    expect($result['subject'])->toContain($workOrder->code)
        ->and($result['body'])->toContain($workOrder->code)
        ->and($result['body'])->toContain('Mario Rossi')
        ->and($result['body'])->toContain('mario.rossi@example.com');

    // {foo.bar} (unknown category) never leaves a `{...}` residue.
    expect($result['subject'])->not->toContain('{foo.bar}')
        ->and($result['body'])->not->toContain('{foo.bar}');

    // client.* delegated to the shared quote VariableResolver, and the body
    // HTML-escapes the substituted value (a client name carrying markup does
    // not become markup) — the subject stays plain text.
    expect($result['body'])->toContain('Cliente &lt;b&gt;Rossi&lt;/b&gt; S.r.l.')
        ->and($result['body'])->not->toContain('<b>Rossi</b>')
        ->and($result['subject'])->toContain('Cliente <b>Rossi</b> S.r.l.');
});

it('AC-005: a category outside the D-4 allow-list (e.g. totals, document) resolves to empty even though the shared resolver knows it', function () {
    $workOrder = workOrderWithQuoteClient('Cliente Test');
    $sender = User::factory()->create();

    $result = app(WorkOrderEmailVariableResolver::class)->render(
        subject: 'Totale: {totals.revenue_net}',
        body: '<p>Generato il {document.generated_at}</p>',
        workOrder: $workOrder,
        actor: $sender,
    );

    expect($result['subject'])->toBe('Totale: ')
        ->and($result['body'])->toBe('<p>Generato il </p>');
});

it('strips newlines from a multi-line value in the subject but preserves them (escaped) in the body', function () {
    $workOrder = workOrderWithQuoteClient('Cliente Test');
    $workOrder->update(['description' => "Riga 1\nRiga 2"]);
    $sender = User::factory()->create();

    $result = app(WorkOrderEmailVariableResolver::class)->render(
        subject: 'Note: {work_order.description}',
        body: '<p>{work_order.description}</p>',
        workOrder: $workOrder->fresh(),
        actor: $sender,
    );

    expect($result['subject'])->toBe('Note: Riga 1 Riga 2')
        ->and($result['body'])->toBe("<p>Riga 1\nRiga 2</p>");
});
