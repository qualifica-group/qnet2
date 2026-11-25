<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Models\Company;
use App\Models\CompanySite;
use App\Models\OperationalSite;
use App\Models\OpportunityProductLine;
use App\Models\Product;
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

    /**
     * Spec 0431 (D-11/D-17): the product categories a commessa born without an
     * offer is classified by: those of its business-function rows and of its
     * products, the latter standing for the offer lines it does not have.
     * They resolve its "Informazioni aggiuntive", its workflow set and its
     * category filters. Empty on any other commessa (its lines decide there).
     *
     * @return array<int, int>
     */
    public function standaloneCategoryIds(WorkOrder $workOrder): array
    {
        if (! $workOrder->is_standalone) {
            return [];
        }

        $workOrder->loadMissing(['productLines', 'products']);

        return $workOrder->productLines->pluck('product_category_id')
            ->merge($workOrder->products->pluck('category_id'))
            ->filter()
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The same set for a commessa not yet saved (the create form's preview).
     *
     * @param  array<int, int>  $productIds
     * @param  array<int, int>  $productCategoryIds
     * @return array<int, int>
     */
    public function draftCategoryIds(array $productIds, array $productCategoryIds): array
    {
        $productCategories = $productIds === []
            ? []
            : Product::query()->whereKey($productIds)->pluck('category_id')->all();

        return collect([...$productCategoryIds, ...$productCategories])
            ->filter()
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
