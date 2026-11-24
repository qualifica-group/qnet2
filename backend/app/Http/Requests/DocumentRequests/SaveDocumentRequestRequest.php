<?php

declare(strict_types=1);

namespace App\Http\Requests\DocumentRequests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Fields shared by the creation and the edit of a document request (spec 0428).
 * Authorization is handled in the controller (DocumentRequestPolicy + the work order).
 */
abstract class SaveDocumentRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'due_date' => $this->dueDateRules(),
            'recipient_email' => ['nullable', 'string', 'email', 'max:'.(int) config('archivio-smart.max_length.email')],
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
     * @return array{due_date: string|null, recipient_email: string|null, message: string|null}
     */
    public function requestData(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return [
            'due_date' => $validated['due_date'] ?? null,
            'recipient_email' => $validated['recipient_email'] ?? null,
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
