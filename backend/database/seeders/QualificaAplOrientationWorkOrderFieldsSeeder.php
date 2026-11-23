<?php

namespace Database\Seeders;

use App\Enums\AttributeContext;
use App\Enums\LayoutFormScope;
use App\Models\Attribute;
use App\Models\ProductCategory;
use App\Models\WorkOrder;
use App\Services\ProductCategories\AttributeLayoutService;
use App\Services\WorkOrders\WorkOrderQuoteAttributeCopier;
use Database\Seeders\QualificaCatalog\AplOrientationAttributeCatalogue;
use Illuminate\Database\Seeder;

/**
 * One-shot (user directive 2026-11-23): the Orientamento specialistico offer
 * fields MOVE to the Commessa — assigned and laid out there, the existing work
 * orders filled from their offer, then withdrawn from the offer.
 *
 * The fields are read from the category's live Offerta set, not from
 * AplOrientationAttributeCatalogue, so the ones added by hand from the
 * configurator (the CPI section) move as well. The offer form wins over a
 * Commessa form: before this move the category had no Commessa field, so the
 * only Commessa form there can be is the one QualificaBranchWorkOrderSeeder
 * composed from the catalogue, which lacks the hand-made sections.
 *
 * The values stay in the offers' `attribute_values`: WorkOrderQuoteAttributeCopier
 * reads them by code, so a work order generated later still takes them.
 *
 * Idempotent: a second run finds no offer field left and writes nothing; a
 * work order value is never overwritten.
 * Run with `php artisan db:seed --class=QualificaAplOrientationWorkOrderFieldsSeeder`.
 */
class QualificaAplOrientationWorkOrderFieldsSeeder extends Seeder
{
    public function __construct(
        private readonly AttributeLayoutService $layouts,
        private readonly WorkOrderQuoteAttributeCopier $copier,
    ) {}

    public function run(): void
    {
        $category = ProductCategory::query()->where('name', AplOrientationAttributeCatalogue::CATEGORY)->sole();

        // Step 1: the Offerta fields, assigned in the Commessa context too
        $assigned = $this->mirrorQuoteFields($category);

        // Step 2: the Offerta form, before the work orders are written against it
        $forms = $this->moveQuoteForms($category);

        // Step 3: the existing work orders take their offer's values
        [$workOrders, $values] = $this->fillWorkOrders($category);

        // Step 4: the fields leave the offer
        $withdrawn = $category->attributes()->wherePivot('context', AttributeContext::Quote->value)->detach();

        $this->command?->info(sprintf(
            'Orientamento specialistico: %d fields assigned to Commessa, %d forms moved, %d values copied into %d work orders, %d fields withdrawn from Offerta.',
            $assigned,
            $forms,
            $values,
            $workOrders,
            $withdrawn,
        ));
    }

    private function mirrorQuoteFields(ProductCategory $category): int
    {
        $existing = $category->attributes()->wherePivot('context', AttributeContext::WorkOrder->value)->pluck('attributes.id')->all();
        $missing = $category->attributes()
            ->wherePivot('context', AttributeContext::Quote->value)
            ->whereNotIn('attributes.id', $existing)
            ->get();

        $missing->each(fn (Attribute $attribute) => $category->attributes()->attach($attribute->id, [
            'context' => AttributeContext::WorkOrder->value,
            'is_required' => $attribute->pivot->is_required,
            'sort_order' => $attribute->pivot->sort_order,
        ]));

        return $missing->count();
    }

    private function moveQuoteForms(ProductCategory $category): int
    {
        $moved = 0;

        foreach (LayoutFormScope::cases() as $scope) {
            $quoteForm = $this->layouts->resolveExact($category, AttributeContext::Quote, $scope);

            if ($quoteForm === null) {
                continue;
            }

            $this->layouts->upsert($category, AttributeContext::WorkOrder, $scope, $quoteForm);
            $this->layouts->upsert($category, AttributeContext::Quote, $scope, null);
            $moved++;
        }

        return $moved;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function fillWorkOrders(ProductCategory $category): array
    {
        $workOrders = 0;
        $values = 0;

        WorkOrder::query()
            ->whereHas('quoteLines.product', fn ($query) => $query->where('category_id', $category->id))
            ->lazyById()
            ->each(function (WorkOrder $workOrder) use (&$workOrders, &$values): void {
                $copied = $this->copier->fill($workOrder, $this->copier->quoteIdsOf($workOrder));
                $workOrders += $copied > 0 ? 1 : 0;
                $values += $copied;
            });

        return [$workOrders, $values];
    }
}
