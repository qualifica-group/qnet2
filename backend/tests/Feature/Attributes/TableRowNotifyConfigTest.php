<?php

declare(strict_types=1);

use App\Models\Attribute;
use App\Models\EmailTemplate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0390 AC-001/AC-002: `config.notify` of a table attribute / custom field.
uses(RefreshDatabase::class);

function notifyConfigAdmin(): User
{
    foreach (['attributes.create', 'custom-fields.create'] as $permission) {
        Permission::findOrCreate($permission);
    }

    return tap(User::factory()->create())->givePermissionTo(['attributes.create', 'custom-fields.create']);
}

/**
 * @param  array<string, mixed>  $notify
 * @return array<string, mixed>
 */
function notifyAttributePayload(array $notify): array
{
    return ['code' => 'inspections', 'name' => 'Inspections', 'type' => 'table', 'config' => notifyingInspectionConfig($notify)];
}

it('AC-001: persists a valid notification on a table attribute and on a table custom field', function (): void {
    Sanctum::actingAs(notifyConfigAdmin());
    $user = User::factory()->create();
    $role = Role::findOrCreate('accounting');
    $template = EmailTemplate::factory()->create();
    $notify = [
        'email_template_id' => $template->id,
        'record_team' => true,
        'user_ids' => [$user->id],
        'role_ids' => [$role->id],
        'emails' => ['audit@example.com'],
    ];

    $this->postJson('/api/attributes', notifyAttributePayload($notify))->assertCreated();
    $this->postJson('/api/custom-fields', [
        'entity_type' => 'companies', 'key' => 'inspections', 'type' => 'table', 'label' => 'Inspections',
        'config' => notifyingInspectionConfig($notify),
    ])->assertCreated();

    expect(Attribute::query()->where('code', 'inspections')->first()->config['notify'])
        ->toBe(notifyingInspectionConfig($notify)['notify']);
});

it('AC-001: the row selection is a valid trigger, and no trigger means every added row', function (?string $trigger): void {
    Sanctum::actingAs(notifyConfigAdmin());

    $this->postJson('/api/attributes', notifyAttributePayload(['trigger' => $trigger]))->assertCreated();
})->with(['row selection' => 'active', 'every added row' => null]);

dataset('invalid notifications', [
    'trigger on a text column' => [['trigger' => 'inspector'], 'config.notify.trigger'],
    'trigger on a missing column' => [['trigger' => 'nope'], 'config.notify.trigger'],
    'no recipient' => [['record_team' => false], 'config.notify'],
    'unknown user' => [['user_ids' => [999999]], 'config.notify.user_ids'],
    'duplicate users' => [['user_ids' => [1, 1]], 'config.notify.user_ids'],
    'too many users' => [['user_ids' => range(1, 51)], 'config.notify.user_ids'],
    'unknown role' => [['role_ids' => [999999]], 'config.notify.role_ids'],
    'invalid email' => [['emails' => ['not-an-email']], 'config.notify.emails'],
    'duplicate emails' => [['emails' => ['a@example.com', 'A@example.com']], 'config.notify.emails'],
    'unknown template' => [['email_template_id' => 999999], 'config.notify.email_template_id'],
    'record team not boolean' => [['record_team' => 'yes'], 'config.notify.record_team'],
]);

it('AC-002: rejects an invalid notification', function (array $notify, string $errorKey): void {
    Sanctum::actingAs(notifyConfigAdmin());

    $this->postJson('/api/attributes', notifyAttributePayload($notify))
        ->assertStatus(422)
        ->assertJsonValidationErrors([$errorKey]);

    expect(Attribute::query()->count())->toBe(0);
})->with('invalid notifications');
