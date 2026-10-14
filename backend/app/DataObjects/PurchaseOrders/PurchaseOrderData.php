<?php

namespace App\DataObjects\PurchaseOrders;

use Illuminate\Support\Arr;

/**
 * Validated payload of a purchase order write (POST/PUT /api/purchase-orders,
 * spec 0210): the header columns, the optional sector ids, the monthly copies
 * (creation only) and the lines in submission order. A partial edit carries
 * only the submitted header keys, and `lines` is null when not sent (spec 0257 D-2). Number, year, status,
 * totals and `created_by` are never part of it (D-11, D-12, D-9).
 */
final readonly class PurchaseOrderData
{
    /**
     * Fields still writable past approval (spec 0259, D-1): what the supplier
     * did not receive. Supplier, date, description, company, site and lines stay locked.
     */
    public const array SERVICE_FIELDS = [
        'operational_site_id', 'general_notes', 'payment_method_id', 'is_internal_user', 'customer_id',
        'work_order_id', 'quote_id', 'opportunity_id', 'sector_ids', 'sector_notes',
    ];

    /** Header columns a client may write. */
    public const array HEADER_KEYS = [
        'supplier_id', 'company_id', 'company_site_id', 'operational_site_id', 'order_date', 'description',
        'general_notes', 'payment_method_id', 'is_internal_user', 'customer_id', 'work_order_id',
        'quote_id', 'opportunity_id', 'sector_notes',
    ];

    /** Editable content of a line; `id` only identifies an existing one. */
    public const array LINE_KEYS = [
        'product_id', 'description', 'cost_type_id', 'unit_of_measure_id', 'quantity', 'unit_price',
        'vat_rate_id', 'work_order_id',
    ];

    /**
     * @param  array<string, mixed>  $header
     * @param  array<int, array<string, mixed>>|null  $lines  each with an optional `id`; null = not submitted
     * @param  array<int, int>|null  $sectorIds  null when the key was not submitted (sectors untouched)
     */
    public function __construct(
        public array $header,
        public ?array $lines,
        public ?array $sectorIds = null,
        public int $copies = 1,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        $lines = isset($data['lines']) ? array_map(
            static fn (array $line): array => ['id' => $line['id'] ?? null] + array_merge(
                array_fill_keys(self::LINE_KEYS, null),
                Arr::only($line, self::LINE_KEYS),
            ),
            array_values($data['lines']),
        ) : null;

        $header = Arr::only($data, self::HEADER_KEYS);

        // The column is NOT NULL: an explicit null means "off"
        if (array_key_exists('is_internal_user', $header)) {
            $header['is_internal_user'] = (bool) $header['is_internal_user'];
        }

        return new self(
            $header,
            $lines,
            array_key_exists('sector_ids', $data) ? array_map('intval', (array) $data['sector_ids']) : null,
            (int) ($data['copies'] ?? 1),
        );
    }
}
