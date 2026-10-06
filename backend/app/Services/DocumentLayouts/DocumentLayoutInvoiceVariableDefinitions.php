<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts;

/**
 * The `invoices` module's frozen variable categories (spec 0195 D-7) as plain
 * definitions; DocumentLayoutVariableCatalog turns them into the picker shape
 * (labels, masking). Split out to keep the catalogue under the file-size
 * soft limit.
 */
final class DocumentLayoutInvoiceVariableDefinitions
{
    private const string STRING = 'string';

    private const string DATE = 'date';

    private const string CURRENCY = 'currency';

    /** The `totals` category's bare keys for invoices. */
    public const array TOTALS_KEYS = ['net', 'vat', 'total', 'collected', 'residual'];

    /**
     * @return array<string, array<int, array{key: string, type: string, example: string}>> category => variables
     */
    public static function all(): array
    {
        return [
            'invoice' => [
                ['key' => 'number_label', 'type' => self::STRING, 'example' => '12/2026'],
                ['key' => 'type_label', 'type' => self::STRING, 'example' => 'Invoice'],
                ['key' => 'document_date', 'type' => self::DATE, 'example' => '2026-07-30'],
                ['key' => 'external_number', 'type' => self::STRING, 'example' => 'FT-2026-0012'],
                ['key' => 'external_date', 'type' => self::DATE, 'example' => '2026-07-31'],
                ['key' => 'notes', 'type' => self::STRING, 'example' => 'Payment within 30 days'],
                ['key' => 'tag_label', 'type' => self::STRING, 'example' => 'Final'],
            ],
            'customer' => [
                ['key' => 'name', 'type' => self::STRING, 'example' => 'Rossi S.r.l.'],
                ['key' => 'address', 'type' => self::STRING, 'example' => 'Via Dante 12 - 20121 Milano'],
                ['key' => 'vat_number', 'type' => self::STRING, 'example' => 'IT01234567890'],
                ['key' => 'tax_code', 'type' => self::STRING, 'example' => 'RSSMRA80A01H501U'],
                ['key' => 'sdi_code', 'type' => self::STRING, 'example' => 'ABCDE12'],
                ['key' => 'pec', 'type' => self::STRING, 'example' => 'rossisrl@pec.example.com'],
                ['key' => 'email', 'type' => self::STRING, 'example' => 'info@rossisrl.example.com'],
            ],
            'company' => [
                ['key' => 'name', 'type' => self::STRING, 'example' => 'Qnet S.r.l.'],
                ['key' => 'vat_number', 'type' => self::STRING, 'example' => 'IT01234567890'],
                ['key' => 'address', 'type' => self::STRING, 'example' => 'Via Milano 1 - 00100 Roma'],
            ],
            'company_site' => [
                ['key' => 'name', 'type' => self::STRING, 'example' => 'Sede di Milano'],
                ['key' => 'bank_name', 'type' => self::STRING, 'example' => 'Banca Intesa'],
                ['key' => 'bank_iban', 'type' => self::STRING, 'example' => 'IT60X0542811101000000123456'],
                ['key' => 'address', 'type' => self::STRING, 'example' => 'Via Torino 5 - 20100 Milano'],
                ['key' => 'address_city', 'type' => self::STRING, 'example' => 'Milano'],
                ['key' => 'address_postal_code', 'type' => self::STRING, 'example' => '20100'],
            ],
            'payment' => [
                ['key' => 'method_name', 'type' => self::STRING, 'example' => 'Bank transfer 30 days'],
                ['key' => 'payment_instructions', 'type' => self::STRING, 'example' => 'Pay by bank transfer'],
                ['key' => 'bank_name', 'type' => self::STRING, 'example' => 'Banca Intesa'],
                ['key' => 'iban', 'type' => self::STRING, 'example' => 'IT60X0542811101000000123456'],
            ],
            'totals' => [
                ['key' => 'net', 'type' => self::CURRENCY, 'example' => '1250.00'],
                ['key' => 'vat', 'type' => self::CURRENCY, 'example' => '275.00'],
                ['key' => 'total', 'type' => self::CURRENCY, 'example' => '1525.00'],
                ['key' => 'collected', 'type' => self::CURRENCY, 'example' => '500.00'],
                ['key' => 'residual', 'type' => self::CURRENCY, 'example' => '1025.00'],
            ],
            'work_order' => [
                ['key' => 'code', 'type' => self::STRING, 'example' => 'WO-2026-0001'],
                ['key' => 'title', 'type' => self::STRING, 'example' => 'Office furniture supply'],
            ],
            'quote' => [
                ['key' => 'code', 'type' => self::STRING, 'example' => 'QUO-2026-0001'],
            ],
            'document' => [
                ['key' => 'generated_at', 'type' => self::DATE, 'example' => '2026-07-30'],
                ['key' => 'generated_by', 'type' => self::STRING, 'example' => 'Mario Rossi'],
            ],
        ];
    }
}
