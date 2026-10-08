<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkOrderCosts;

use App\Models\WorkOrder;
use App\Quotes\QuoteLineRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates PUT /api/work-orders/{workOrder}/costs (spec 0190): the FULL set
 * of the commessa's actual costs. Quantity/price/VAT/description rules are
 * the offer lines' own (QuoteLineRules::fieldRules); amounts and
 * `unit_of_measure_id` stay `prohibited` there (server-computed/frozen).
 * Authorization lives here (not in the controller) so a caller without
 * `manageCosts` gets 403 before any payload validation.
 */
class SyncWorkOrderCostsRequest extends FormRequest
{
    private const int DOCUMENT_REFERENCE_MAX = 100;

    /** Keys of QuoteLineRules::fieldRules() that apply unchanged to a cost row. */
    private const array SHARED_RULE_KEYS = [
        'product_id',
        'quantity',
        'unit_price',
        'vat_rate_id',
        'additional_description',
        'net_amount',
        'vat_amount',
        'total_amount',
        'unit_of_measure_id',
    ];

    public function authorize(): bool
    {
        return $this->user()?->can('manageCosts', $this->workOrder()) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $workOrderId = $this->workOrder()->id;
        $shared = QuoteLineRules::fieldRules(
            'lines',
            false,
            $this->workOrder()->costs()->pluck('product_id')->map(static fn (mixed $id): int => (int) $id)->all(),
        );

        $rules = ['lines' => ['present', 'array', 'max:'.QuoteLineRules::MAX_ROWS]];

        foreach (self::SHARED_RULE_KEYS as $key) {
            $rules["lines.*.{$key}"] = $shared["lines.*.{$key}"];
        }

        return $rules + [
            'lines.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('work_order_costs', 'id')->where('work_order_id', $workOrderId)],
            'lines.*.quote_line_id' => ['nullable', 'integer', Rule::exists('quote_line_work_order', 'quote_line_id')->where('work_order_id', $workOrderId)],
            'lines.*.incurred_on' => ['required', 'date_format:Y-m-d'],
            'lines.*.supplier_id' => ['nullable', 'integer', Rule::exists('registries', 'id')->where('is_supplier', true)],
            'lines.*.document_reference' => ['nullable', 'string', 'max:'.self::DOCUMENT_REFERENCE_MAX],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function lines(): array
    {
        /** @var array<int, array<string, mixed>> $lines */
        $lines = $this->validated('lines');

        return array_values($lines);
    }

    private function workOrder(): WorkOrder
    {
        /** @var WorkOrder $workOrder */
        $workOrder = $this->route('workOrder');

        return $workOrder;
    }
}
