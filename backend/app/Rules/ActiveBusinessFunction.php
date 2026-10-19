<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\BusinessFunction;
use App\Services\BusinessFunctions\BusinessFunctionActivity;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The business function id must exist and be EFFECTIVELY active (spec 0280):
 * its own `is_active` and every ancestor's.
 *
 * REPLACES `exists:business_functions,id` (one message per field).
 * `$exemptIds` carries spec 0280 D-2: a function already linked to the record
 * being updated stays valid even once deactivated.
 */
final class ActiveBusinessFunction implements ValidationRule
{
    /** Resolved once per rule instance: a request validates many ids with one rule. */
    private ?BusinessFunctionActivity $activity = null;

    /**
     * @param  array<int, int>  $exemptIds  ids already linked to the record being updated
     */
    public function __construct(private readonly array $exemptIds = []) {}

    /**
     * @param  Closure(string): void  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value) || ! BusinessFunction::query()->whereKey((int) $value)->exists()) {
            $fail(__('The selected business function is invalid.'));

            return;
        }

        $businessFunctionId = (int) $value;

        if (! in_array($businessFunctionId, $this->exemptIds, true) && ! $this->activity()->isActive($businessFunctionId)) {
            $fail(__('This business function is not active.'));
        }
    }

    private function activity(): BusinessFunctionActivity
    {
        return $this->activity ??= app(BusinessFunctionActivity::class);
    }
}
