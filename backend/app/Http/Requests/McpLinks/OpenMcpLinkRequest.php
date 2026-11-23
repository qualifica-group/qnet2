<?php

namespace App\Http\Requests\McpLinks;

use Illuminate\Foundation\Http\FormRequest;

class OpenMcpLinkRequest extends FormRequest
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
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'mcp_client' => ['required', 'string', 'max:'.config('mcp-link.mcp_client_max_length')],
        ];
    }
}
