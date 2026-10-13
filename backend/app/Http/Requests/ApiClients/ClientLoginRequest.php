<?php

namespace App\Http\Requests\ApiClients;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/auth/client-login (spec 0210). Authenticated by the client key
 * (route middleware); the controller verifies the Bearer is a client key.
 */
class ClientLoginRequest extends FormRequest
{
    private const string DEFAULT_DEVICE_NAME = 'api-client';

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
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:120'],
        ];
    }

    public function deviceName(): string
    {
        return $this->input('device_name', self::DEFAULT_DEVICE_NAME);
    }
}
