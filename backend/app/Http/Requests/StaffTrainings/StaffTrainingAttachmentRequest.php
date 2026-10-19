<?php

namespace App\Http\Requests\StaffTrainings;

use App\Services\StaffTrainings\StaffTrainingVisibility;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload of POST /api/staff-trainings/{staffTraining}/attachment (spec 0284
 * D-3): one certificate, pdf or image. The extension must agree with the
 * detected MIME type. A training the actor cannot see is a 404; seeing it
 * without `manage` is a 403.
 */
class StaffTrainingAttachmentRequest extends FormRequest
{
    public const string ALLOWED_EXTENSIONS = 'pdf,jpg,jpeg,png';

    public function authorize(): bool
    {
        $visibility = app(StaffTrainingVisibility::class);
        $visibility->assertCanView($this->user(), $this->route('staffTraining'));

        return $visibility->canManage($this->user());
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
