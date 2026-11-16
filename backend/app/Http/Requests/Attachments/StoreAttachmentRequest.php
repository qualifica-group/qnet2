<?php

namespace App\Http\Requests\Attachments;

use App\DataObjects\Attachments\CreateAttachmentData;
use App\Rules\AllowedAttachmentFile;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/attachments.
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('create', Attachment::class)). The file is constrained by the
 * server-side size and MIME allowlists from config/attachments.php — the
 * frontend is never trusted. The polymorphic owner, if provided, is validated
 * against the attachable_types allowlist and must actually exist.
 */
class StoreAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled in the controller via the AttachmentPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.(int) config('attachments.max_size'), new AllowedAttachmentFile],
            'collection' => ['sometimes', 'nullable', 'string', 'max:255'],
            'attachable_type' => ['sometimes', 'required_with:attachable_id', 'string', Rule::in($this->allowedAliases())],
            'attachable_id' => ['sometimes', 'required_with:attachable_type', 'integer', 'min:1'],
        ];
    }

    /**
     * Verify the polymorphic owner actually exists, after the base rules pass.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $alias = $this->input('attachable_type');
            $id = $this->input('attachable_id');

            if ($alias === null || $id === null) {
                return;
            }

            $modelClass = $this->allowedTypes()[$alias] ?? null;

            if ($modelClass === null) {
                return; // already rejected by the Rule::in on attachable_type
            }

            if (! $modelClass::query()->whereKey($id)->exists()) {
                $validator->errors()->add('attachable_id', 'The selected attachable owner does not exist.');
            }
        });
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service). The public alias is resolved to the concrete model class here,
     * at the boundary, so the service deals only with real class names.
     */
    public function toData(): CreateAttachmentData
    {
        $alias = $this->input('attachable_type');

        return new CreateAttachmentData(
            file: $this->file('file'),
            collection: $this->input('collection'),
            attachableType: $alias !== null ? ($this->allowedTypes()[$alias] ?? null) : null,
            attachableId: $this->filled('attachable_id') ? (int) $this->input('attachable_id') : null,
        );
    }

    /**
     * Alias => model class allowlist (config/attachments.php).
     *
     * @return array<string, class-string>
     */
    private function allowedTypes(): array
    {
        /** @var array<string, class-string> $types */
        $types = (array) config('attachments.attachable_types');

        return $types;
    }

    /**
     * @return array<int, string>
     */
    private function allowedAliases(): array
    {
        return array_keys($this->allowedTypes());
    }
}
