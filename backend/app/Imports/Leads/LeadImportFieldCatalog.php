<?php

namespace App\Imports\Leads;

/**
 * Static catalogue backing `LeadsImportDefinition::columns()/fields()/
 * globalConfig()` (spec 0033): the mappable Registry+Lead field ids and the
 * configuration-step global fields, kept off the main definition class to
 * stay under the 300-line soft limit (engineering.md §6).
 *
 * `label` values are i18n KEYS (`imports.leads.fields.*`/`imports.leads.
 * global.*`), not literal display strings — the frontend's dedicated
 * `features/imports` i18n bundle resolves them.
 */
final class LeadImportFieldCatalog
{
    /**
     * Mappable field catalogue: id => [group, type]. Field ids double as the
     * `resolved`/`mapped_values` keys LeadProfileBuilder/LeadDuplicateMatcher
     * read, and MUST match what NameSplitRecognizer (`first_name`/
     * `last_name`)/GeoRecognizer (`*_id`) produce.
     *
     * @var array<string, array{group: string, type: string}>
     */
    private const array FIELD_CATALOG = [
        'full_name' => ['group' => 'identity', 'type' => 'text'],
        'first_name' => ['group' => 'identity', 'type' => 'text'],
        'last_name' => ['group' => 'identity', 'type' => 'text'],
        'company_name' => ['group' => 'identity', 'type' => 'text'],
        'tax_code' => ['group' => 'identity', 'type' => 'text'],
        'vat_number' => ['group' => 'identity', 'type' => 'text'],
        'email' => ['group' => 'contact', 'type' => 'text'],
        'phone' => ['group' => 'contact', 'type' => 'text'],
        'mobile' => ['group' => 'contact', 'type' => 'text'],
        'street' => ['group' => 'address', 'type' => 'text'],
        'postal_code' => ['group' => 'address', 'type' => 'text'],
        'country' => ['group' => 'address', 'type' => 'text'],
        'region' => ['group' => 'address', 'type' => 'text'],
        'province' => ['group' => 'address', 'type' => 'text'],
        'city' => ['group' => 'address', 'type' => 'text'],
        'notes' => ['group' => 'lead', 'type' => 'textarea'],
        'campaign_code' => ['group' => 'lead', 'type' => 'text'],
    ];

    /**
     * Configuration-step global fields, applied to every imported row. A Lead
     * inherits its project via `campaign_id`, so the campaign alone is the
     * global scope — there is no standalone project field. Neither Operator
     * nor Operational Site is a global field: both are Review-only, per-row
     * overrides (spec 0045, extended to Operational Site).
     *
     * `product_ids` (spec 0094, D-4/AC-050) is the ONE multi-value global
     * field: the run's default "Prodotti di interesse", validated against
     * `campaign_id`'s effective categories (LeadImportProductCoherence) and
     * still overridable per row (`import_run_rows.product_ids`, review
     * grid) — never mapped from a file column.
     *
     * `campaign_id` (spec 0108, D-2) is required ONLY when no file column is
     * mapped to `campaign_code`: `required_unless_mapped` names that field, and
     * ConfigureImportRequest reads it off this catalogue instead of hardcoding
     * the pair. With the column mapped, the campaign is resolved per row by
     * CampaignRecognizer and a global value is rejected outright — the two
     * modes are mutually exclusive, never a silent fallback.
     *
     * @var array<int, array{id: string, required: bool, for_select_resource: string, multiple?: bool, depends_on?: string, required_unless_mapped?: string}>
     */
    private const array GLOBAL_FIELDS = [
        ['id' => 'campaign_id', 'required' => true, 'for_select_resource' => 'campaigns', 'required_unless_mapped' => 'campaign_code'],
        ['id' => 'source_id', 'required' => false, 'for_select_resource' => 'sources'],
        ['id' => 'product_ids', 'required' => false, 'for_select_resource' => 'products', 'multiple' => true, 'depends_on' => 'campaign_id'],
    ];

    /**
     * Fields StagedRowBuilder defaults to `config('imports.placeholder')`
     * when still blank after recognizers ran (spec 0033 delta
     * D-2026-07-15-placeholder-review-fields): a Registry's identity card
     * needs a first/last name — NameSplitRecognizer supplies these from
     * `full_name` when possible, the placeholder covers what it cannot.
     *
     * @var array<int, string>
     */
    private const array REQUIRED_FOR_CREATION = ['first_name', 'last_name'];

    /**
     * @return array<int, array{id: string, required: bool}>
     */
    public function columns(): array
    {
        // No single column is unconditionally required — a row's identity is
        // an OR of name/company/contact, enforced by LeadRowValidator.
        return array_map(
            static fn (string $id): array => ['id' => $id, 'required' => false],
            array_keys(self::FIELD_CATALOG),
        );
    }

    /**
     * @return array<int, array{id: string, label: string, required: bool, group: ?string, type: string}>
     */
    public function fields(): array
    {
        return array_map(
            static fn (string $id, array $meta): array => [
                'id' => $id,
                'label' => "imports.leads.fields.{$id}",
                'required' => false,
                'group' => $meta['group'],
                'type' => $meta['type'],
            ],
            array_keys(self::FIELD_CATALOG),
            self::FIELD_CATALOG,
        );
    }

    /**
     * @return array<int, array{id: string, label: string, required: bool, for_select_resource: ?string, multiple: bool, depends_on: ?string, default: mixed, required_unless_mapped: ?string}>
     */
    public function globalConfig(): array
    {
        return array_map(
            fn (array $field): array => [
                'id' => $field['id'],
                'label' => "imports.leads.global.{$field['id']}",
                'required' => $field['required'],
                'for_select_resource' => $field['for_select_resource'],
                'multiple' => $field['multiple'] ?? false,
                'depends_on' => $field['depends_on'] ?? null,
                'default' => null,
                'required_unless_mapped' => $field['required_unless_mapped'] ?? null,
            ],
            self::GLOBAL_FIELDS,
        );
    }

    /**
     * @return array<int, string>
     */
    public function requiredForCreation(): array
    {
        return self::REQUIRED_FOR_CREATION;
    }

    /**
     * The review grid's FINAL persisted fields: every mappable field EXCEPT
     * `full_name` — an input-only column, replaced by NameSplitRecognizer's
     * `first_name`/`last_name` output (+ the placeholder), never itself
     * persisted.
     *
     * @return array<int, array{id: string, label: string}>
     */
    public function reviewFields(): array
    {
        return array_values(array_filter(
            array_map(
                static fn (array $field): array => ['id' => $field['id'], 'label' => $field['label']],
                $this->fields(),
            ),
            static fn (array $field): bool => $field['id'] !== 'full_name',
        ));
    }
}
