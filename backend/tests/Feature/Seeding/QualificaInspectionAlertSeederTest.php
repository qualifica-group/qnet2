<?php

declare(strict_types=1);

use App\CustomFields\Table\TableFieldConfigValidator;
use App\Models\Attribute;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Notifications\TableRowEmailRenderer;
use Database\Seeders\QualificaInspectionAlertSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Spec 0390: the legacy "Verifica Ispettiva Programmata" alert as configuration.
uses(RefreshDatabase::class);

/**
 * The `orderiso_verificas` attribute as the legacy migration creates it.
 */
function legacyInspectionAttribute(): Attribute
{
    return Attribute::factory()->ofType('table')->create([
        'code' => QualificaInspectionAlertSeeder::ATTRIBUTE_CODE,
        'name' => 'Verifiche Ispettive',
        'config' => [
            'columns' => [
                ['key' => 'data_verifica', 'type' => 'date', 'label' => 'Data Verifica I.'],
                ['key' => 'ispettore_verifica', 'type' => 'text', 'label' => 'Ispettore', 'required' => true],
                ['key' => 'invio_alert', 'type' => 'boolean', 'label' => 'Avviso'],
            ],
            'summary' => ['column' => 'data_verifica', 'strategy' => 'selected'],
            'selectable' => ['key' => 'attivo', 'label' => 'Attivo'],
        ],
    ]);
}

it('creates the legacy email template and turns on the row event of the inspections field', function (): void {
    $attribute = legacyInspectionAttribute();

    $this->seed(QualificaInspectionAlertSeeder::class);

    $template = EmailTemplate::query()->where('name', QualificaInspectionAlertSeeder::TEMPLATE_NAME)->sole();
    $config = $attribute->fresh()->config;

    expect($template->subject)->toBe('Verifica Ispettiva Programmata')
        ->and($template->is_active)->toBeTrue()
        ->and($config['columns'])->toHaveCount(3)
        ->and($config['selectable']['key'])->toBe('attivo')
        ->and($config['notify'])->toBe([
            'trigger' => 'invio_alert',
            'email_template_id' => $template->id,
            'record_team' => true,
            'user_ids' => [],
            'role_ids' => [],
            'emails' => ['contabilita@qualificagroup.it'],
        ])
        ->and((new TableFieldConfigValidator)->validate($config))->toBe([]);
});

it('converges on a re-run', function (): void {
    $attribute = legacyInspectionAttribute();

    $this->seed(QualificaInspectionAlertSeeder::class);
    $this->seed(QualificaInspectionAlertSeeder::class);

    expect(EmailTemplate::query()->count())->toBe(1)
        ->and($attribute->fresh()->config['notify']['email_template_id'])->toBe(EmailTemplate::query()->sole()->id);
});

it('does nothing without the migrated attribute', function (): void {
    $this->seed(QualificaInspectionAlertSeeder::class);

    expect(EmailTemplate::query()->count())->toBe(0);
});

it('renders the legacy text against the work order', function (): void {
    legacyInspectionAttribute();
    $this->seed(QualificaInspectionAlertSeeder::class);
    $workOrder = WorkOrder::factory()->create(['title' => 'Certificazione ISO 9001']);

    $email = app(TableRowEmailRenderer::class)->render(EmailTemplate::query()->sole(), $workOrder, User::factory()->create());

    expect($email['subject'])->toBe('Verifica Ispettiva Programmata')
        ->and($email['body'])->toContain("Per la commessa in lavorazione n. {$workOrder->code}")
        ->and($email['body'])->toContain('Certificazione ISO 9001')
        ->and($email['body'])->toContain('è stata programmata la verifica ispettiva per')
        ->and($email['body'])->not->toContain('{client.name}');
});
