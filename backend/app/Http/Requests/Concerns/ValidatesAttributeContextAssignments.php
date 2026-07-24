<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cross-row invariant for the `attributes[]` payload of the product-category
 * write endpoints (spec 0061): (attribute_id, context) may not repeat — the
 * SAME attribute assigned twice to the SAME section is a payload mistake,
 * while assigning it once per context (Product AND Opportunity) is exactly
 * the two-pivot-row model this feature introduces, so plain Eloquent
 * `distinct` on `attribute_id` alone (the pre-0061 rule) would wrongly reject
 * that legitimate case. Mirrors ValidatesProductLines' cross-row pattern:
 * base per-row rules stay in each request's rules(), this only adds the
 * pair-uniqueness check, called from withValidator().
 *
 * @phpstan-require-extends FormRequest
 */
trait ValidatesAttributeContextAssignments
{
    protected function validateAttributeContextAssignments(Validator $validator): void
    {
        $rows = $this->input('attributes');

        if (! is_array($rows)) {
            return;
        }

        $seen = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row) || ! isset($row['attribute_id'], $row['context'])) {
                continue;
            }

            $key = $row['attribute_id'].'|'.$row['context'];

            if (isset($seen[$key])) {
                $validator->errors()->add("attributes.{$index}.attribute_id", 'This attribute is already assigned to this context.');

                continue;
            }

            $seen[$key] = true;
        }
    }
}
