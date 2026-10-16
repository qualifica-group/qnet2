<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DataObjects\WorkOrders\CreateWorkOrderData;
use App\Enums\WorkOrderType;
use App\Models\Contract;
use App\Models\WorkOrder;
use App\Services\WorkOrderService;
use Illuminate\Support\Facades\DB;

/**
 * "Programma commessa unica" (spec 0267, D-3): ONE multi-offer work order
 * (`quote_id` null) from lines of several contracts' offers, all or nothing.
 * The write goes through WorkOrderService::create() (numbering, line rules,
 * supervisors, task template, attribute values): nothing is duplicated here.
 */
final class MultiQuoteWorkOrderProgrammer
{
    public const int MAX_CONTRACTS = 50;

    public function __construct(private readonly WorkOrderService $workOrders) {}

    /**
     * @param  iterable<Contract>  $contracts  already authorized by MultiQuoteContractGate
     * @param  array<string, mixed>  $validated
     */
    public function handle(iterable $contracts, array $validated): WorkOrder
    {
        // Step 1: the offers the lines may come from are the contracts' own
        $quoteIds = collect($contracts)->map(static fn (Contract $contract): int => $contract->quote_id)->all();

        // Step 2: build the DTO from the common "Programma" fields
        $data = CreateWorkOrderData::forMultiQuoteProgram(
            quoteIds: $quoteIds,
            title: isset($validated['title']) ? (string) $validated['title'] : null,
            type: WorkOrderType::from((string) $validated['type']),
            startDate: (string) $validated['start_date'],
            supervisorIds: (array) $validated['supervisor_ids'],
            quoteLineIds: (array) $validated['quote_line_ids'],
            attributeValues: array_key_exists('attribute_values', $validated) ? (array) $validated['attribute_values'] : null,
            taskTemplateId: isset($validated['task_template_id']) ? (int) $validated['task_template_id'] : null,
        );

        // Step 3: create inside a transaction; any 422 leaves nothing behind
        return DB::transaction(fn (): WorkOrder => $this->workOrders->create($data));
    }
}
