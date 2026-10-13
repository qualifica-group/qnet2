<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Sector;
use App\Services\Sectors\SectorActivity;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The sector id must exist and be EFFECTIVELY active (spec 0212): its own
 * `is_active` and every ancestor's.
 *
 * REPLACES `exists:sectors,id` (one message per field). `$exemptIds` carries
 * spec 0212 D-2: a sector already linked to the record being updated stays
 * valid even once deactivated.
 */
final class ActiveSector implements ValidationRule
{
    /** Resolved once per rule instance: a request validates many ids with one rule. */
    private ?SectorActivity $activity = null;

    /**
     * @param  array<int, int>  $exemptIds  ids already linked to the record being updated
     */
    public function __construct(private readonly array $exemptIds = []) {}

    /**
     * @param  Closure(string): void  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value) || ! Sector::query()->whereKey((int) $value)->exists()) {
            $fail(__('The selected sector is invalid.'));

            return;
        }

        $sectorId = (int) $value;

        if (! in_array($sectorId, $this->exemptIds, true) && ! $this->activity()->isActive($sectorId)) {
            $fail(__('This sector is not active.'));
        }
    }

    private function activity(): SectorActivity
    {
        return $this->activity ??= app(SectorActivity::class);
    }
}
