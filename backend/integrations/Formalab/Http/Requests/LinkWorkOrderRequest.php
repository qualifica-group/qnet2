<?php

declare(strict_types=1);

namespace Integrations\Formalab\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LinkWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->route('workOrder'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['edition_id' => ['required', 'integer', 'min:1']];
    }
}
