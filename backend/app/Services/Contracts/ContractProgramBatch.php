<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DataObjects\WorkOrders\CreateWorkOrderData;
use App\Enums\WorkOrderType;
use App\Models\Contract;
use App\Models\WorkOrder;
use App\Services\WorkOrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Programma" in one go (spec 0215, D-1/D-9): creates one work order per
 * group of the contract's offer lines, ALL OR NOTHING. Each group goes through
 * WorkOrderService::create() (numbering, line membership, supervisors, task
 * template: no logic is duplicated here); the outer transaction makes the
 * batch atomic, and a ValidationException from any group is re-thrown with its
 * keys prefixed `groups.{index}.` so the client can place the error on the
 * right group.
 */
final class ContractProgramBatch
{
    public const int MAX_GROUPS = 50;

    public function __construct(private readonly WorkOrderService $workOrders) {}

    /**
     * @param  array<int, array<string, mixed>>  $groups  validated groups, in order
     * @return array<int, WorkOrder> same order as $groups
     */
    public function handle(Contract $contract, array $groups): array
    {
        // Step 1: one transaction around every create (inner ones become savepoints).
        return DB::transaction(function () use ($contract, $groups): array {
            $created = [];

            foreach (array_values($groups) as $index => $group) {
                try {
                    $created[] = $this->workOrders->create($this->toData($contract, $group));
                } catch (ValidationException $exception) {
                    // Step 2: point the error at the offending group; the
                    // exception leaves the closure, so everything rolls back.
                    throw $this->remap($exception, $index);
                }
            }

            return $created;
        });
    }

    /**
     * @param  array<string, mixed>  $group
     */
    private function toData(Contract $contract, array $group): CreateWorkOrderData
    {
        return CreateWorkOrderData::forContractGeneration(
            quoteId: $contract->quote_id,
            title: isset($group['title']) ? (string) $group['title'] : null,
            type: WorkOrderType::from((string) $group['type']),
            startDate: (string) $group['start_date'],
            supervisorIds: (array) $group['supervisor_ids'],
            quoteLineIds: (array) $group['quote_line_ids'],
            taskTemplateId: isset($group['task_template_id']) ? (int) $group['task_template_id'] : null,
        );
    }

    private function remap(ValidationException $exception, int $index): ValidationException
    {
        $messages = [];

        foreach ($exception->errors() as $key => $errors) {
            $messages["groups.{$index}.{$key}"] = $errors;
        }

        return ValidationException::withMessages($messages);
    }
}
