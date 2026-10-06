<?php

namespace App\Http\Requests\ProformaRequests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the only client-supplied field of a proforma request (spec 0193):
 * the note for Accounting, on both creation and update. Authorization stays in
 * the controller via the Policies.
 */
class ProformaRequestNoteRequest extends FormRequest
{
    public const int NOTE_MAX_LENGTH = 5000;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'max:'.self::NOTE_MAX_LENGTH],
        ];
    }

    public function note(): string
    {
        return $this->safe()->only(['note'])['note'];
    }
}
