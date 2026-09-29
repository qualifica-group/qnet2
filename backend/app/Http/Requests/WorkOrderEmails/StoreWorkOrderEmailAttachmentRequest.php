<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkOrderEmails;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the payload for
 * POST /api/work-orders/{workOrder}/emails/{email}/attachments (spec 0175,
 * D-7a): same server-side size/MIME allowlist as the generic
 * `POST /api/attachments` (StoreAttachmentRequest) — the frontend is never
 * the source of truth. The draft-only / total-size-limit rules are enforced
 * by OutboundEmailAttachmentService (a 409/422 business rule, not a plain
 * field validation).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('sendEmail', $workOrder)).
 */
class StoreWorkOrderEmailAttachmentRequest extends FormRequest
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
        $rules = [
            'file' => ['required', 'file', 'max:'.(int) config('attachments.max_size')],
        ];

        $allowedMimeTypes = (array) config('attachments.allowed_mime_types');

        if ($allowedMimeTypes !== []) {
            $rules['file'][] = 'mimetypes:'.implode(',', $allowedMimeTypes);
        }

        return $rules;
    }
}
