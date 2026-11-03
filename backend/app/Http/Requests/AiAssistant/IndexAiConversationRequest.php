<?php

namespace App\Http\Requests\AiAssistant;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Models\AiConversation;
use Illuminate\Foundation\Http\FormRequest;

class IndexAiConversationRequest extends FormRequest
{
    public const int DEFAULT_LIMIT = 30;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', AiConversation::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $maxLimit = BaseApiController::MAX_LIMIT;

        return [
            'offset' => ['sometimes', 'integer', 'min:0'],
            'limit' => ['sometimes', 'integer', 'min:1', "max:{$maxLimit}"],
        ];
    }
}
