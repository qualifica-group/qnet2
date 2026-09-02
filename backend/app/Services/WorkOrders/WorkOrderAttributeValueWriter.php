<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Models\WorkOrder;
use App\RequestManagement\ApplicableAttribute;
use App\RequestManagement\AttributeValueNormalizer;
use App\RequestManagement\AttributeValueValidator;
use App\WorkOrders\WorkOrderAttributeResolver;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The dynamic `attribute_values` write of the `work-orders` resource (spec
 * 0098, D-4/D-5), byte-for-byte the same pipeline as
 * App\Services\Quotes\QuoteAttributeValueWriter: reuses the SAME agnostic
 * AttributeValueValidator/AttributeValueNormalizer (vietato clonarli).
 *
 * Validation runs against the applicable set resolved for the work order's
 * OWN quote lines AS THEY ARE when this is called (per-code applicability/
 * type/required, AttributeValueValidator, keyed `attribute_values.<code>` on
 * failure) — WorkOrderService calls this AFTER `WorkOrderLineWriter::
 * writeSubmitted()` (D-6), so the set validated against is exactly the one
 * the form rendered its fields from.
 *
 * The merge is sparse: a code the payload leaves out keeps its persisted
 * value (on create there is none yet, so the map is simply what was
 * submitted).
 */
final class WorkOrderAttributeValueWriter
{
    public function __construct(
        private readonly WorkOrderAttributeResolver $attributeResolver,
        private readonly AttributeValueValidator $validator,
        private readonly AttributeValueNormalizer $normalizer,
    ) {}

    /**
     * Merges $submitted into the work order's map IN MEMORY — the caller
     * saves. Reports into $changed/$old so the caller's audit entry carries
     * the same shape the automatic model log would have (the column is NOT
     * fillable, so Spatie's dirty-diff never sees it).
     *
     * @param  array<string, mixed>  $submitted
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     * @param  Collection<int, ApplicableAttribute>|null  $applicable  the set to validate against; `null` = the work order's own quote-line set
     *
     * @throws ValidationException a submitted code is not applicable, has the wrong type, or a required one is empty
     */
    public function apply(WorkOrder $workOrder, array $submitted, array &$changed, array &$old, ?Collection $applicable = null): void
    {
        $applicable ??= $this->attributeResolver->resolve($workOrder);
        $validated = $this->validator->validate($applicable, $submitted);
        $normalized = $this->normalizer->normalize($applicable, $validated);

        $current = $workOrder->attribute_values ?? [];
        $merged = array_merge($current, $normalized);

        if ($merged === $current) {
            return;
        }

        $old['attribute_values'] = $current;
        // `attribute_values` is NOT in WorkOrder::$fillable (mass-assignment
        // guard): forceFill is the deliberate, single write path.
        $workOrder->forceFill(['attribute_values' => $merged]);
        $changed['attribute_values'] = $merged;
    }
}
