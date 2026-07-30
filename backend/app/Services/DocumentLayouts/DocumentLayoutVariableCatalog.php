<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts;

use App\Authorization\AuthorizationRegistry;
use App\Enums\DocumentLayoutModule;
use App\Models\User;

/**
 * The document-layouts variable catalogue (spec 0069): every `{category.key}`
 * token an editor's variable picker may offer, and a layout's `config` may
 * reference, per module. Two families of category:
 *  - STATIC (quote, totals, client, ...): a fixed list declared in
 *    quoteCategories() — one method per module (only `quotes` exists today,
 *    D-2/scope).
 *  - DYNAMIC (custom_fields, opportunity_attributes): built from live data
 *    (App\CustomFields\CustomFieldProvider / the Attribute catalogue), so a
 *    new CustomFieldDefinition/Attribute appears with zero code change
 *    (AC-042/043).
 *
 * D-6 (non-negotiable): `client.vat_number`/`client.tax_code`/`client.sdi_code`
 * are the ONLY tokens in this module's frozen category list sourced from a
 * `personal_data` scalar column (quote->opportunity->registry->personalData —
 * see spec `context`). Every other `client`/`referent`/`commercial`/`reporter`
 * token (name, email, phone, ...) is either a non-personal column
 * (`registries.name`) or a `contacts` row, neither of which this spec's
 * frozen catalogue exposes as PII — so masking only ever touches those three
 * keys. They are omitted when the actor's `registries` resource field
 * permissions hide the matching `personal_data.{field}` key
 * (App\Authorization\RegistriesAuthorization is `client`'s field-permission
 * source: the client IS a Registry).
 */
final class DocumentLayoutVariableCatalog
{
    private const string TYPE_STRING = 'string';

    private const string TYPE_DATE = 'date';

    private const string TYPE_CURRENCY = 'currency';

    /** The `totals` category's bare keys — module-independent today (only `quotes` exists). */
    private const array TOTALS_KEYS = [
        'revenue_net', 'revenue_vat', 'revenue_gross', 'cost_net', 'cost_vat', 'cost_gross', 'margin_net',
    ];

    /** The three `client.*` keys sourced from a `personal_data` column (D-6). */
    private const array CLIENT_PERSONAL_DATA_KEYS = ['vat_number', 'tax_code', 'sdi_code'];

    public function __construct(
        private readonly AuthorizationRegistry $authorizationRegistry,
        private readonly DocumentLayoutDynamicVariableCategories $dynamicCategories,
    ) {}

    /**
     * The full catalogue for $module, masked for $actor (D-6).
     *
     * @return array<int, array{key: string, label: string, variables: array<int, array{variable: string, label: string, type: string, example: string}>}>
     */
    public function categoriesFor(DocumentLayoutModule $module, User $actor): array
    {
        return match ($module) {
            DocumentLayoutModule::Quotes => $this->quoteCategories($actor),
        };
    }

    /**
     * The full `{totals.key}` tokens valid for $module — used by
     * DocumentLayoutConfigValidator to check
     * `products_table.totals.rows[].variable` without building the whole,
     * actor-scoped catalogue: the `totals` category never carries PII, so no
     * masking is needed for this check.
     *
     * @return array<int, string>
     */
    public function totalsVariableTokens(DocumentLayoutModule $module): array
    {
        return match ($module) {
            DocumentLayoutModule::Quotes => array_map(static fn (string $key): string => "{totals.{$key}}", self::TOTALS_KEYS),
        };
    }

    /**
     * @return array<int, array{key: string, label: string, variables: array<int, array{variable: string, label: string, type: string, example: string}>}>
     */
    private function quoteCategories(User $actor): array
    {
        return [
            $this->staticCategory('quote', [
                ['key' => 'code', 'type' => self::TYPE_STRING, 'example' => 'QUO-2026-0001'],
                ['key' => 'title', 'type' => self::TYPE_STRING, 'example' => 'Fornitura arredi ufficio'],
                ['key' => 'internal_notes', 'type' => self::TYPE_STRING, 'example' => 'Cliente storico, sconto gia concordato'],
                ['key' => 'status_name', 'type' => self::TYPE_STRING, 'example' => 'Inviato'],
                ['key' => 'created_at', 'type' => self::TYPE_DATE, 'example' => '2026-07-30'],
                ['key' => 'updated_at', 'type' => self::TYPE_DATE, 'example' => '2026-07-30'],
            ]),
            $this->staticCategory('totals', [
                ['key' => 'revenue_net', 'type' => self::TYPE_CURRENCY, 'example' => '1250.00'],
                ['key' => 'revenue_vat', 'type' => self::TYPE_CURRENCY, 'example' => '275.00'],
                ['key' => 'revenue_gross', 'type' => self::TYPE_CURRENCY, 'example' => '1525.00'],
                ['key' => 'cost_net', 'type' => self::TYPE_CURRENCY, 'example' => '800.00'],
                ['key' => 'cost_vat', 'type' => self::TYPE_CURRENCY, 'example' => '176.00'],
                ['key' => 'cost_gross', 'type' => self::TYPE_CURRENCY, 'example' => '976.00'],
                ['key' => 'margin_net', 'type' => self::TYPE_CURRENCY, 'example' => '450.00'],
            ]),
            $this->clientCategory($actor),
            $this->staticCategory('opportunity', [
                ['key' => 'name', 'type' => self::TYPE_STRING, 'example' => 'Fornitura Q3 2026'],
                ['key' => 'status_name', 'type' => self::TYPE_STRING, 'example' => 'Trattativa'],
                ['key' => 'estimated_value', 'type' => self::TYPE_CURRENCY, 'example' => '5000.00'],
                ['key' => 'expected_close_date', 'type' => self::TYPE_DATE, 'example' => '2026-09-15'],
                ['key' => 'start_date', 'type' => self::TYPE_DATE, 'example' => '2026-07-01'],
                ['key' => 'general_notes', 'type' => self::TYPE_STRING, 'example' => 'Trattativa avviata da fiera di settore'],
            ]),
            $this->staticCategory('referent', [
                ['key' => 'name', 'type' => self::TYPE_STRING, 'example' => 'Giulia Bianchi'],
                ['key' => 'type_name', 'type' => self::TYPE_STRING, 'example' => 'Responsabile acquisti'],
                ['key' => 'email', 'type' => self::TYPE_STRING, 'example' => 'giulia.bianchi@example.com'],
                ['key' => 'phone', 'type' => self::TYPE_STRING, 'example' => '+39 02 1234567'],
            ]),
            $this->staticCategory('commercial', [
                ['key' => 'name', 'type' => self::TYPE_STRING, 'example' => 'Marco Verdi'],
                ['key' => 'email', 'type' => self::TYPE_STRING, 'example' => 'marco.verdi@example.com'],
                ['key' => 'phone', 'type' => self::TYPE_STRING, 'example' => '+39 335 1234567'],
            ]),
            $this->staticCategory('reporter', [
                ['key' => 'name', 'type' => self::TYPE_STRING, 'example' => 'Anna Neri'],
                ['key' => 'email', 'type' => self::TYPE_STRING, 'example' => 'anna.neri@example.com'],
                ['key' => 'phone', 'type' => self::TYPE_STRING, 'example' => '+39 335 7654321'],
            ]),
            $this->staticCategory('supervisor', [
                ['key' => 'name', 'type' => self::TYPE_STRING, 'example' => 'Luca Ferri'],
                ['key' => 'email', 'type' => self::TYPE_STRING, 'example' => 'luca.ferri@example.com'],
            ]),
            $this->staticCategory('company', [
                ['key' => 'denomination', 'type' => self::TYPE_STRING, 'example' => 'Qnet S.r.l.'],
                ['key' => 'vat_number', 'type' => self::TYPE_STRING, 'example' => 'IT01234567890'],
                ['key' => 'address', 'type' => self::TYPE_STRING, 'example' => 'Via Milano 1 - 00100 Roma'],
                ['key' => 'address_city', 'type' => self::TYPE_STRING, 'example' => 'Roma'],
                ['key' => 'address_postal_code', 'type' => self::TYPE_STRING, 'example' => '00100'],
            ]),
            $this->staticCategory('company_site', [
                ['key' => 'name', 'type' => self::TYPE_STRING, 'example' => 'Sede di Milano'],
                ['key' => 'bank_name', 'type' => self::TYPE_STRING, 'example' => 'Banca Intesa'],
                ['key' => 'bank_iban', 'type' => self::TYPE_STRING, 'example' => 'IT60X0542811101000000123456'],
                ['key' => 'address', 'type' => self::TYPE_STRING, 'example' => 'Via Torino 5 - 20100 Milano'],
                ['key' => 'address_city', 'type' => self::TYPE_STRING, 'example' => 'Milano'],
                ['key' => 'address_postal_code', 'type' => self::TYPE_STRING, 'example' => '20100'],
            ]),
            $this->staticCategory('operational_site', [
                ['key' => 'label', 'type' => self::TYPE_STRING, 'example' => 'Via Torino 5 - Milano'],
            ]),
            $this->dynamicCategories->customFields('quotes'),
            $this->dynamicCategories->opportunityAttributes(),
            $this->staticCategory('document', [
                ['key' => 'generated_at', 'type' => self::TYPE_DATE, 'example' => '2026-07-30'],
                ['key' => 'generated_by', 'type' => self::TYPE_STRING, 'example' => 'Mario Rossi'],
            ]),
        ];
    }

    /**
     * @return array{key: string, label: string, variables: array<int, array{variable: string, label: string, type: string, example: string}>}
     */
    private function clientCategory(User $actor): array
    {
        $visible = $this->visiblePersonalDataFields($actor, 'registries', self::CLIENT_PERSONAL_DATA_KEYS);
        $masked = array_diff(self::CLIENT_PERSONAL_DATA_KEYS, $visible);

        $definitions = array_values(array_filter([
            ['key' => 'name', 'type' => self::TYPE_STRING, 'example' => 'Rossi S.r.l.'],
            ['key' => 'full_name', 'type' => self::TYPE_STRING, 'example' => 'Mario Rossi'],
            ['key' => 'type', 'type' => self::TYPE_STRING, 'example' => 'company'],
            ['key' => 'vat_number', 'type' => self::TYPE_STRING, 'example' => 'IT01234567890'],
            ['key' => 'tax_code', 'type' => self::TYPE_STRING, 'example' => 'RSSMRA80A01H501U'],
            ['key' => 'sdi_code', 'type' => self::TYPE_STRING, 'example' => 'ABCDE12'],
            ['key' => 'email', 'type' => self::TYPE_STRING, 'example' => 'info@rossisrl.example.com'],
            ['key' => 'phone', 'type' => self::TYPE_STRING, 'example' => '+39 02 7654321'],
            ['key' => 'address', 'type' => self::TYPE_STRING, 'example' => 'Via Dante 12 - 20121 Milano'],
            ['key' => 'address_line1', 'type' => self::TYPE_STRING, 'example' => 'Via Dante 12'],
            ['key' => 'address_postal_code', 'type' => self::TYPE_STRING, 'example' => '20121'],
            ['key' => 'address_city', 'type' => self::TYPE_STRING, 'example' => 'Milano'],
            ['key' => 'address_province', 'type' => self::TYPE_STRING, 'example' => 'Milano'],
            ['key' => 'address_state', 'type' => self::TYPE_STRING, 'example' => 'Lombardia'],
            ['key' => 'address_country', 'type' => self::TYPE_STRING, 'example' => 'Italia'],
        ], static fn (array $definition): bool => ! in_array($definition['key'], $masked, true)));

        return $this->staticCategory('client', $definitions);
    }

    /**
     * The subset of $fields whose `personal_data.{field}` key is VISIBLE to
     * $actor on $resource (D-6): piggy-backs on the exact same merge
     * AbstractResourceAuthorization::fieldPermissions() already computes
     * (ceiling intersected with role_field_permissions, privileged-role
     * bypass) rather than re-implementing it. $model is always null: none of
     * these three PII fields' `visible` flag depends on create-vs-update
     * (only `editable` does, see RegistriesAuthorization::personalDataFieldPermissions()),
     * so this single, model-less check is correct for every quote's client.
     *
     * @param  array<int, string>  $fields
     * @return array<int, string>
     */
    private function visiblePersonalDataFields(User $actor, string $resource, array $fields): array
    {
        $permissions = $this->authorizationRegistry->resolve($resource)->fieldPermissions($actor, null);

        return array_values(array_filter(
            $fields,
            static fn (string $field): bool => ($permissions["personal_data.{$field}"] ?? null)?->visible ?? true,
        ));
    }

    /**
     * @param  array<int, array{key: string, type: string, example: string}>  $definitions
     * @return array{key: string, label: string, variables: array<int, array{variable: string, label: string, type: string, example: string}>}
     */
    private function staticCategory(string $key, array $definitions): array
    {
        return [
            'key' => $key,
            'label' => __("document_layouts.variables.categories.{$key}"),
            'variables' => array_map(
                fn (array $definition): array => $this->variable($key, $definition['key'], $definition['type'], $definition['example']),
                $definitions,
            ),
        ];
    }

    /**
     * @return array{variable: string, label: string, type: string, example: string}
     */
    private function variable(string $category, string $key, string $type, string $example): array
    {
        return [
            'variable' => "{{$category}.{$key}}",
            'label' => __("document_layouts.variables.{$category}.{$key}"),
            'type' => $type,
            'example' => $example,
        ];
    }
}
