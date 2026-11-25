<?php

declare(strict_types=1);

namespace App\Http\Requests\AiAssistant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /api/ai-assistant/models/providers (spec 0433): the id travels in the query
 * because it contains a slash; the pattern keeps anything else out of the URL
 * built towards OpenRouter.
 */
class AiAssistantModelProvidersRequest extends FormRequest
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
            'model_id' => ['required', 'string', 'max:120', 'regex:'.UpdateAiAssistantSettingsRequest::MODEL_ID_PATTERN],
        ];
    }
}
