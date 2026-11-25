<?php

declare(strict_types=1);

namespace App\Http\Requests\AiAssistant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PUT /api/ai-assistant/settings (spec 0433). Authorization is the `super-admin`
 * route middleware; whether the model is in the OpenRouter list is checked by
 * AiAssistantSettingsService.
 */
class UpdateAiAssistantSettingsRequest extends FormRequest
{
    public const string MODEL_ID_PATTERN = '/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.:-]+$/';

    private const array SORTS = ['throughput', 'price', 'latency'];

    private const string MAX_CAP = '9999999999.99';

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
            'enabled' => ['sometimes', 'boolean'],
            'model_id' => ['sometimes', 'string', 'max:120', 'regex:'.self::MODEL_ID_PATTERN],
            'instructions' => ['sometimes', 'nullable', 'string', 'max:'.config('ai-assistant.settings.instructions_max_chars')],
            'spend_cap_person_usd' => ['sometimes', 'nullable', 'decimal:0,2', 'min:0', 'max:'.self::MAX_CAP],
            'spend_cap_total_usd' => ['sometimes', 'nullable', 'decimal:0,2', 'min:0', 'max:'.self::MAX_CAP],
            'routing' => ['sometimes', 'array'],
            'routing.zdr' => ['sometimes', 'boolean'],
            'routing.data_collection' => ['sometimes', Rule::in(['deny', 'allow'])],
            'routing.require_parameters' => ['sometimes', 'boolean'],
            'routing.sort' => ['sometimes', Rule::in(self::SORTS)],
        ];
    }
}
