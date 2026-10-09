<?php

namespace App\DataObjects\PurchaseRequests;

use Illuminate\Support\Arr;

/**
 * Validated payload of a purchase request write (POST/PUT /api/purchase-requests,
 * spec 0208): the header and footer columns plus the lines in submission order.
 * Totals, status and `created_by` are never part of it (D-12, D-9).
 */
final readonly class PurchaseRequestData
{
    /** Header and footer columns a client may write. */
    public const array HEADER_KEYS = [
        'subject', 'requested_at', 'priority', 'requester_id', 'function_manager_id', 'customer_id',
        'supplier_id', 'work_order_id', 'company_id', 'company_site_id', 'operational_site_id',
        'business_function_id', 'notes', 'delivery_terms', 'procurement_plan',
        'technical_requirements', 'special_conditions',
    ];

    /** Editable content of a line (D-10); `id` only identifies an existing one. */
    public const array LINE_KEYS = [
        'product_id', 'description', 'reason', 'unit_of_measure_id', 'quantity', 'unit_price', 'vat_rate_id',
    ];

    /**
     * @param  array<string, mixed>  $header
     * @param  array<int, array<string, mixed>>  $lines  each with an optional `id`
     */
    public function __construct(public array $header, public array $lines) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        $lines = array_map(
            static fn (array $line): array => ['id' => $line['id'] ?? null] + array_merge(
                array_fill_keys(self::LINE_KEYS, null),
                Arr::only($line, self::LINE_KEYS),
            ),
            array_values($data['lines']),
        );

        return new self(Arr::only($data, self::HEADER_KEYS), $lines);
    }
}
