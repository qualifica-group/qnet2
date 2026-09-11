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
 * 0118, D-10..D-12).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('requestUpdate', $task)).
 *
 * The D-11 membership rule on `recipient_ids` — every id must be an assignee
 * OR a watcher of THIS Task; creatore/richiedente are never candidates —
 * lives HERE rather than in App\Services\Tasks\TaskActionService, unlike
 * every other guard of this module (constraint: the Service re-asserts what
 * the flag suggests). The other guards all evaluate the RESULTING state of a
 * WRITE (TaskWatcherOverlapGuard, TaskHierarchyGuard, ...), which only the
 * Service can compute once submitted keys are reconciled with persisted
 * ones. This endpoint writes NOTHING to the Task at all, so there is no
 * "resulting record" for a Service-level guard to evaluate — it is plain
 * validation of the submitted payload against a record the FormRequest
 * already holds from the route (the same shape UpdateTaskRequest's
 * authorizationModel() reads), so a field-scoped 422 belongs here.
 */
class RequestTaskUpdateRequest extends FormRequest
{
    private const int MESSAGE_MAX = 2000;

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
            'recipient_ids' => ['required', 'array', 'min:1'],
            'recipient_ids.*' => ['integer', Rule::exists('users', 'id')],
            'message' => ['sometimes', 'nullable', 'string', 'max:'.self::MESSAGE_MAX],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->assertRecipientsAreMembers($validator);
        });
    }

    /**
     * D-11: the set of valid recipients is exactly the Task's assignees plus
     * its watchers. A submitted id that is neither fails field-scoped on
     * `recipient_ids`, whether or not it exists as a user (AC-050).
     */
    private function assertRecipientsAreMembers(Validator $validator): void
    {
        $recipientIds = $this->input('recipient_ids');

        if (! is_array($recipientIds) || $recipientIds === []) {
            return;
        }

        $task = $this->task();
        $memberIds = [
            ...$task->assignees()->pluck('users.id')->all(),
            ...$task->watchers()->pluck('users.id')->all(),
        ];

        $strangers = array_diff(array_map('intval', $recipientIds), $memberIds);

        if ($strangers !== []) {
            $validator->errors()->add(
                'recipient_ids',
                __('Each recipient must be an assignee or a watcher of this task.'),
            );
        }
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
