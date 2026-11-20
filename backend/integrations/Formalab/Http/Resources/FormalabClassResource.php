<?php

declare(strict_types=1);

namespace Integrations\Formalab\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Integrations\Formalab\Models\FormalabClass;

/**
 * @mixin FormalabClass
 */
class FormalabClassResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'edition_id' => $this->edition_id,
            'label' => $this->label,
            'code' => $this->code,
            'title' => $this->title,
            'status' => $this->status,
            'course_status' => $this->course_status,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'total_hours' => $this->total_hours,
            'exam_date' => $this->exam_date?->format('Y-m-d'),
            'site_name' => $this->site_name,
            'site_city' => $this->site_city,
            'learners_count' => $this->learners_count,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
        ];
    }
}
