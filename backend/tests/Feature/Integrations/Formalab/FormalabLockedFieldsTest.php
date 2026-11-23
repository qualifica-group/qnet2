<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Integrations\Formalab\Models\FormalabWorkOrderLink;
use Integrations\Formalab\Services\ClassCatalogSync;
use Integrations\Formalab\Support\Trigger;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/** The 11 mapped fields Formalab owns: every suggested one but the course name. */
function formalabLockedCodes(): array
{
    return collect(formalabSuggestedMap())->pluck('attribute_code')->reject(fn (string $code): bool => $code === 'gol_corso')->values()->all();
}

// AC-010
it('shows the mapped fields of a linked category as locked, the course name excepted', function () {
    formalabEnable();
    $workOrder = formalabGolWorkOrder(['gol_corso' => 5]);
    $plain = formalabGolWorkOrder(linkCategory: false);
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));

    // REQUIREMENT CHANGED (2026-11-23): each locked field carries its source, shown beside it
    $locked = $this->getJson('/api/work-orders/'.$workOrder->id)->assertOk()->json('data.locked_attributes');
    expect(array_column($locked, 'code'))->toEqualCanonicalizing(formalabLockedCodes())->not->toContain('gol_corso')->not->toContain('gol_notice')
        ->and(array_unique(array_column($locked, 'source')))->toBe(['Formalab']);

    $this->getJson('/api/work-orders/'.$plain->id)->assertOk()->assertJsonPath('data.locked_attributes', []);
});

// AC-011 — REQUIREMENT CHANGED (spec 0426): the course name is the class select (an edition of the catalogue)
it('refuses a user change to a locked field and accepts the class select, other fields and an unchanged resend', function () {
    formalabEnable();
    formalabFakeApi([formalabClassPayload(5), formalabClassPayload(6)]);
    app(ClassCatalogSync::class)->handle(Trigger::Manual);
    $workOrder = formalabGolWorkOrder(['gol_corso' => 5]);
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));
    $url = '/api/work-orders/'.$workOrder->id;

    $this->patchJson($url, ['attribute_values' => ['gol_stato_corso' => 'Concluso']])
        ->assertUnprocessable()->assertJsonValidationErrors('attribute_values.gol_stato_corso');
    $this->patchJson($url, ['attribute_values' => ['gol_data_esame' => '2026-07-10']])
        ->assertUnprocessable()->assertJsonValidationErrors('attribute_values.gol_data_esame');
    $this->patchJson($url, ['attribute_values' => ['gol_corso' => 99]])
        ->assertUnprocessable()->assertJsonValidationErrors('attribute_values.gol_corso');
    $this->patchJson($url, ['attribute_values' => ['gol_corso' => 'COD-6 - Course 6']])
        ->assertUnprocessable()->assertJsonValidationErrors('attribute_values.gol_corso');

    $this->patchJson($url, ['attribute_values' => [
        'gol_corso' => 6, 'gol_notice' => 'free', 'gol_stato_corso' => 'In corso', 'gol_ore_totali_corso' => '300', 'gol_data_esame' => '',
    ]])->assertOk();

    $values = $workOrder->refresh()->attribute_values;
    expect($values['gol_corso'])->toBe(6)->and($values['gol_notice'])->toBe('free')
        ->and($values['gol_stato_corso'])->toBe('In corso')
        ->and(FormalabWorkOrderLink::query()->where('work_order_id', $workOrder->id)->value('edition_id'))->toBe(6);
});

it('locks the mapped fields on create and in the form context', function () {
    formalabEnable();
    $workOrder = formalabGolWorkOrder();
    $line = $workOrder->quoteLines->first();
    $actor = workOrderUserWith(['view', 'create', 'update']);
    Sanctum::actingAs($actor);

    $context = $this->postJson('/api/work-orders/form-context', ['quote_line_ids' => [$line->id]])->assertOk();
    expect(array_column($context->json('data.locked_attributes'), 'code'))->toEqualCanonicalizing(formalabLockedCodes());

    $this->postJson('/api/work-orders', [
        ...workOrderRequiredFields(), 'quote_id' => $line->quote_id, 'title' => 'New', 'type' => 'processing',
        'quote_line_ids' => [$line->id], 'attribute_values' => ['gol_stato_classe' => 'Avviata'],
    ])->assertUnprocessable()->assertJsonValidationErrors('attribute_values.gol_stato_classe');
});

it('locks nothing while the integration is off', function () {
    $workOrder = formalabGolWorkOrder(['gol_stato_corso' => 'In corso']);
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));

    $this->patchJson('/api/work-orders/'.$workOrder->id, ['attribute_values' => ['gol_stato_corso' => 'Concluso']])->assertOk()
        ->assertJsonPath('data.locked_attributes', []);
});
