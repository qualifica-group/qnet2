<?php

declare(strict_types=1);

namespace App\Http\Requests\SupportTopics;

use App\DataObjects\SupportTopics\SupportTopicData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\SupportTopic;
use App\Support\BadgeTokens;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared validation of POST/PATCH /api/support-topics (spec 0334). The two
 * concrete requests differ only in the presence rule (`required` vs
 * `sometimes`), the name uniqueness scope and the model the field-permission
 * gate targets. Authorization stays in the controller (SupportTopicPolicy).
 *
 * A user cannot be both assignee and watcher of the same topic; on PATCH the
 * check runs on the RESULTING sets (submitted key, else the persisted pivot).
 */
abstract class SupportTopicRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    private const int NAME_MAX = 191;

    private const int DESCRIPTION_MAX = 500;

    private const int ICON_MAX = 64;

    private const int DAYS_MAX = 365;

    /**
     * @return array<int, string> 'required' (store) or 'sometimes' (update)
     */
    abstract protected function presence(): array;

    abstract protected function nameUniqueRule(): mixed;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $presence = $this->presence();
        $optional = ['sometimes', 'nullable'];
        $lookup = fn (string $table): array => [...$optional, 'integer', Rule::exists($table, 'id')];

        return [
            'name' => [...$presence, 'string', 'max:'.self::NAME_MAX, $this->nameUniqueRule()],
            'description' => [...$optional, 'string', 'max:'.self::DESCRIPTION_MAX],
            'icon' => [...$optional, 'string', 'max:'.self::ICON_MAX, Rule::in(BadgeTokens::icons())],
            'sort_order' => ['sometimes', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
            'task_type_id' => $lookup('task_types'),
            'task_category_id' => $lookup('task_categories'),
            'task_priority_id' => $lookup('task_priorities'),
            'urgent_task_priority_id' => $lookup('task_priorities'),
            'task_importance_id' => $lookup('task_importances'),
            'due_days' => [...$presence, 'integer', 'min:0', 'max:'.self::DAYS_MAX],
            'urgent_due_days' => [...$optional, 'integer', 'min:0', 'max:'.self::DAYS_MAX],
            'is_private' => ['sometimes', 'boolean'],
            'requires_closure_feedback' => ['sometimes', 'boolean'],
            'assignee_ids' => [...$presence, 'array', 'min:1'],
            'assignee_ids.*' => ['integer', Rule::exists('users', 'id')->where('is_active', true)],
            'watcher_ids' => ['sometimes', 'nullable', 'array'],
            'watcher_ids.*' => ['integer', Rule::exists('users', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $validator->errors()->hasAny(['assignee_ids', 'watcher_ids'])) {
                $this->assertDisjointUsers($validator);
            }

            $this->enforceFieldPermissions($validator);
        });
    }

    public function toData(): SupportTopicData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return SupportTopicData::fromValidated($validated);
    }

    protected function authorizationResource(): string
    {
        return 'support-topics';
    }

    private function assertDisjointUsers(Validator $validator): void
    {
        $topic = $this->route('supportTopic');

        $assignees = $this->has('assignee_ids')
            ? (array) $this->input('assignee_ids')
            : ($topic instanceof SupportTopic ? $topic->assignees()->pluck('users.id')->all() : []);
        $watchers = $this->has('watcher_ids')
            ? (array) $this->input('watcher_ids')
            : ($topic instanceof SupportTopic ? $topic->watchers()->pluck('users.id')->all() : []);

        $overlap = array_intersect(array_map('intval', $assignees), array_map('intval', $watchers));

        if ($overlap !== []) {
            $validator->errors()->add('watcher_ids', 'A watcher cannot also be an assignee of the same topic.');
        }
    }
}
