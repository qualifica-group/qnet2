<?php

namespace App\Http\Requests\McpLinks;

use Illuminate\Foundation\Http\FormRequest;

class ExchangeMcpLinkRequest extends FormRequest
{
    /**
     * Authorised by the route: only the API client key reaches it.
     */
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
            'credential' => ['required', 'string', 'max:128'],
        ];
    }
}
