<?php

declare(strict_types=1);

namespace App\Http\Requests\AiAssistant;

use App\Http\Controllers\Abstract\BaseApiController;
use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /api/ai-assistant/models (spec 0433): search and window over the cached list.
 */
class IndexAiAssistantModelsRequest extends FormRequest
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
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'offset' => ['sometimes', 'integer', 'min:0'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.BaseApiController::MAX_LIMIT],
        ];
    }
}
