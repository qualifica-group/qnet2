<?php

use App\Enums\EmailTemplateModule;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| GET /api/email-templates/for-select (spec 0175, ADR 0011)
|--------------------------------------------------------------------------
*/

uses(RefreshDatabase::class);

it('requires authentication (401)', function () {
    $this->getJson('/api/email-templates/for-select?module=work_orders')->assertUnauthorized();
});

it('422 without the required `module`', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/email-templates/for-select')
        ->assertStatus(422)->assertJsonValidationErrors('module');
});

it('lists only is_active rows of the requested module, no permission gate beyond auth:sanctum', function () {
    // No email-templates.* permission granted at all (ADR 0011).
    Sanctum::actingAs(User::factory()->create());

    $active = EmailTemplate::factory()->create(['name' => 'Attivo', 'module' => EmailTemplateModule::WorkOrders, 'is_active' => true]);
    EmailTemplate::factory()->inactive()->create(['name' => 'Inattivo', 'module' => EmailTemplateModule::WorkOrders]);

    $response = $this->getJson('/api/email-templates/for-select?module=work_orders')->assertOk();

    $labels = collect($response->json('items'))->pluck('label');
    expect($labels)->toContain('Attivo')->not->toContain('Inattivo')
        ->and($response->json('items.0.id'))->toBe($active->id);
});

it('search narrows by name', function () {
    Sanctum::actingAs(User::factory()->create());
    EmailTemplate::factory()->create(['name' => 'Conferma appuntamento', 'module' => EmailTemplateModule::WorkOrders]);
    EmailTemplate::factory()->create(['name' => 'Chiusura commessa', 'module' => EmailTemplateModule::WorkOrders]);

    $response = $this->getJson('/api/email-templates/for-select?'.http_build_query([
        'module' => 'work_orders',
        'search' => 'Conferma',
    ]))->assertOk();

    expect(collect($response->json('items'))->pluck('label')->all())->toBe(['Conferma appuntamento']);
});
