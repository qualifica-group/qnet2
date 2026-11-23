<?php

declare(strict_types=1);

namespace Integrations\Formalab\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Integrations\Formalab\Support\FormalabField;

/**
 * PUT /api/integrations/formalab/product-categories/{productCategory}: the
 * shape of the settings; what the category can hold is checked by
 * CategorySettingsService. Authorization is the category's `update` policy,
 * checked in the controller.
 */
class UpdateCategorySettingsRequest extends FormRequest
{
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
            'enabled' => ['sometimes', 'boolean'],
            'field_map' => ['present', 'nullable', 'array', 'max:'.count(FormalabField::cases())],
            'field_map.*.formalab_field' => ['required', 'string', 'distinct', Rule::enum(FormalabField::class)],
            'field_map.*.attribute_code' => ['required', 'string', 'max:255', 'distinct'],
        ];
    }

    public function enabled(): ?bool
    {
        return $this->has('enabled') ? $this->boolean('enabled') : null;
    }

    /**
     * @return list<array{formalab_field: string, attribute_code: string}>|null
     */
    public function fieldMap(): ?array
    {
        $map = $this->validated('field_map');

        return $map === null ? null : array_map(static fn (array $pair): array => [
            'formalab_field' => (string) $pair['formalab_field'],
            'attribute_code' => (string) $pair['attribute_code'],
        ], array_values($map));
    }
}
