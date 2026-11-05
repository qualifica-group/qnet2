<?php

declare(strict_types=1);

namespace App\Http\Requests\Treasury;

use App\DataObjects\Treasury\StatementFilters;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET /api/financial-accounts/{financialAccount}/statement (spec 0355).
 * Authorization before validation: `treasury-movements.view`.
 */
class StatementRequest extends FormRequest
{
    public const array TYPES = ['manual_income', 'manual_expense', 'transfer', 'adjustment', 'collection', 'payment'];

    public function authorize(): bool
    {
        return $this->user()?->can($this->requiredPermission()) === true;
    }

    protected function requiredPermission(): string
    {
        return 'treasury-movements.view';
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'types' => ['sometimes', 'array'],
            'types.*' => ['string', Rule::in(self::TYPES)],
            'registry_id' => ['nullable', 'integer', Rule::exists('registries', 'id')],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function filters(): StatementFilters
    {
        $data = $this->validated();
        $today = CarbonImmutable::today();

        return new StatementFilters(
            dateFrom: isset($data['date_from']) ? CarbonImmutable::parse($data['date_from']) : $today->startOfMonth(),
            dateTo: isset($data['date_to']) ? CarbonImmutable::parse($data['date_to']) : $today,
            types: array_values(array_unique($data['types'] ?? [])),
            registryId: isset($data['registry_id']) ? (int) $data['registry_id'] : null,
            search: isset($data['search']) && trim($data['search']) !== '' ? trim($data['search']) : null,
        );
    }
}
