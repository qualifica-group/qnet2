<?php

namespace App\Http\Requests\Stock;

use App\DataObjects\Stock\StockMovementData;
use App\Enums\StockMovementType;
use App\Models\OperationalSite;
use App\Models\Product;
use App\Models\User;
use App\Policies\StockPolicy;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates POST /api/stock-movements (spec 0307, D-11/D-14). The rules change
 * with the movement type; the checks that depend on the stock itself (quantity
 * available, adjustment equal to the count) are made by StockMovementService
 * under the product lock.
 */
class StoreStockMovementRequest extends FormRequest
{
    public const int NOTE_MAX = 1000;

    /** decimal(12,3) ceiling of stock_balances.quantity. */
    public const string QUANTITY_MAX = '999999999.999';

    public function authorize(): bool
    {
        return app(StockPolicy::class)->manage($this->user());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $type = StockMovementType::tryFrom((string) $this->input('type'));
        $quantity = ['numeric', 'decimal:0,3', 'max:'.self::QUANTITY_MAX];

        return [
            'type' => ['required', Rule::enum(StockMovementType::class)],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'operational_site_id' => ['required', 'integer', 'exists:operational_sites,id'],
            'user_id' => [
                $this->userIsRequired($type) ? 'required' : 'nullable',
                $type === StockMovementType::Load ? 'prohibited' : 'integer',
                'exists:users,id',
            ],
            'to_operational_site_id' => $type === StockMovementType::Transfer
                ? ['required', 'integer', 'exists:operational_sites,id', 'different:operational_site_id']
                : ['prohibited'],
            'quantity' => $type === StockMovementType::Adjustment
                ? ['prohibited']
                : ['required', ...$quantity, 'gt:0'],
            'counted_quantity' => $type === StockMovementType::Adjustment
                ? ['required', ...$quantity, 'min:0']
                : ['prohibited'],
            'moved_on' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'note' => [
                in_array($type, [StockMovementType::Unload, StockMovementType::Adjustment], true) ? 'required' : 'nullable',
                'string',
                'max:'.self::NOTE_MAX,
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->assertTracked($validator);
            $this->assertActiveTargets($validator);
        });
    }

    public function toData(): StockMovementData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return StockMovementData::fromValidated($validated);
    }

    private function userIsRequired(?StockMovementType $type): bool
    {
        return in_array($type, [StockMovementType::Assign, StockMovementType::Return], true);
    }

    private function assertTracked(Validator $validator): void
    {
        if (! Product::query()->findOrFail($this->input('product_id'))->isStockTracked()) {
            $validator->errors()->add('product_id', __('stock.product_not_tracked'));
        }
    }

    /** Stock goes into active sites and to active users; it may always leave inactive ones (D-14). */
    private function assertActiveTargets(Validator $validator): void
    {
        $type = StockMovementType::from((string) $this->input('type'));

        if ($type === StockMovementType::Load && ! $this->siteIsActive($this->input('operational_site_id'))) {
            $validator->errors()->add('operational_site_id', __('stock.inactive_site'));
        }

        if ($type === StockMovementType::Transfer && ! $this->siteIsActive($this->input('to_operational_site_id'))) {
            $validator->errors()->add('to_operational_site_id', __('stock.inactive_site'));
        }

        if ($type === StockMovementType::Assign && ! User::query()->whereKey($this->input('user_id'))->where('is_active', true)->exists()) {
            $validator->errors()->add('user_id', __('stock.inactive_user'));
        }
    }

    private function siteIsActive(mixed $siteId): bool
    {
        return OperationalSite::query()->whereKey($siteId)->where('is_active', true)->exists();
    }
}
