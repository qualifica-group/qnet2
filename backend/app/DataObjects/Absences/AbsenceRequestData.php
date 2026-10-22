<?php

declare(strict_types=1);

namespace App\DataObjects\Absences;

use App\Enums\AbsenceDayPart;

/**
 * Validated write payload of an absence request (POST/PATCH /api/absence-requests).
 * `requestedMinutes` is only meaningful for an hourly request: a half day
 * (`dayPart`) gets its minutes from the server (spec 0304 D-8).
 */
final readonly class AbsenceRequestData
{
    public function __construct(
        public int $absenceTypeId,
        public bool $isFullDay,
        public string $startsOn,
        public string $endsOn,
        public ?int $requestedMinutes,
        public ?string $note,
        public ?AbsenceDayPart $dayPart = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        $isFullDay = (bool) $data['is_full_day'];
        $dayPart = ! $isFullDay && ! empty($data['day_part']) ? AbsenceDayPart::from($data['day_part']) : null;

        return new self(
            absenceTypeId: (int) $data['absence_type_id'],
            isFullDay: $isFullDay,
            startsOn: (string) $data['starts_on'],
            endsOn: $isFullDay ? (string) $data['ends_on'] : (string) $data['starts_on'],
            requestedMinutes: $isFullDay || $dayPart !== null || ! isset($data['requested_minutes']) ? null : (int) $data['requested_minutes'],
            note: isset($data['note']) && $data['note'] !== '' ? (string) $data['note'] : null,
            dayPart: $dayPart,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'absence_type_id' => $this->absenceTypeId,
            'is_full_day' => $this->isFullDay,
            'starts_on' => $this->startsOn,
            'ends_on' => $this->endsOn,
            'requested_minutes' => $this->requestedMinutes,
            'day_part' => $this->dayPart,
            'note' => $this->note,
        ];
    }
}
