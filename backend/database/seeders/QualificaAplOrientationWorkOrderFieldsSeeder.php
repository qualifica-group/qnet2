<?php

namespace Database\Seeders;

use App\Enums\AttributeContext;
use App\Enums\LayoutFormScope;
use App\Models\Attribute;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\WorkOrder;
use App\Quotes\QuoteAttributeResolver;
use App\Services\ProductCategories\AttributeLayoutService;
use App\Services\WorkOrders\QuoteWorkOrderAttributeSync;
use App\Services\WorkOrders\WorkOrderQuoteAttributeCopier;
use Database\Seeders\QualificaCatalog\AplOrientationAttributeCatalogue;
use Illuminate\Database\Seeder;

/**
 * One-shot (user directives 2026-11-23, spec 0425 D-9): the Orientamento
 * specialistico offer fields MOVE to the Commessa — assigned and laid out
 * there, the existing work orders filled from their offer — and the offer
 * keeps "Anagrafica Utente" alone, synced with the Commessa.
 *
 * The fields are read from the category's live Offerta set, not from
 * AplOrientationAttributeCatalogue, so the ones added by hand from the
 * configurator (the CPI section) move as well. The offer form wins over a
 * Commessa form: before this move the category had no Commessa field, so the
 * only Commessa form there can be is the one QualificaBranchWorkOrderSeeder
 * composed from the catalogue, which lacks the hand-made sections. The offer
 * keeps no form: its single field renders flat.
 *
 * A synced field takes the offer's value on every work order, even one that
 * holds another (the work order never had the field before); the others only
 * fill an empty one. The values of the withdrawn fields stay in the offers'
 * `attribute_values`, so a work order generated later still takes them.
 *
 * Also completes an installation where the 1f04e9cd7 revision already ran
 * (every offer field withdrawn): "Anagrafica Utente" goes back to the offer.
 *
 * Idempotent: a second run finds nothing left to move and writes nothing.
 * Run with `php artisan db:seed --class=QualificaAplOrientationWorkOrderFieldsSeeder`.
 */
class QualificaAplOrientationWorkOrderFieldsSeeder extends Seeder
{
    public function __construct(
        private readonly AttributeLayoutService $layouts,
        private readonly WorkOrderQuoteAttributeCopier $copier,
        private readonly QuoteWorkOrderAttributeSync $sync,
        private readonly QuoteAttributeResolver $quoteResolver,
    ) {}

    public function run(): void
    {
        $category = ProductCategory::query()->where('name', AplOrientationAttributeCatalogue::CATEGORY)->sole();

        // Step 1: the Offerta fields in the Commessa, "Anagrafica Utente" synced and in both
        $assigned = $this->mirrorQuoteFields($category);
        $this->syncFieldsInBothContexts($category);

        // Step 2: the Offerta form, before the work orders are written against it
        $forms = $this->moveQuoteForms($category);

        // Step 3: the existing work orders take their offer's values
        [$workOrders, $values] = $this->fillWorkOrders($category);

        // Step 4: the offer keeps the synced fields alone
        $withdrawn = $this->withdrawQuoteFields($category);

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

    /**
     * Flags the synced codes on the Commessa and gives them back to the
     * Offerta when missing there: the 1f04e9cd7 revision of this seeder,
     * already run on some installation, withdrew every offer field.
     */
    private function syncFieldsInBothContexts(ProductCategory $category): void
    {
        $workOrderIds = $category->attributes()
            ->wherePivot('context', AttributeContext::WorkOrder->value)
            ->whereIn('code', AplOrientationAttributeCatalogue::SYNCED_CODES)
            ->pluck('attributes.id')
            ->all();
        $quoteIds = $category->attributes()->wherePivot('context', AttributeContext::Quote->value)->pluck('attributes.id')->all();

        $category->attributes()->wherePivot('context', AttributeContext::WorkOrder->value)->updateExistingPivot($workOrderIds, ['syncs_with_quote' => true]);

        foreach (array_diff($workOrderIds, $quoteIds) as $id) {
            $category->attributes()->attach($id, ['context' => AttributeContext::Quote->value, 'is_required' => false, 'sort_order' => 0]);
        }
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
        $quoteIds = [];

        WorkOrder::query()
            ->whereHas('quoteLines.product', fn ($query) => $query->where('category_id', $category->id))
            ->lazyById()
            ->each(function (WorkOrder $workOrder) use (&$workOrders, &$values, &$quoteIds): void {
                $ids = $this->copier->quoteIdsOf($workOrder);
                $copied = $this->copier->fill($workOrder, $ids);
                $workOrders += $copied > 0 ? 1 : 0;
                $values += $copied;
                $quoteIds = [...$quoteIds, ...$ids];
            });

        // A synced value is the offer's on every work order, even one that differs.
        Quote::query()->whereIn('id', array_unique($quoteIds))->lazyById()->each(fn (Quote $quote) => $this->sync->fromQuote(
            $quote,
            $this->quoteResolver->resolve($quote),
            [],
            $quote->attribute_values ?? [],
        ));

        return [$workOrders, $values];
    }

    private function withdrawQuoteFields(ProductCategory $category): int
    {
        $kept = Attribute::query()->whereIn('code', AplOrientationAttributeCatalogue::SYNCED_CODES)->pluck('id')->all();
        $withdrawn = $category->attributes()
            ->wherePivot('context', AttributeContext::Quote->value)
            ->whereNotIn('attributes.id', $kept)
            ->pluck('attributes.id')
            ->all();

        return $category->attributes()->wherePivot('context', AttributeContext::Quote->value)->detach($withdrawn);
    }
}
