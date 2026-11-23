<?php

namespace App\Http\Requests\McpLinks;

use Illuminate\Foundation\Http\FormRequest;

class RevokeMcpLinkRequest extends FormRequest
{
    /**
     * Authorised by the route: only the API client key reaches it.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The link comes from the route; the body carries nothing.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}
