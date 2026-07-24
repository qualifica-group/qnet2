<?php

namespace Database\Seeders;

use App\Models\CustomFieldDefinition;
use Illuminate\Database\Seeder;

/**
 * Clean, idempotent reference seed: provisions the per-module custom field
 * "template" as universal custom field definitions (spec 0021). One entry per
 * entity_type in TEMPLATES:
 *   - company-sites: the former flat "Altro" columns, PLUS the former
 *     client-specific ERP settings (responsible_*, proforma/invoice
 *     progressives, quotation_*), now dynamic fields;
 *   - products: the expiration date.
 *
 * Definitions only — no values are written (that is per-row user data).
 * `updateOrCreate` on (entity_type, key) keeps re-runs from duplicating.
 * Adding a module's template = one more entry in TEMPLATES.
 */
class QualificaTemplateSeeder extends Seeder
{
    /**
     * `accounting_manager_id` points at a single user (the former
     * `accounting_manager_id` FK). `users` is a registered custom-fieldable
     * entity, so it is a valid relation target + for-select resource.
     *
     * @var array<string, mixed>
     */
    private const array MANAGER_RELATION_TARGET = [
        'entity_type' => 'users',
        'cardinality' => 'one',
        'for_select_resource' => 'users',
    ];

    /**
     * entity_type => ordered list of field specs [key, label, type, ?relation_target].
     * Everything company-site is `integer` (former numeric reference/status
     * columns) except `color` (free text) and `accounting_manager_id` (a
     * one-to-one relation to a user). The product expiration is a `date`.
     *
     * @var array<string, list<array{key: string, label: string, type: string, relation_target?: array<string, mixed>}>>
     */
    private const array TEMPLATES = [
        'company-sites' => [
            ['key' => 'accounting_manager_id', 'label' => 'Responsabile amministrativo', 'type' => 'relation', 'relation_target' => self::MANAGER_RELATION_TARGET],
            ['key' => 'store_id', 'label' => 'Negozio', 'type' => 'integer'],
            ['key' => 'company_type', 'label' => 'Tipo società', 'type' => 'integer'],
            ['key' => 'commissions', 'label' => 'Commissioni', 'type' => 'integer'],
            ['key' => 'order_sites', 'label' => 'Ordine sedi', 'type' => 'integer'],
            ['key' => 'payment_status_assign_technician', 'label' => 'Stato pagamento (assegna tecnico)', 'type' => 'integer'],
            ['key' => 'payment_status_deposit', 'label' => 'Stato pagamento (acconto)', 'type' => 'integer'],
            ['key' => 'payment_status_balance', 'label' => 'Stato pagamento (saldo)', 'type' => 'integer'],
            ['key' => 'default_payment_id', 'label' => 'Pagamento predefinito', 'type' => 'integer'],
            ['key' => 'default_vat_id', 'label' => 'IVA predefinita', 'type' => 'integer'],
            ['key' => 'other_category_id', 'label' => 'Categoria altro', 'type' => 'integer'],
            ['key' => 'iso_category_id', 'label' => 'Categoria ISO', 'type' => 'integer'],
            ['key' => 'soa_category_id', 'label' => 'Categoria SOA', 'type' => 'integer'],
            ['key' => 'sic_category_id', 'label' => 'Categoria SIC', 'type' => 'integer'],
            ['key' => 'avv_category_id', 'label' => 'Categoria AVV', 'type' => 'integer'],
            ['key' => 'gdpr_category_id', 'label' => 'Categoria GDPR', 'type' => 'integer'],
            ['key' => 'res_category_id', 'label' => 'Categoria RES', 'type' => 'integer'],
            ['key' => 'pal_category_id', 'label' => 'Categoria PAL', 'type' => 'integer'],
            ['key' => 'quattro_category_id', 'label' => 'Categoria 4.0', 'type' => 'integer'],
            ['key' => 'finage_category_id', 'label' => 'Categoria Finage', 'type' => 'integer'],
            ['key' => 'fondi_category_id', 'label' => 'Categoria fondi', 'type' => 'integer'],
            ['key' => 'gare_category_id', 'label' => 'Categoria gare', 'type' => 'integer'],
            ['key' => 'partnership_category_id', 'label' => 'Categoria partnership', 'type' => 'integer'],
            ['key' => 'progetti_category_id', 'label' => 'Categoria progetti', 'type' => 'integer'],
            ['key' => 'status', 'label' => 'Stato', 'type' => 'integer'],
            ['key' => 'color', 'label' => 'Colore', 'type' => 'text'],
            ['key' => 'surface_sqm', 'label' => 'Superficie (mq)', 'type' => 'integer'],
            // De-verticalization: former `responsible_*_id` FKs and ERP
            // settings columns (proforma/invoice progressives, quotation_*),
            // now dynamic fields.
            ['key' => 'responsible_rda', 'label' => 'Responsabile RDA', 'type' => 'relation', 'relation_target' => self::MANAGER_RELATION_TARGET],
            ['key' => 'responsible_tickets', 'label' => 'Responsabile Ticket', 'type' => 'relation', 'relation_target' => self::MANAGER_RELATION_TARGET],
            ['key' => 'responsible_validation_contracts', 'label' => 'Responsabile Validazione contratti', 'type' => 'relation', 'relation_target' => self::MANAGER_RELATION_TARGET],
            ['key' => 'responsible_validation_contracts_two', 'label' => 'Responsabile Validazione contratti 2', 'type' => 'relation', 'relation_target' => self::MANAGER_RELATION_TARGET],
            ['key' => 'proforma_progressive', 'label' => 'Progressivo proforma', 'type' => 'integer'],
            ['key' => 'invoice_progressive', 'label' => 'Progressivo fattura', 'type' => 'integer'],
            ['key' => 'quotation_layout', 'label' => 'Layout preventivo', 'type' => 'integer'],
            ['key' => 'quotation_header', 'label' => 'Header preventivo', 'type' => 'integer'],
            ['key' => 'quotation_footer', 'label' => 'Footer preventivo', 'type' => 'integer'],
        ],
        'products' => [
            ['key' => 'expiration_date', 'label' => 'Data scadenza', 'type' => 'date'],
        ],
    ];

    public function run(): void
    {
        foreach (self::TEMPLATES as $entityType => $fields) {
            $this->seedTemplate($entityType, $fields);
        }
    }

    /**
     * @param  list<array{key: string, label: string, type: string, relation_target?: array<string, mixed>}>  $fields
     */
    private function seedTemplate(string $entityType, array $fields): void
    {
        $sortOrder = 0;

        foreach ($fields as $field) {
            CustomFieldDefinition::updateOrCreate(
                ['entity_type' => $entityType, 'key' => $field['key']],
                [
                    'type' => $field['type'],
                    'label' => $field['label'],
                    'sort_order' => $sortOrder++,
                    'is_indexed' => false,
                    'is_active' => true,
                    'relation_target' => $field['relation_target'] ?? null,
                ],
            );
        }
    }
}
