<?php

declare(strict_types=1);

namespace App\Http\Requests\Tasks;

use App\DataObjects\Tasks\RequestTaskUpdateData;
use App\Models\Task;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/tasks/{task}/request-update (spec
 * 0153, D-14, superseding spec 0118 D-10..D-12/spec 0126 D-6).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('requestUpdate', $task)).
 *
 * `target` replaces the old `recipient_ids`: a fixed group
 * (`assignees`/`observers`/`all`) rather than a caller-picked list, so the
 * D-11-style "no automatic audience" guarantee moves from "every id must be
 * a member" to "the group must resolve to at least one member" — asserted
 * HERE against the Task's CURRENT pivots, the same reasoning
 * RequestTaskUpdateRequest always used for a payload this endpoint writes
 * nothing to (no "resulting record" for a Service-level guard to evaluate).
 * `message` is now REQUIRED (3..2000, D-14) rather than optional.
 */
class RequestTaskUpdateRequest extends FormRequest
{
    private const int MESSAGE_MIN = 3;

    private const int MESSAGE_MAX = 2000;

    /** @var array<int, string> */
    private const array TARGETS = ['assignees', 'observers', 'all'];

    public function authorize(): bool
    {
        // Authorization handled in the controller via TaskPolicy::requestUpdate.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'target' => ['required', 'string', Rule::in(self::TARGETS)],
            'message' => ['required', 'string', 'min:'.self::MESSAGE_MIN, 'max:'.self::MESSAGE_MAX],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->assertTargetHasRecipients($validator);
        });
    }

    /**
     * D-14: a target that resolves to nobody (e.g. `observers` on a Task with
     * no watchers) 422s on `target` rather than silently sending nothing.
     */
    private function assertTargetHasRecipients(Validator $validator): void
    {
        $target = $this->input('target');

        if (! is_string($target) || ! in_array($target, self::TARGETS, true)) {
            return;
        }

        if ($this->recipientIdsFor($target) === []) {
            $validator->errors()->add(
                'target',
                __('There is no recipient for this target.'),
            );
        }
    }

    /**
     * @return array<int, int>
     */
    private function recipientIdsFor(string $target): array
    {
        $task = $this->task();
        $assigneeIds = $task->assignees()->pluck('users.id')->all();
        $watcherIds = $task->watchers()->pluck('users.id')->all();

        return match ($target) {
            'observers' => $watcherIds,
            'all' => array_values(array_unique([...$assigneeIds, ...$watcherIds])),
            default => $assigneeIds, // 'assignees'
        };
    }

    private function task(): Task
    {
        /** @var Task $task */
        $task = $this->route('task');

        return $task;
    }

    public function toData(): RequestTaskUpdateData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return RequestTaskUpdateData::fromValidated($validated);
    }
}
