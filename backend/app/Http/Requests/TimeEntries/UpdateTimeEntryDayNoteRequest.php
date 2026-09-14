<?php

declare(strict_types=1);

namespace App\Http\Requests\TimeEntries;

use App\DataObjects\TimeEntries\UpdateDayNoteData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT /api/time-entries/day-notes (spec 0122,
 * data_contract). `note` is deliberately `nullable` with no `required`
 * counterpart: an empty/blank/absent note is a legitimate way to CLEAR the
 * day's note (AC-021) — TimeEntryDayNoteService trims and deletes on empty,
 * it never rejects the request.
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller — see TimeEntryDayNoteController).
 */
class UpdateTimeEntryDayNoteRequest extends FormRequest
{
    private const int NOTE_MAX = 5000;

    private const string DATE_FORMAT = 'Y-m-d';

    public function authorize(): bool
    {
        // Authorization handled in the controller.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
            'date' => ['required', 'date_format:'.self::DATE_FORMAT],
            'note' => ['sometimes', 'nullable', 'string', 'max:'.self::NOTE_MAX],
        ];
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateDayNoteData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateDayNoteData::fromValidated($validated);
    }
}
