<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts;

/**
 * The `purchase_orders` module's variable categories (spec 0210) as plain
 * definitions; DocumentLayoutVariableCatalog turns them into the picker shape
 * (labels, masking). Categories shared with `invoices` (company, company_site,
 * work_order, document) are reused from DocumentLayoutInvoiceVariableDefinitions,
 * never redeclared.
 */
final class DocumentLayoutPurchaseOrderVariableDefinitions
{
    private const string STRING = 'string';

    private const string DATE = 'date';

    private const string CURRENCY = 'currency';

    /** The `totals` category's bare keys for purchase orders. */
    public const array TOTALS_KEYS = ['net', 'vat', 'total'];

    /** The `supplier.*` key sourced from a `personal_data` column (masked like `client.vat_number`, D-6). */
    public const string SUPPLIER_PII_KEY = 'vat_number';

    /**
     * @return array<string, array<int, array{key: string, type: string, example: string}>> category => variables
     */
    public static function all(): array
    {
        $shared = DocumentLayoutInvoiceVariableDefinitions::all();

        return [
            'purchase_order' => [
                ['key' => 'code', 'type' => self::STRING, 'example' => '12/2026'],
                ['key' => 'order_date', 'type' => self::DATE, 'example' => '2026-07-30'],
                ['key' => 'description', 'type' => self::STRING, 'example' => 'Office furniture supply'],
                ['key' => 'general_notes', 'type' => self::STRING, 'example' => 'Delivery within 15 days'],
            ],
            'supplier' => [
                ['key' => 'name', 'type' => self::STRING, 'example' => 'Bianchi S.r.l.'],
                ['key' => 'vat_number', 'type' => self::STRING, 'example' => 'IT01234567890'],
                ['key' => 'address', 'type' => self::STRING, 'example' => 'Via Dante 12 - 20121 Milano'],
                ['key' => 'email', 'type' => self::STRING, 'example' => 'info@bianchisrl.example.com'],
            ],
            'company' => $shared['company'],
            'company_site' => $shared['company_site'],
            'operational_site' => [
                ['key' => 'label', 'type' => self::STRING, 'example' => 'Via Torino 5 - Milano'],
            ],
            'payment' => [
                ['key' => 'method_name', 'type' => self::STRING, 'example' => 'Bank transfer 30 days'],
            ],
            'customer' => [
                ['key' => 'name', 'type' => self::STRING, 'example' => 'Rossi S.r.l.'],
            ],
            'work_order' => $shared['work_order'],
            'totals' => [
                ['key' => 'net', 'type' => self::CURRENCY, 'example' => '1250.00'],
                ['key' => 'vat', 'type' => self::CURRENCY, 'example' => '275.00'],
                ['key' => 'total', 'type' => self::CURRENCY, 'example' => '1525.00'],
            ],
            'document' => $shared['document'],
        ];
    }
}
