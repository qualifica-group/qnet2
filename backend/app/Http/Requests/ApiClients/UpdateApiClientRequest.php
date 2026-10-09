<?php

namespace App\Http\Requests\ApiClients;

use App\Models\ApiClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PUT|PATCH /api/api-clients/{apiClient} (spec 0209): same rules as creation,
 * every field optional, name uniqueness ignoring the client itself.
 */
class UpdateApiClientRequest extends FormRequest
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
        /** @var ApiClient $client */
        $client = $this->route('apiClient');

        return [
            'name' => ['sometimes', 'string', 'max:120', Rule::unique(ApiClient::class, 'name')->ignore($client->getKey())],
            ...StoreApiClientRequest::sharedRules(),
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
