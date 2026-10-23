<?php

namespace App\Http\Requests\Stock;

use App\Policies\StockPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates GET /api/stock-movements (spec 0307): the history of one balance
 * row, identified by product, site and optional user.
 */
class ListStockMovementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(StockPolicy::class)->viewAny($this->user());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'operational_site_id' => ['required', 'integer', 'exists:operational_sites,id'],
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ];
    }
}
