<?php

namespace Database\Seeders;

use App\Enums\EmailTemplateModule;
use App\Models\Attribute;
use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

/**
 * The legacy "Verifica Ispettiva Programmata" alert on the ISO work orders
 * (user directive 2026-11-11, spec 0390): in the legacy QNet, adding an
 * inspection row with "Invio alert" ticked mailed the work order's
 * Responsabili and Partecipanti, and the accounting mailbox. Here the same
 * behaviour is plain configuration of the generic table row event: an
 * "Modello email" with the legacy text, and `config.notify` on the
 * `orderiso_verificas` attribute (trigger = the "Avviso" column).
 *
 * The accounting manager of the legacy business units is one mailbox, whose
 * qnet-2 account is deactivated: it is a free address, so it keeps getting
 * the email.
 *
 * `php artisan db:seed --class=QualificaInspectionAlertSeeder`. A re-run
 * converges: same template, same notification, the columns untouched.
 */
class QualificaInspectionAlertSeeder extends Seeder
{
    public const string ATTRIBUTE_CODE = 'orderiso_verificas';

    public const string TRIGGER_COLUMN = 'invio_alert';

    public const string TEMPLATE_NAME = 'Verifica Ispettiva Programmata';

    public const string TEMPLATE_SUBJECT = 'Verifica Ispettiva Programmata';

    public const string TEMPLATE_BODY = '<p>Gentile responsabile,</p>'
        .'<p>è stata programmata la verifica ispettiva per {client.name}</p>'
        .'<p>Per la commessa in lavorazione n. {work_order.code}</p>'
        .'<p>{work_order.title}</p>';

    public const string TEMPLATE_DESCRIPTION = 'Inviata quando si attiva Avviso su una riga delle Verifiche Ispettive.';

    /** @var array<int, string> */
    public const array EXTRA_EMAILS = ['contabilita@qualificagroup.it'];

    public function run(): void
    {
        // Step 1: the attribute the legacy migration created
        $attribute = Attribute::query()->where('code', self::ATTRIBUTE_CODE)->first();

        if ($attribute === null) {
            $this->command?->warn(sprintf('Attribute %s not found: run the attributes migration first.', self::ATTRIBUTE_CODE));

            return;
        }

        // Step 2: the email the legacy sent
        $template = EmailTemplate::query()->updateOrCreate(
            ['name' => self::TEMPLATE_NAME, 'module' => EmailTemplateModule::WorkOrders],
            [
                'subject' => self::TEMPLATE_SUBJECT,
                'body' => self::TEMPLATE_BODY,
                'description' => self::TEMPLATE_DESCRIPTION,
                'is_active' => true,
            ],
        );

        // Step 3: the row event on the field, the columns left as they are
        $attribute->update(['config' => [
            ...($attribute->config ?? []),
            'notify' => [
                'trigger' => self::TRIGGER_COLUMN,
                'email_template_id' => $template->id,
                'record_team' => true,
                'user_ids' => [],
                'role_ids' => [],
                'emails' => self::EXTRA_EMAILS,
            ],
        ]]);
    }
}
