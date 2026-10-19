<?php

declare(strict_types=1);

namespace App\DataObjects\Absences;

/**
 * Validated write payload of an absence request (POST/PATCH /api/absence-requests).
 * `requestedMinutes` is only meaningful for an hourly request.
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
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        $isFullDay = (bool) $data['is_full_day'];

        return new self(
            absenceTypeId: (int) $data['absence_type_id'],
            isFullDay: $isFullDay,
            startsOn: (string) $data['starts_on'],
            endsOn: $isFullDay ? (string) $data['ends_on'] : (string) $data['starts_on'],
            requestedMinutes: $isFullDay || ! isset($data['requested_minutes']) ? null : (int) $data['requested_minutes'],
            note: isset($data['note']) && $data['note'] !== '' ? (string) $data['note'] : null,
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
            'note' => $this->note,
        ];
    }
}
