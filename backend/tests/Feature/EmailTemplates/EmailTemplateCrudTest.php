<?php

use App\Enums\EmailTemplateModule;
use App\Models\EmailTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| `email-templates` CRUD (spec 0175, D-14, AC-002)
|--------------------------------------------------------------------------
*/

uses(RefreshDatabase::class);

if (! function_exists('emailTemplateStorePayload')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function emailTemplateStorePayload(array $overrides = []): array
    {
        return [...[
            'name' => 'Preventivo inviato',
            'module' => EmailTemplateModule::WorkOrders->value,
            'subject' => 'Il tuo preventivo {quote.code}',
            'body' => '<p>Gentile cliente, in allegato trova il preventivo.</p>',
            'description' => 'Descrizione',
            'is_active' => true,
        ], ...$overrides];
    }
}

// ---------------------------------------------------------------------------
// store / update / delete happy path
// ---------------------------------------------------------------------------

it('store: 201 with the submitted fields (AC-002)', function () {
    Sanctum::actingAs(emailTemplateUserWith(['create', 'view']));

    $this->postJson('/api/email-templates', emailTemplateStorePayload())
        ->assertCreated()
        ->assertJsonPath('data.name', 'Preventivo inviato')
        ->assertJsonPath('data.module', 'work_orders')
        ->assertJsonPath('data.subject', 'Il tuo preventivo {quote.code}')
        ->assertJsonPath('data.is_active', true);

    $this->assertDatabaseHas('email_templates', ['name' => 'Preventivo inviato', 'module' => 'work_orders']);
});

it('store: 403 without email-templates.create', function () {
    Sanctum::actingAs(emailTemplateUserWith([]));

    $this->postJson('/api/email-templates', emailTemplateStorePayload())->assertForbidden();
});

it('store: 422 on a name duplicated within the same module (AC-002)', function () {
    Sanctum::actingAs(emailTemplateUserWith(['create']));
    EmailTemplate::factory()->create(['name' => 'Gia esistente', 'module' => EmailTemplateModule::WorkOrders]);

    $this->postJson('/api/email-templates', emailTemplateStorePayload(['name' => 'Gia esistente']))
        ->assertStatus(422)->assertJsonValidationErrors('name');
});

it('update: 200, name/subject/description/is_active editable', function () {
    Sanctum::actingAs(emailTemplateUserWith(['update', 'view']));
    $emailTemplate = EmailTemplate::factory()->create();

    $this->patchJson("/api/email-templates/{$emailTemplate->id}", ['name' => 'Rinominato', 'is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.name', 'Rinominato')
        ->assertJsonPath('data.is_active', false);
});

it('update: 403 without email-templates.update', function () {
    Sanctum::actingAs(emailTemplateUserWith([]));
    $emailTemplate = EmailTemplate::factory()->create();

    $this->patchJson("/api/email-templates/{$emailTemplate->id}", ['name' => 'Rinominato'])->assertForbidden();
});

it('AC-002: module is prohibited on update — 422, nothing persisted', function () {
    Sanctum::actingAs(emailTemplateUserWith(['update']));
    $emailTemplate = EmailTemplate::factory()->create();

    $this->patchJson("/api/email-templates/{$emailTemplate->id}", ['module' => 'quotes'])
        ->assertStatus(422)->assertJsonValidationErrors('module');

    expect($emailTemplate->fresh()->module)->toBe(EmailTemplateModule::WorkOrders);
});

it('destroy: 204 with email-templates.delete', function () {
    Sanctum::actingAs(emailTemplateUserWith(['delete']));
    $emailTemplate = EmailTemplate::factory()->create();

    $this->deleteJson("/api/email-templates/{$emailTemplate->id}")->assertNoContent();

    $this->assertDatabaseMissing('email_templates', ['id' => $emailTemplate->id]);
});

it('destroy: 403 without email-templates.delete', function () {
    Sanctum::actingAs(emailTemplateUserWith([]));
    $emailTemplate = EmailTemplate::factory()->create();

    $this->deleteJson("/api/email-templates/{$emailTemplate->id}")->assertForbidden();
});

it('show: 404 for an unknown id', function () {
    Sanctum::actingAs(emailTemplateUserWith(['view']));

    $this->getJson('/api/email-templates/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// AC-002 — body sanitized: script and img removed (D-11)
// ---------------------------------------------------------------------------

it('AC-002: store strips a <script> tag from body', function () {
    Sanctum::actingAs(emailTemplateUserWith(['create']));

    $response = $this->postJson('/api/email-templates', emailTemplateStorePayload([
        'body' => '<p>Testo</p><script>alert(1)</script>',
    ]))->assertCreated();

    expect($response->json('data.body'))->not->toContain('<script')
        ->and($response->json('data.body'))->toContain('<p>Testo</p>');
});

it('AC-002/D-11: store removes every <img>, even a structurally valid one', function () {
    Sanctum::actingAs(emailTemplateUserWith(['create']));

    $response = $this->postJson('/api/email-templates', emailTemplateStorePayload([
        'body' => '<p>Testo</p><img data-attachment-id="1" alt="logo">',
    ]))->assertCreated();

    expect($response->json('data.body'))->not->toContain('<img');
});

it('AC-002/D-11: update re-sanitizes a resubmitted body', function () {
    Sanctum::actingAs(emailTemplateUserWith(['update']));
    $emailTemplate = EmailTemplate::factory()->create();

    $response = $this->patchJson("/api/email-templates/{$emailTemplate->id}", [
        'body' => '<p>Nuovo testo</p><script>alert(2)</script>',
    ])->assertOk();

    expect($response->json('data.body'))->not->toContain('<script');
});
