<?php

declare(strict_types=1);

namespace Integrations\Formalab\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClassSearchRequest extends FormRequest
{
    public const int DEFAULT_LIMIT = 20;

    public const int MAX_LIMIT = 50;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('work-orders.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
        ];
    }
}
