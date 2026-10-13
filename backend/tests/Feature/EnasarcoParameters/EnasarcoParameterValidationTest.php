<?php

use App\Models\EnasarcoParameter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('AC-015: requires every value', function () {
    Sanctum::actingAs(enasarcoParameterUserWith(['create']));

    $this->postJson('/api/enasarco-parameters', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['year', 'total_rate', 'agent_share_percent', 'single_firm_ceiling', 'multi_firm_ceiling']);
});

it('AC-015: a duplicated year is a 422 on year, on create and on update to another row year', function () {
    Sanctum::actingAs(enasarcoParameterUserWith(['create', 'update']));
    EnasarcoParameter::factory()->create(['year' => 2026]);
    $other = EnasarcoParameter::factory()->create(['year' => 2025]);

    $this->postJson('/api/enasarco-parameters', enasarcoParameterPayload(['year' => 2026]))
        ->assertUnprocessable()->assertJsonValidationErrors('year');

    $this->putJson("/api/enasarco-parameters/{$other->id}", enasarcoParameterPayload(['year' => 2026]))
        ->assertUnprocessable()->assertJsonValidationErrors('year');

    expect($other->fresh()->year)->toBe(2025);
});

it('AC-015: the year must lie between 2000 and 2100', function (mixed $year) {
    Sanctum::actingAs(enasarcoParameterUserWith(['create']));

    $this->postJson('/api/enasarco-parameters', enasarcoParameterPayload(['year' => $year]))
        ->assertUnprocessable()->assertJsonValidationErrors('year');
})->with([1999, 2101, 'abc', 2026.5]);

it('AC-015: the percentages must be between 0 and 100 with at most two decimals', function (string $field, mixed $value) {
    Sanctum::actingAs(enasarcoParameterUserWith(['create']));

    $this->postJson('/api/enasarco-parameters', enasarcoParameterPayload([$field => $value]))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    ['total_rate', '100.01'],
    ['total_rate', '-0.01'],
    ['total_rate', '17.123'],
    ['agent_share_percent', '101'],
    ['agent_share_percent', '-1'],
    ['agent_share_percent', 'abc'],
]);

it('AC-015: the ceilings must be non-negative with at most two decimals', function (string $field, mixed $value) {
    Sanctum::actingAs(enasarcoParameterUserWith(['create']));

    $this->postJson('/api/enasarco-parameters', enasarcoParameterPayload([$field => $value]))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    ['single_firm_ceiling', '-1'],
    ['single_firm_ceiling', '10.123'],
    ['multi_firm_ceiling', '-0.01'],
    ['multi_firm_ceiling', 'abc'],
]);

it('AC-015: accepts the boundary values 0 and 100 and a zero ceiling', function () {
    Sanctum::actingAs(enasarcoParameterUserWith(['create']));

    $this->postJson('/api/enasarco-parameters', enasarcoParameterPayload([
        'total_rate' => '0', 'agent_share_percent' => '100', 'single_firm_ceiling' => '0', 'multi_firm_ceiling' => '0.00',
    ]))->assertCreated();
});

it('AC-015: the notes are capped at 2000 characters', function () {
    Sanctum::actingAs(enasarcoParameterUserWith(['create']));

    $this->postJson('/api/enasarco-parameters', enasarcoParameterPayload(['notes' => str_repeat('a', 2001)]))
        ->assertUnprocessable()->assertJsonValidationErrors('notes');
});
