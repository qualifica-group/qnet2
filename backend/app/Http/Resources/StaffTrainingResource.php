<?php

namespace App\Http\Resources;

use App\Models\Attachment;
use App\Models\StaffTraining;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Projection of a StaffTraining (spec 0284). Expects the relations loaded by
 * StaffTrainingService::RELATIONS.
 *
 * @mixin StaffTraining
 */
class StaffTrainingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => ['id' => $this->user->id, 'name' => $this->user->name],
            'course' => [
                'id' => $this->course->id,
                'name' => $this->course->name,
                'validity_months' => $this->course->validity_months,
            ],
            'completed_on' => $this->completed_on->format('Y-m-d'),
            'expires_on' => $this->expires_on?->format('Y-m-d'),
            'outcome' => $this->outcome->value,
            'notes' => $this->notes,
            'attachment' => $this->attachmentSummary(),
            'recorded_by' => $this->recordedBy === null ? null : ['id' => $this->recordedBy->id, 'name' => $this->recordedBy->name],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int, name: string, size: int}|null
     */
    private function attachmentSummary(): ?array
    {
        /** @var Attachment|null $attachment */
        $attachment = $this->attachments->first();

        return $attachment === null
            ? null
            : ['id' => $attachment->id, 'name' => $attachment->original_name, 'size' => $attachment->size];
    }
}
