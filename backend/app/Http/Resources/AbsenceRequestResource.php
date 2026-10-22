<?php

namespace App\Http\Resources;

use App\Models\AbsenceRequest;
use App\Models\Attachment;
use App\Services\Absences\Requests\AbsenceRequestCapabilities;
use App\Services\TimeEntries\DailyTargetResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Projection of an AbsenceRequest (spec 0278). Expects the relations loaded by
 * AbsenceRequestService::RELATIONS. `can` is computed for the requesting actor.
 *
 * @mixin AbsenceRequest
 */
class AbsenceRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => ['id' => $this->user->id, 'name' => $this->user->name],
            'absence_type' => [
                'id' => $this->absenceType->id,
                'name' => $this->absenceType->name,
                'deducts_balance' => (bool) $this->absenceType->deducts_balance,
                'requires_approval' => (bool) $this->absenceType->requires_approval,
                'color' => $this->absenceType->color,
            ],
            'status' => $this->status->value,
            'is_full_day' => $this->is_full_day,
            'day_part' => $this->day_part?->value,
            'is_short_notice' => $this->is_short_notice,
            'company_closure' => $this->companyClosure === null ? null : ['id' => $this->companyClosure->id, 'name' => $this->companyClosure->name],
            'daily_minutes' => app(DailyTargetResolver::class)->resolve($this->user->employment),
            'starts_on' => $this->starts_on->format('Y-m-d'),
            'ends_on' => $this->ends_on->format('Y-m-d'),
            'minutes' => $this->minutes,
            'requested_minutes' => $this->requested_minutes,
            'note' => $this->note,
            'rejection_reason' => $this->rejection_reason,
            'approvers' => $this->approvers->map(static fn ($approver): array => ['id' => $approver->id, 'name' => $approver->name])->values()->all(),
            'decided_by' => $this->decidedBy === null ? null : ['id' => $this->decidedBy->id, 'name' => $this->decidedBy->name],
            'decided_at' => $this->decided_at?->toIso8601String(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'attachment' => $this->attachmentSummary(),
            'can' => app(AbsenceRequestCapabilities::class)->for($request->user(), $this->resource),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int, name: string, size: int, mime_type: string}|null
     */
    private function attachmentSummary(): ?array
    {
        /** @var Attachment|null $attachment */
        $attachment = $this->attachments->first();

        return $attachment === null
            ? null
            : ['id' => $attachment->id, 'name' => $attachment->original_name, 'size' => $attachment->size, 'mime_type' => $attachment->mime_type];
    }
}
