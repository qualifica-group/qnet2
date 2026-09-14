<?php

declare(strict_types=1);

namespace App\Http\Requests\TimeEntries;

use App\DataObjects\TimeEntries\TimeEntryData;
use Illuminate\Foundation\Http\FormRequest;

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
 *
 * `rules()` is TimeEntryValidationRules::rules() verbatim (spec 0123, D-3):
 * the SAME shape CompleteTaskRequest requires under `time_entry.*`, from the
 * one shared source, so the two can never drift apart (AC-009).
 */
class StoreTaskTimeEntryRequest extends FormRequest
{
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
        return TimeEntryValidationRules::rules();
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
