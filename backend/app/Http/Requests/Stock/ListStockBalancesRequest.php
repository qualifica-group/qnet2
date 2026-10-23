<?php

namespace App\Http\Requests\Stock;

use App\Policies\StockPolicy;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates GET /api/stock-balances (spec 0307): at least one of the three
 * filters, so the endpoint never dumps the whole stock.
 */
class ListStockBalancesRequest extends FormRequest
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
            'product_id' => ['sometimes', 'integer', 'exists:products,id'],
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'operational_site_id' => ['sometimes', 'integer', 'exists:operational_sites,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->hasAny(['product_id', 'user_id', 'operational_site_id'])) {
                $validator->errors()->add('product_id', __('stock.filter_required'));
            }
        });
    }
}
