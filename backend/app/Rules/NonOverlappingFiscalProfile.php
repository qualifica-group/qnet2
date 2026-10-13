<?php

namespace App\Rules;

use App\Models\AgentFiscalProfile;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Throwable;

/**
 * Rejects a profile whose validity range [valid_from, valid_until] (until null
 * = open-ended) intersects another profile of the same referent (spec 0212,
 * D-8). Attached to `valid_from`; the other bounds are passed in, so a payload
 * the field rules already refuse (bad referent, bad dates) is left to them.
 */
final class NonOverlappingFiscalProfile implements ValidationRule
{
    public function __construct(
        private readonly mixed $referentId,
        private readonly mixed $validUntil,
        private readonly ?int $ignoreProfileId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $from = $this->parseDate($value);
        $hasUntil = $this->validUntil !== null && $this->validUntil !== '';
        $until = $hasUntil ? $this->parseDate($this->validUntil) : null;

        if ($from === null || ! is_numeric($this->referentId) || ($hasUntil && $until === null)) {
            return;
        }

        if (self::overlaps((int) $this->referentId, $from, $until, $this->ignoreProfileId)) {
            $fail('agent_fiscal_profiles.overlapping_validity')->translate();
        }
    }

    /**
     * Whether another profile of the referent intersects [$from, $until]
     * (null = open-ended). Shared with the service's locked re-check.
     */
    public static function overlaps(int $referentId, string $from, ?string $until, ?int $ignoreProfileId = null): bool
    {
        return AgentFiscalProfile::query()
            ->where('referent_id', $referentId)
            ->when($ignoreProfileId !== null, fn ($query) => $query->whereKeyNot($ignoreProfileId))
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', $from))
            ->when($until !== null, fn ($query) => $query->whereDate('valid_from', '<=', $until))
            ->exists();
    }

    private function parseDate(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
