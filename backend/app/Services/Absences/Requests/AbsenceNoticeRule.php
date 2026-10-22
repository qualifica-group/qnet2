<?php

declare(strict_types=1);

namespace App\Services\Absences\Requests;

use App\Models\AbsenceType;
use Carbon\CarbonImmutable;

/**
 * The short-notice rule of an absence request (spec 0304 D-10), in one place
 * for the submission (which freezes it) and the preview: the calendar days
 * between today and the start are fewer than the notice of the type. A request
 * entered by someone else (the HR office) is never short.
 */
final class AbsenceNoticeRule
{
    public function isShort(AbsenceType $type, string $startsOn, bool $enteredByAnother, ?CarbonImmutable $today = null): bool
    {
        if ($type->min_notice_days === null || $enteredByAnother) {
            return false;
        }

        $today ??= CarbonImmutable::today();

        return (int) $today->diffInDays(CarbonImmutable::parse($startsOn)->startOfDay(), false) < $type->min_notice_days;
    }
}
