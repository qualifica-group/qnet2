<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts;

use App\Enums\DocumentLayoutModule;

/**
 * The `products_table` block's closed `source` values per layout module (spec
 * 0195 D-6), each with the ColumnKey allow-list its rows expose. The
 * validator accepts only the sources of the layout's own module and, for the
 * chosen source, only its column keys; the renderer's subject produces rows
 * keyed by exactly these keys. A new module = a new arm here.
 */
final class DocumentLayoutProductSources
{
    /**
     * The quote line keys (spec 0069 config_schema #4); `discount` is
     * deliberately absent (0069 D-4).
     *
     * @var array<int, string>
     */
    public const array QUOTE_LINE_COLUMNS = [
        'code', 'name', 'description', 'additional_description', 'quantity', 'unit_price', 'vat_rate', 'net_amount', 'vat_amount', 'total_amount',
    ];

    /** @var array<int, string> */
    public const array INVOICE_LINE_COLUMNS = [
        'code', 'name', 'description', 'quantity', 'unit_price', 'vat_rate', 'net_amount', 'vat_amount', 'total_amount',
    ];

    /** @var array<int, string> */
    public const array INSTALLMENT_COLUMNS = ['sequence', 'due_date', 'amount', 'payment_method_code', 'status'];

    /**
     * @return array<string, array<int, string>> source => allowed column keys
     */
    public static function for(DocumentLayoutModule $module): array
    {
        return match ($module) {
            DocumentLayoutModule::Quotes => [
                'offer_lines' => self::QUOTE_LINE_COLUMNS,
                'cost_lines' => self::QUOTE_LINE_COLUMNS,
            ],
            DocumentLayoutModule::Invoices => [
                'invoice_lines' => self::INVOICE_LINE_COLUMNS,
                'installments' => self::INSTALLMENT_COLUMNS,
            ],
        };
    }
}
