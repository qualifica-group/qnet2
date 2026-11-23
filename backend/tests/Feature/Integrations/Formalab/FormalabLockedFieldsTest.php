<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
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
    $workOrder = formalabGolWorkOrder(['gol_corso' => 'COD-5 - Course 5']);
    $plain = formalabGolWorkOrder(linkCategory: false);
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));

    $locked = $this->getJson('/api/work-orders/'.$workOrder->id)->assertOk()->json('data.locked_attribute_codes');
    expect($locked)->toEqualCanonicalizing(formalabLockedCodes())->not->toContain('gol_corso')->not->toContain('gol_notice');

    $this->getJson('/api/work-orders/'.$plain->id)->assertOk()->assertJsonPath('data.locked_attribute_codes', []);
});

// AC-011
it('refuses a user change to a locked field and accepts the course name, other fields and an unchanged resend', function () {
    formalabEnable();
    $workOrder = formalabGolWorkOrder(['gol_corso' => 'COD-5 - Course 5', 'gol_stato_corso' => 'In corso', 'gol_ore_totali_corso' => 120]);
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));
    $url = '/api/work-orders/'.$workOrder->id;

    $this->patchJson($url, ['attribute_values' => ['gol_stato_corso' => 'Concluso']])
        ->assertUnprocessable()->assertJsonValidationErrors('attribute_values.gol_stato_corso');
    $this->patchJson($url, ['attribute_values' => ['gol_data_esame' => '2026-07-10']])
        ->assertUnprocessable()->assertJsonValidationErrors('attribute_values.gol_data_esame');

    $this->patchJson($url, ['attribute_values' => [
        'gol_corso' => 'COD-6 - Course 6', 'gol_notice' => 'free', 'gol_stato_corso' => 'In corso', 'gol_ore_totali_corso' => '120', 'gol_data_esame' => '',
    ]])->assertOk();

    $values = $workOrder->refresh()->attribute_values;
    expect($values['gol_corso'])->toBe('COD-6 - Course 6')->and($values['gol_notice'])->toBe('free')
        ->and($values['gol_stato_corso'])->toBe('In corso');
});

it('locks the mapped fields on create and in the form context', function () {
    formalabEnable();
    $workOrder = formalabGolWorkOrder();
    $line = $workOrder->quoteLines->first();
    $actor = workOrderUserWith(['view', 'create', 'update']);
    Sanctum::actingAs($actor);

    $context = $this->postJson('/api/work-orders/form-context', ['quote_line_ids' => [$line->id]])->assertOk();
    expect($context->json('data.locked_attribute_codes'))->toEqualCanonicalizing(formalabLockedCodes());

    $this->postJson('/api/work-orders', [
        ...workOrderRequiredFields(), 'quote_id' => $line->quote_id, 'title' => 'New', 'type' => 'processing',
        'quote_line_ids' => [$line->id], 'attribute_values' => ['gol_stato_classe' => 'Avviata'],
    ])->assertUnprocessable()->assertJsonValidationErrors('attribute_values.gol_stato_classe');
});

it('locks nothing while the integration is off', function () {
    $workOrder = formalabGolWorkOrder(['gol_stato_corso' => 'In corso']);
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));

    $this->patchJson('/api/work-orders/'.$workOrder->id, ['attribute_values' => ['gol_stato_corso' => 'Concluso']])->assertOk()
        ->assertJsonPath('data.locked_attribute_codes', []);
});
