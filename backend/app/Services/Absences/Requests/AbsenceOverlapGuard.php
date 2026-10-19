<?php

declare(strict_types=1);

namespace App\Services\Absences\Requests;

use App\Enums\AbsenceRequestStatus;
use App\Models\AbsenceRequest;
use Illuminate\Validation\ValidationException;

/**
 * Two requests of the same person that are not rejected or cancelled never
 * overlap (spec 0278, D-6): a 422 on `starts_on`.
 */
final class AbsenceOverlapGuard
{
    public function assertFree(int $userId, string $startsOn, string $endsOn, ?int $ignoreRequestId = null): void
    {
        $overlaps = AbsenceRequest::query()
            ->where('user_id', $userId)
            ->whereNotIn('status', [AbsenceRequestStatus::Rejected->value, AbsenceRequestStatus::Cancelled->value])
            ->where('starts_on', '<=', $endsOn)
            ->where('ends_on', '>=', $startsOn)
            ->when($ignoreRequestId !== null, static fn ($query) => $query->whereKeyNot($ignoreRequestId))
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages(['starts_on' => [__('absence_requests.overlap')]]);
        }
    }
}
