<?php

declare(strict_types=1);

namespace App\Http\Requests\DocumentRequests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Fields shared by the creation and the edit of a document request (spec 0428).
 * Each subclass authorizes before validating, so a user without the permission
 * gets 403 and never learns the rules (DocumentRequestPolicy + the work order).
 */
abstract class SaveDocumentRequestRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'due_date' => $this->dueDateRules(),
            'recipient_email' => ['required', 'string', 'email', 'max:'.(int) config('archivio-smart.max_length.email')],
            'message' => ['nullable', 'string', 'max:'.(int) config('archivio-smart.max_length.request_message')],
            'send' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function dueDateRules(): array
    {
        return ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'due_date' => __('archivio_smart.attributes.due_date'),
            'recipient_email' => __('archivio_smart.attributes.recipient_email'),
            'message' => __('archivio_smart.attributes.message'),
            'document_checklist_id' => __('archivio_smart.attributes.document_checklist_id'),
        ];
    }

    /**
     * @return array{due_date: string|null, recipient_email: string, message: string|null}
     */
    public function requestData(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return [
            'due_date' => $validated['due_date'] ?? null,
            'recipient_email' => (string) $validated['recipient_email'],
            'message' => $validated['message'] ?? null,
        ];
    }

    /**
     * Sent by default (§4).
     */
    public function shouldSend(): bool
    {
        return (bool) ($this->validated('send') ?? true);
    }
}
