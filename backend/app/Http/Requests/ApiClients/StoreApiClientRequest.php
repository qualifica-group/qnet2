<?php

namespace App\Http\Requests\ApiClients;

use App\Models\ApiClient;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/api-clients (specs 0209, 0210). Authorization is enforced by the
 * controller through ApiClientPolicy; rules are shared with the update request.
 */
class StoreApiClientRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120', Rule::unique(ApiClient::class, 'name')],
            ...self::sharedRules(),
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public static function sharedRules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:2000'],
            'rate_limit_per_minute' => ['nullable', 'integer', 'between:1,'.(int) config('external-api.rate_limit.max')],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
