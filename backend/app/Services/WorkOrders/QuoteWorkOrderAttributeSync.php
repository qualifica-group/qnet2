<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Models\Quote;
use App\Models\WorkOrder;
use App\Quotes\QuoteAttributeResolver;
use App\RequestManagement\ApplicableAttribute;
use App\WorkOrders\WorkOrderAttributeResolver;
use App\WorkOrders\WorkOrderQuoteLinks;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * The fields an offer and its work orders share one value of (spec 0425):
 * a write on one side lands on the other. Called by the two value writers
 * with the map before and after their merge, so every writing surface (form,
 * grid cell, Gestione Richieste, copy from the offer) syncs the same way.
 *
 * The mirrored write is direct: only the synced keys, no required-field or
 * hidden-section rule of the target, saved quietly so it never comes back
 * (D-3). A code an integration owns on the work order takes nothing from the
 * offer (D-5). No permission on the target is required: the sync is a rule of
 * the category, like the copy from the offer (D-6).
 */
final class QuoteWorkOrderAttributeSync
{
    public function __construct(
        private readonly QuoteAttributeResolver $quoteResolver,
        private readonly WorkOrderAttributeResolver $workOrderResolver,
        private readonly WorkOrderQuoteLinks $links,
    ) {}

    /**
     * @param  Collection<int, ApplicableAttribute>  $applicable  the offer's set the write validated against
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function fromQuote(Quote $quote, Collection $applicable, array $before, array $after): void
    {
        // Step 1: the synced values this write changed (none: no query at all)
        $changed = self::changedSyncedValues($applicable, $before, $after);

        if ($changed === []) {
            return;
        }

        // Step 2: every work order of the offer
        $this->links->workOrdersOf([$quote->id])->each(fn (WorkOrder $workOrder) => $this->mirrorOnWorkOrder($workOrder, $changed));
    }

    /**
     * @param  Collection<int, ApplicableAttribute>  $applicable  the work order's set the write validated against
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function fromWorkOrder(WorkOrder $workOrder, Collection $applicable, array $before, array $after): void
    {
        // Step 1: the synced values this write changed (none: no query at all)
        $changed = self::changedSyncedValues($applicable, $before, $after);

        if ($changed === []) {
            return;
        }

        // Step 2: its offers (every one, multi-offer), then their other work orders
        $quoteIds = $this->links->quoteIdsOf($workOrder);

        Quote::query()->whereIn('id', $quoteIds)->get()->each(fn (Quote $quote) => $this->mirrorOnQuote($quote, $changed));

        $this->links->workOrdersOf($quoteIds)
            ->reject(fn (WorkOrder $other): bool => $other->id === $workOrder->id)
            ->each(fn (WorkOrder $other) => $this->mirrorOnWorkOrder($other, $changed));
    }

    /**
     * Fills a work order just created with the offer's synced values it was
     * not given (D-4): a value sent with the creation already went the other
     * way, through fromWorkOrder().
     */
    public function adoptQuoteValues(WorkOrder $workOrder): void
    {
        // Step 1: the synced codes the work order still lacks
        $current = $workOrder->attribute_values ?? [];
        $missing = $this->syncedCodes($this->workOrderResolver->resolve($workOrder))
            ->diff($this->workOrderResolver->lockedCodes($workOrder))
            ->reject(static fn (string $code): bool => self::comparable($current[$code] ?? null) !== null);

        if ($missing->isEmpty()) {
            return;
        }

        // Step 2: the first offer holding a value, in the offers' order
        $quoteValues = Quote::query()->whereIn('id', $this->links->quoteIdsOf($workOrder))->orderBy('id')->pluck('attribute_values');
        $adopted = $missing->mapWithKeys(static fn (string $code): array => [
            $code => $quoteValues->map(static fn (?array $values): mixed => self::comparable($values[$code] ?? null))->first(static fn (mixed $value): bool => $value !== null),
        ])->filter(static fn (mixed $value): bool => $value !== null)->all();

        self::write($workOrder, $adopted);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function mirrorOnWorkOrder(WorkOrder $workOrder, array $values): void
    {
        $accepted = $this->syncedCodes($this->workOrderResolver->resolve($workOrder))
            ->diff($this->workOrderResolver->lockedCodes($workOrder))
            ->all();

        self::write($workOrder, array_intersect_key($values, array_flip($accepted)));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function mirrorOnQuote(Quote $quote, array $values): void
    {
        $accepted = $this->syncedCodes($this->quoteResolver->resolve($quote))->all();

        self::write($quote, array_intersect_key($values, array_flip($accepted)));
    }

    /**
     * @param  Collection<int, ApplicableAttribute>  $applicable
     * @return Collection<int, string>
     */
    private function syncedCodes(Collection $applicable): Collection
    {
        return $applicable->filter(static fn (ApplicableAttribute $attribute): bool => $attribute->isSynced)->pluck('code')->values();
    }

    /**
     * @param  Collection<int, ApplicableAttribute>  $applicable
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, mixed> code => new value (null = emptied)
     */
    private static function changedSyncedValues(Collection $applicable, array $before, array $after): array
    {
        $changed = [];

        foreach ($applicable as $attribute) {
            $value = self::comparable($after[$attribute->code] ?? null);

            if ($attribute->isSynced && $value !== self::comparable($before[$attribute->code] ?? null)) {
                $changed[$attribute->code] = $value;
            }
        }

        return $changed;
    }

    /**
     * Sets $values on the record's map (null removes the key) and saves only
     * when something changed, without events: the mirror never syncs back.
     *
     * @param  array<string, mixed>  $values
     */
    private static function write(Model $record, array $values): void
    {
        $current = $record->getAttribute('attribute_values') ?? [];
        $next = $current;

        foreach ($values as $code => $value) {
            if ($value === null) {
                unset($next[$code]);
            } else {
                $next[$code] = $value;
            }
        }

        if ($next === $current) {
            return;
        }

        // `attribute_values` is not fillable on either model: forceFill is the write path.
        $record->forceFill(['attribute_values' => $next])->saveQuietly();
    }

    /**
     * Empty in any form (null, blank string, empty list) is one value: null.
     */
    private static function comparable(mixed $value): mixed
    {
        return $value === '' || $value === [] ? null : $value;
    }
}
