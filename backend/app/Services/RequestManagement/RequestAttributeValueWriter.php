<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\Models\Opportunity;
use App\RequestManagement\ApplicableAttributesResolver;
use App\RequestManagement\AttributeValueNormalizer;
use App\RequestManagement\AttributeValueValidator;
use Illuminate\Validation\ValidationException;

/**
 * The dynamic `attribute_values` write of the request-management module (spec
 * 0049 D-4), extracted from RequestManagementService so BOTH channels reach
 * the same one: the panel's sparse PATCH and — since the user directive
 * 2026-07-31 that made the create form mirror the panel — the initial values
 * submitted with POST /api/request-management.
 *
 * Validation runs against the applicable set resolved for the opportunity AS
 * IT IS when this is called (per-code applicability/type/required,
 * AttributeValueValidator, keyed `attribute_values.<code>` on failure): on
 * create that is the set the just-inserted product lines produce, i.e. exactly
 * the set the create form rendered its fields from.
 *
 * The merge is sparse in both channels: a code the payload leaves out keeps
 * its persisted value (on create there is none, so the map is simply what was
 * submitted).
 */
final class RequestAttributeValueWriter
{
    public function __construct(
        private readonly ApplicableAttributesResolver $attributesResolver,
        private readonly AttributeValueValidator $validator,
        private readonly AttributeValueNormalizer $normalizer,
    ) {}

    /**
     * Merges $submitted into the opportunity's map IN MEMORY — the caller
     * saves. Reports into $changed/$old so the caller's audit entry carries
     * the same shape the automatic model log would have (the column is NOT
     * fillable, so Spatie's dirty-diff never sees it).
     *
     * @param  array<string, mixed>  $submitted
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     *
     * @throws ValidationException a submitted code is not applicable, has the wrong type, or a required one is empty
     */
    public function apply(Opportunity $opportunity, array $submitted, array &$changed, array &$old): void
    {
        $applicable = $this->attributesResolver->resolve($opportunity);
        $validated = $this->validator->validate($applicable, $submitted);
        $normalized = $this->normalizer->normalize($applicable, $validated);

        $current = $opportunity->attribute_values ?? [];
        $merged = array_merge($current, $normalized);

        if ($merged === $current) {
            return;
        }

        $old['attribute_values'] = $current;
        // `attribute_values` is NOT in Opportunity::$fillable (D-4 mass-
        // assignment guard): forceFill is the deliberate, single write path.
        $opportunity->forceFill(['attribute_values' => $merged]);
        $changed['attribute_values'] = $merged;
    }
}
