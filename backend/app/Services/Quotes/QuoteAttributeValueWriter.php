<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\Models\Quote;
use App\Quotes\QuoteAttributeResolver;
use App\RequestManagement\AttributeValueNormalizer;
use App\RequestManagement\AttributeValueValidator;
use Illuminate\Validation\ValidationException;

/**
 * The dynamic `attribute_values` write of the `quotes` resource (spec 0084,
 * D-1/D-4): moved from the former Opportunity-level
 * `RequestAttributeValueWriter`, reusing the SAME agnostic
 * AttributeValueValidator/AttributeValueNormalizer pipeline (constraints:
 * vietato clonarli).
 *
 * Validation runs against the applicable set resolved for the quote's OWN
 * offer lines AS THEY ARE when this is called (per-code applicability/type/
 * required, AttributeValueValidator, keyed `attribute_values.<code>` on
 * failure) — QuoteService calls this AFTER the offer lines are written, so
 * the set validated against is exactly the one the form rendered its fields
 * from.
 *
 * The merge is sparse: a code the payload leaves out keeps its persisted
 * value (on create there is none yet, so the map is simply what was
 * submitted).
 */
final class QuoteAttributeValueWriter
{
    public function __construct(
        private readonly QuoteAttributeResolver $attributeResolver,
        private readonly AttributeValueValidator $validator,
        private readonly AttributeValueNormalizer $normalizer,
    ) {}

    /**
     * Merges $submitted into the quote's map IN MEMORY — the caller saves.
     * Reports into $changed/$old so the caller's audit entry carries the
     * same shape the automatic model log would have (the column is NOT
     * fillable, so Spatie's dirty-diff never sees it).
     *
     * @param  array<string, mixed>  $submitted
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     *
     * @throws ValidationException a submitted code is not applicable, has the wrong type, or a required one is empty
     */
    public function apply(Quote $quote, array $submitted, array &$changed, array &$old): void
    {
        $applicable = $this->attributeResolver->resolve($quote);
        $validated = $this->validator->validate($applicable, $submitted);
        $normalized = $this->normalizer->normalize($applicable, $validated);

        $current = $quote->attribute_values ?? [];
        $merged = array_merge($current, $normalized);

        if ($merged === $current) {
            return;
        }

        $old['attribute_values'] = $current;
        // `attribute_values` is NOT in Quote::$fillable (mass-assignment
        // guard): forceFill is the deliberate, single write path.
        $quote->forceFill(['attribute_values' => $merged]);
        $changed['attribute_values'] = $merged;
    }
}
