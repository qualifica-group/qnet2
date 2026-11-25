<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Models\Company;
use App\Models\CompanySite;
use App\Models\OperationalSite;
use App\Models\OpportunityProductLine;
use App\Models\Registry;
use App\Models\WorkOrder;
use App\Models\WorkOrderProductLine;
use Illuminate\Support\Collection;

/**
 * The client, company, sites and business-function lines of a commessa
 * (spec 0431, D-6): its OWN ones when it was born without an offer, its
 * offer's otherwise (quote -> opportunity -> registry, quote.company...),
 * null on a multi-offer commessa, which has no single offer. The one place
 * that knows where these come from: every reader asks here.
 *
 * The relations are expected eager-loaded by the caller
 * (WorkOrderService::DETAIL_RELATIONS); nothing here adds a query of its own
 * beyond what a lazy relation would.
 */
final class WorkOrderReferences
{
    public function registry(WorkOrder $workOrder): ?Registry
    {
        return $workOrder->is_standalone ? $workOrder->registry : $workOrder->quote?->opportunity?->registry;
    }

    /** The client's id alone, without loading the registry. */
    public function registryId(WorkOrder $workOrder): ?int
    {
        return $workOrder->is_standalone ? $workOrder->registry_id : $workOrder->quote?->opportunity?->registry_id;
    }

    public function company(WorkOrder $workOrder): ?Company
    {
        return $workOrder->is_standalone ? $workOrder->company : $workOrder->quote?->company;
    }

    public function companySite(WorkOrder $workOrder): ?CompanySite
    {
        return $workOrder->is_standalone ? $workOrder->companySite : $workOrder->quote?->companySite;
    }

    public function operationalSite(WorkOrder $workOrder): ?OperationalSite
    {
        return $workOrder->is_standalone ? $workOrder->operationalSite : $workOrder->quote?->operationalSite;
    }

    /**
     * The "funzione aziendale + categoria" rows: the commessa's own, or its
     * offer's opportunity's. Both row models expose `businessFunction` and
     * `productCategory`.
     *
     * @return Collection<int, WorkOrderProductLine|OpportunityProductLine>
     */
    public function productLines(WorkOrder $workOrder): Collection
    {
        if ($workOrder->is_standalone) {
            return $workOrder->productLines->toBase();
        }

        return collect($workOrder->quote?->opportunity?->productLines ?? [])->values();
    }
}
