<?php

declare(strict_types=1);

namespace App\Http\Requests\SupportRequests;

use App\DataObjects\SupportRequests\SupportRequestData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates POST /api/support-requests (spec 0334). Open to any authenticated
 * user (D-7); every Task setting is `prohibited` (D-10): it only comes from
 * the topic's configuration.
 */
class StoreSupportRequestRequest extends FormRequest
{
    private const int DESCRIPTION_MAX = 5000;

    private const int MAX_ATTACHMENTS = 5;

    private const int PAGE_URL_MAX = 2048;

    private const int USER_AGENT_MAX = 500;

    /** Task keys the user may never submit. */
    private const array PROHIBITED = [
        'title', 'assignee_ids', 'watcher_ids', 'requester_id', 'task_type_id',
        'task_category_id', 'task_priority_id', 'task_importance_id', 'end_date', 'is_private',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $fileRules = [
            'file',
            'max:'.(int) config('attachments.max_size'),
            'extensions:'.implode(',', (array) config('attachments.allowed_extensions')),
        ];
        $mimeTypes = (array) config('attachments.allowed_mime_types');

        if ($mimeTypes !== []) {
            $fileRules[] = 'mimetypes:'.implode(',', $mimeTypes);
        }

        return [
            'support_topic_id' => ['required', 'integer', Rule::exists('support_topics', 'id')->where('is_active', true)],
            'description' => ['required', 'string', 'max:'.self::DESCRIPTION_MAX],
            'is_urgent' => ['sometimes', 'boolean'],
            'attachments' => ['sometimes', 'array', 'max:'.self::MAX_ATTACHMENTS],
            'attachments.*' => $fileRules,
            'page_url' => ['sometimes', 'nullable', 'string', 'max:'.self::PAGE_URL_MAX],
            'user_agent' => ['sometimes', 'nullable', 'string', 'max:'.self::USER_AGENT_MAX],
            ...array_fill_keys(self::PROHIBITED, ['prohibited']),
        ];
    }

    public function toData(): SupportRequestData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return SupportRequestData::fromValidated($validated);
    }
}
