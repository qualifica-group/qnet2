<?php

declare(strict_types=1);

namespace App\Http\Requests\TimeEntries;

use App\DataObjects\TimeEntries\TimeEntryData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/tasks/{task}/time-entries (spec 0122,
 * data_contract, D-9). Deliberately narrower than StoreTimeEntryRequest: no
 * `title`, no `user_id`, no record links — the editor "Nuovo intervallo"
 * carries none of them (D-9), all five are DERIVED server-side from the
 * route's Task and the authenticated actor (TimeEntryData::forTask()).
 *
 * Authorization (`time-entries.create` AND
 * `TaskAbilityResolver::canComplete()`) is intentionally NOT handled here —
 * it stays in TaskTimeEntryController::store, which has the Task in scope.
 */
class StoreTaskTimeEntryRequest extends FormRequest
{
    private const int NOTES_MAX = 5000;

    private const string TIME_FORMAT = 'H:i';

    private const string DATE_FORMAT = 'Y-m-d';

    public function authorize(): bool
    {
        // Authorization handled in the controller (time-entries.create +
        // TaskAbilityResolver::canComplete).
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:'.self::DATE_FORMAT],
            'task_type_id' => [
                'required',
                'integer',
                Rule::exists('task_types', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'start_time' => ['nullable', 'string', 'date_format:'.self::TIME_FORMAT, 'required_with:end_time'],
            'end_time' => ['nullable', 'string', 'date_format:'.self::TIME_FORMAT, 'required_with:start_time', 'after:start_time'],
            'minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:'.self::NOTES_MAX],
        ];
    }

    /**
     * The validated payload as a typed DTO, with `$taskId` injected from the
     * route (never from the payload — see the class docblock).
     */
    public function toData(int $taskId): TimeEntryData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return TimeEntryData::forTask($validated, $taskId);
    }
}
