<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\ProductCategory;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Key-range invariant for the `manager_labels` payload of the
 * product-category write endpoints (spec 0080): every KEY must be an
 * integer within 1..ProductCategory::MANAGER_LABEL_MAX_POSITION (the G.A.
 * level position) — "0", "5" or a non-numeric key is rejected. Mirrors
 * ValidatesAttributeContextAssignments' pattern: base per-value rules stay
 * in each request's rules(), this only adds the key check, called from
 * withValidator().
 *
 * @phpstan-require-extends FormRequest
 */
trait ValidatesManagerLabelPositions
{
    protected function validateManagerLabelPositions(Validator $validator): void
    {
        $labels = $this->input('manager_labels');

        if (! is_array($labels)) {
            return;
        }

        foreach (array_keys($labels) as $key) {
            $isValidPosition = ctype_digit((string) $key)
                && (int) $key >= 1
                && (int) $key <= ProductCategory::MANAGER_LABEL_MAX_POSITION;

            if (! $isValidPosition) {
                $validator->errors()->add("manager_labels.{$key}", 'Invalid manager label position.');
            }
        }
    }
}
