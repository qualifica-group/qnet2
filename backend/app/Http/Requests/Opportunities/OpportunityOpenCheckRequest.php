<?php

declare(strict_types=1);

namespace App\Http\Requests\Opportunities;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/opportunities/open-check (spec 0396, D-6): read-only pre-check of
 * the one-open-opportunity-per-anagrafica rule. POST because `product_lines`
 * is a collection of objects. Authorization (`opportunities.create`) lives in
 * the controller.
 */
class OpportunityOpenCheckRequest extends FormRequest
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
            'registry_id' => ['required', 'integer', Rule::exists('registries', 'id')],
            'product_lines' => ['sometimes', 'array'],
            'product_lines.*.product_category_id' => ['integer', Rule::exists('product_categories', 'id')],
        ];
    }

    public function registryId(): int
    {
        return (int) $this->validated('registry_id');
    }

    /**
     * @return array<int, int>
     */
    public function categoryIds(): array
    {
        return collect((array) ($this->validated('product_lines') ?? []))
            ->map(static fn (mixed $row): mixed => is_array($row) ? ($row['product_category_id'] ?? null) : null)
            ->filter(static fn (mixed $id): bool => $id !== null)
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
