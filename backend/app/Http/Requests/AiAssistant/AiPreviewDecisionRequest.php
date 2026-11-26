<?php

declare(strict_types=1);

namespace App\Http\Requests\AiAssistant;

use App\Models\AiConversation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Body of `confirm` and `cancel` (spec 0433): the identifier of the preview and nothing else. The
 * ownership of the conversation, of the turn and of the preview is checked by the controller and the service.
 */
class AiPreviewDecisionRequest extends FormRequest
{
    public const int PREVIEW_ID_MAX_CHARS = 128;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', AiConversation::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return ['preview_id' => ['required', 'string', 'max:'.self::PREVIEW_ID_MAX_CHARS]];
    }
}
