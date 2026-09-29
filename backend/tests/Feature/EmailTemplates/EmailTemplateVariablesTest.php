<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| GET /api/email-templates/variables (spec 0175, D-4, AC-003)
|--------------------------------------------------------------------------
*/

uses(RefreshDatabase::class);

it('requires authentication (401)', function () {
    $this->getJson('/api/email-templates/variables?module=work_orders')->assertUnauthorized();
});

it('403 without email-templates.view', function () {
    Sanctum::actingAs(emailTemplateUserWith([]));

    $this->getJson('/api/email-templates/variables?module=work_orders')->assertForbidden();
});

it('422 without `module`', function () {
    Sanctum::actingAs(emailTemplateUserWith(['view']));

    $this->getJson('/api/email-templates/variables')
        ->assertStatus(422)->assertJsonValidationErrors('module');
});

it('422 when `module` is out of the enum', function () {
    Sanctum::actingAs(emailTemplateUserWith(['view']));

    $this->getJson('/api/email-templates/variables?module=quotes')
        ->assertStatus(422)->assertJsonValidationErrors('module');
});

it('AC-003: exposes work_order, sender and the reused quote categories, NOT totals/document/custom_fields/quote_attributes/operational_site', function () {
    Sanctum::actingAs(emailTemplateUserWith(['view']));

    $response = $this->getJson('/api/email-templates/variables?module=work_orders')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['success', 'message', 'data' => [['key', 'label', 'variables']]]);

    $categoryKeys = collect($response->json('data'))->pluck('key')->all();

    expect($categoryKeys)->toBe([
        'work_order', 'sender', 'quote', 'client', 'opportunity', 'referent',
        'commercial', 'reporter', 'supervisor', 'company', 'company_site',
    ])->and($categoryKeys)->not->toContain('totals')
        ->and($categoryKeys)->not->toContain('document')
        ->and($categoryKeys)->not->toContain('custom_fields')
        ->and($categoryKeys)->not->toContain('quote_attributes')
        ->and($categoryKeys)->not->toContain('operational_site');

    $workOrderCategory = collect($response->json('data'))->firstWhere('key', 'work_order');
    $tokens = collect($workOrderCategory['variables'])->pluck('variable')->all();
    expect($tokens)->toBe([
        '{work_order.code}', '{work_order.title}', '{work_order.type}',
        '{work_order.start_date}', '{work_order.callback_date}', '{work_order.description}',
    ]);

    foreach ($workOrderCategory['variables'] as $variable) {
        expect($variable['label'])->not->toBeEmpty()
            ->and($variable['type'])->not->toBeEmpty()
            ->and($variable['example'])->not->toBeEmpty();
    }

    $senderCategory = collect($response->json('data'))->firstWhere('key', 'sender');
    expect(collect($senderCategory['variables'])->pluck('variable')->all())->toBe(['{sender.name}', '{sender.email}']);
});
