<?php

declare(strict_types=1);

namespace App\Tables\WorkOrders;

use App\Enums\ContractStatusGroup;
use App\Enums\StatusSystemKey;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderWorkPhase;
use App\Models\ContractStatus;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The hidden `work_phase` column behind the status tabs of the Commesse page.
 * The first three phases are the groups of "Stato lavorazione"; the `closed`
 * group splits on the commessa's contract: Terminated when the contract is
 * closed negatively by anything but "Annullato", Cancelled otherwise.
 */
final class WorkOrderWorkPhaseColumn
{
    public const string COLUMN = 'work_phase';

    private const string ALL_COUNT = 'all';

    private const string CONTRACT_STATUS_RELATION = 'quote.contract.contractStatus';

    public function project(WorkOrder $workOrder): ?string
    {
        $group = $workOrder->workOrderWorkflowStatus?->group;

        return $group === null ? null : $this->phaseOf($group, $workOrder->quote?->contract?->contractStatus)->value;
    }

    /**
     * How many commesse of $query sit in each phase, plus the total under
     * `all`: the figures of the tabs.
     *
     * @param  Builder<Model>  $query
     * @return array<string, int>
     */
    public function counts(Builder $query): array
    {
        $contractStatuses = ContractStatus::query()->get()->keyBy('id');
        $counts = array_fill_keys(WorkOrderWorkPhase::values(), 0);

        // `contracts.quote_id` is unique, so the join cannot multiply a commessa.
        $groups = DB::table('work_orders')
            ->join('work_order_workflow_statuses', 'work_order_workflow_statuses.id', '=', 'work_orders.work_order_workflow_status_id')
            ->leftJoin('contracts', 'contracts.quote_id', '=', 'work_orders.quote_id')
            ->whereIn('work_orders.id', (clone $query)->reorder()->select('work_orders.id'))
            ->groupBy('work_order_workflow_statuses.group', 'contracts.contract_status_id')
            ->select('work_order_workflow_statuses.group', 'contracts.contract_status_id')
            ->selectRaw('COUNT(*) as total')
            ->get();

        foreach ($groups as $row) {
            $counts[$this->phaseOf(WorkOrderStatus::from($row->group), $contractStatuses->get($row->contract_status_id))->value] += (int) $row->total;
        }

        return [self::ALL_COUNT => array_sum($counts), ...$counts];
    }

    private function phaseOf(WorkOrderStatus $group, ?ContractStatus $contractStatus): WorkOrderWorkPhase
    {
        return match ($group) {
            WorkOrderStatus::Open => WorkOrderWorkPhase::ToPlan,
            WorkOrderStatus::InProgress => WorkOrderWorkPhase::InProgress,
            WorkOrderStatus::Completed => WorkOrderWorkPhase::Completed,
            WorkOrderStatus::Closed => $contractStatus?->closesAsTermination() ? WorkOrderWorkPhase::Terminated : WorkOrderWorkPhase::Cancelled,
        };
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<int, string>  $values
     */
    public function applyFilter(Builder $query, array $values): void
    {
        $phases = array_filter(array_map(WorkOrderWorkPhase::tryFrom(...), $values));

        if ($phases === []) {
            return;
        }

        $query->where(function (Builder $any) use ($phases): void {
            foreach ($phases as $phase) {
                $any->orWhere(fn (Builder $branch) => $this->constrainToPhase($branch, $phase));
            }
        });
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function constrainToPhase(Builder $query, WorkOrderWorkPhase $phase): void
    {
        $query->whereHas('workOrderWorkflowStatus', static function (Builder $status) use ($phase): void {
            $status->where('group', $phase->statusGroup()->value);
        });

        match ($phase) {
            WorkOrderWorkPhase::Terminated => $query->whereHas(self::CONTRACT_STATUS_RELATION, $this->terminatedContractStatus(...)),
            WorkOrderWorkPhase::Cancelled => $query->whereDoesntHave(self::CONTRACT_STATUS_RELATION, $this->terminatedContractStatus(...)),
            default => null,
        };
    }

    /**
     * SQL twin of ContractStatus::closesAsTermination().
     *
     * @param  Builder<Model>  $status
     */
    private function terminatedContractStatus(Builder $status): void
    {
        $status->where('group', ContractStatusGroup::ClosedLost->value)
            ->where(static function (Builder $key): void {
                $key->whereNull('system_key')->orWhere('system_key', '<>', StatusSystemKey::Cancelled->value);
            });
    }
}
