<?php

namespace App\Http\Requests\Products;

use App\Authorization\AuthorizationRegistry;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Payload of POST /api/products/activation (spec 0366, D-2/D-6): one state for
 * many products. Gated by `products.update` AND the `is_active` field
 * permission, so a role whose matrix locks the flag cannot flip it in bulk
 * either. The products ceiling does not depend on which product (only on
 * create vs update), so an unsaved instance stands for "an existing product".
 */
class SetProductsActivationRequest extends FormRequest
{
    public const int MAX_PRODUCTS = 500;

    private const string FIELD = 'is_active';

    public function authorize(AuthorizationRegistry $authorization): bool
    {
        /** @var User|null $actor */
        $actor = $this->user();

        return $actor !== null
            && $actor->can('products.update')
            && $authorization->resolve('products')->fieldPermissions($actor, new Product)[self::FIELD]->editable;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'product_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_PRODUCTS],
            'product_ids.*' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')],
            self::FIELD => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function productIds(): array
    {
        return array_map('intval', $this->validated('product_ids'));
    }

    public function isActive(): bool
    {
        return (bool) $this->validated(self::FIELD);
    }
}
