<?php

declare(strict_types=1);

namespace Integrations\Formalab\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WebhookClassesRequest extends FormRequest
{
    /** Authenticated by the shared token (InboundWebhookGuard), not by a user. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'edition_ids' => ['required', 'array', 'min:1', 'max:50'],
            'edition_ids.*' => ['required', 'integer', 'min:1'],
        ];
    }
}
