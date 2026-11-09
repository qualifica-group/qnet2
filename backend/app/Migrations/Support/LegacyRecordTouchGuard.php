<?php

declare(strict_types=1);

namespace App\Migrations\Support;

use App\Models\Contract;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\WorkOrder;
use App\Models\WorkOrderLinePayment;
use App\Models\WorkOrderStage;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

/**
 * Decides whether a record imported from the legacy was worked on in qnet-2
 * afterwards (spec 0372, D-6): the import runs without activity log, so any
 * `activity_log` entry on the record is a human change that a realignment must
 * not overwrite.
 *
 * A commessa also counts its stages and line payments; an offer also counts its
 * opportunity, because the status and Gestione Richieste services log the offer's
 * actions on the opportunity. One query per check.
 */
final class LegacyRecordTouchGuard
{
    public function isWorkOrderTouched(WorkOrder $workOrder): bool
    {
        return $this->hasActivity([
            [WorkOrder::class, $workOrder->getKey()],
            [WorkOrderStage::class, WorkOrderStage::query()->where('work_order_id', $workOrder->getKey())->select('id')],
            [WorkOrderLinePayment::class, WorkOrderLinePayment::query()->where('work_order_id', $workOrder->getKey())->select('id')],
        ]);
    }

    public function isOpportunityTouched(Opportunity $opportunity): bool
    {
        return $this->hasActivity([[Opportunity::class, $opportunity->getKey()]]);
    }

    public function isQuoteTouched(Quote $quote): bool
    {
        return $this->hasActivity([
            [Quote::class, $quote->getKey()],
            [Opportunity::class, $quote->opportunity_id],
        ]);
    }

    public function isContractTouched(Contract $contract): bool
    {
        return $this->hasActivity([[Contract::class, $contract->getKey()]]);
    }

    /**
     * @param  list<array{0: class-string<Model>, 1: int|string|Builder<Model>|null}>  $subjects  model class + the id(s) to look for
     */
    private function hasActivity(array $subjects): bool
    {
        return Activity::query()
            ->where(function (Builder $query) use ($subjects): void {
                foreach ($subjects as [$class, $ids]) {
                    if ($ids === null) {
                        continue;
                    }

                    $query->orWhere($this->subjectMatches($class, $ids));
                }
            })
            ->exists();
    }

    /**
     * @param  class-string<Model>  $class
     * @param  int|string|Builder<Model>  $ids
     */
    private function subjectMatches(string $class, int|string|Builder $ids): Closure
    {
        return function (Builder $query) use ($class, $ids): void {
            $query->where('subject_type', (new $class)->getMorphClass());

            $ids instanceof Builder
                ? $query->whereIn('subject_id', $ids)
                : $query->where('subject_id', $ids);
        };
    }
}
