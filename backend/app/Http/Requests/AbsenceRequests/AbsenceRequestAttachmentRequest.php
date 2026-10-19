<?php

namespace App\Http\Requests\AbsenceRequests;

use App\Services\Absences\Requests\AbsenceRequestVisibility;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload of POST /api/absence-requests/{absenceRequest}/attachment (spec 0278,
 * D-12): one optional certificate, pdf or image. The extension must agree with
 * the detected MIME type. A request the actor cannot see is a 404.
 */
class AbsenceRequestAttachmentRequest extends FormRequest
{
    public const string ALLOWED_EXTENSIONS = 'pdf,jpg,jpeg,png';

    public function authorize(): bool
    {
        app(AbsenceRequestVisibility::class)->assertCanView($this->user(), $this->route('absenceRequest'));

        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:'.self::ALLOWED_EXTENSIONS,
                'extensions:'.self::ALLOWED_EXTENSIONS,
                'max:'.(int) config('attachments.max_size'),
            ],
        ];
    }
}
