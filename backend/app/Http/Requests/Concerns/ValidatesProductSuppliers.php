<?php

namespace App\Http\Requests\Concerns;

use App\Models\Product;
use App\Models\ProductSupplier;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Spec 0320, D-1/D-2: the product's `suppliers` list on Store and Update, kept
 * in ONE place. Rows are validated one by one (supplier registry flagged
 * `is_supplier`, no repeats, price and cost >= 0, both optional: absent = 0); the cross-row rule (exactly one
 * default when there are 2+ rows) runs after them. With a single row the
 * default flag is forced on by ProductSupplierData, so it is not checked here.
 *
 * Also gives EnforcesFieldPermissions a meaningful "current value" for the
 * `suppliers` field: Product has no `suppliers` relation (the rows live on
 * `productSuppliers`), so the generic reader would see "nothing" and reject a
 * harmless re-submit of an unchanged list on a readonly field.
 */
trait ValidatesProductSuppliers
{
    private const int MAX_SUPPLIERS = 50;

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function supplierRules(): array
    {
        return [
            'suppliers' => ['sometimes', 'array', 'max:'.self::MAX_SUPPLIERS],
            'suppliers.*.supplier_id' => ['required', 'integer', 'distinct', Rule::exists('registries', 'id')->where('is_supplier', true)],
            'suppliers.*.price' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'suppliers.*.cost' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'suppliers.*.is_default' => ['sometimes', 'boolean'],
            // The single supplier moved onto `suppliers`: a stale client gets a clear 422.
            'supplier_id' => ['prohibited'],
        ];
    }

    protected function enforceSingleDefaultSupplier(Validator $validator): void
    {
        $rows = $this->input('suppliers');

        if (! is_array($rows) || count($rows) < 2 || $this->hasSupplierRowErrors($validator)) {
            return;
        }

        $defaults = count(array_filter($rows, static fn (mixed $row): bool => is_array($row) && filter_var($row['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN)));

        if ($defaults !== 1) {
            $validator->errors()->add('suppliers', trans('products.suppliers_default_required'));
        }
    }

    /**
     * The persisted list in the submitted shape; when it is semantically the
     * same as the submitted one the submitted value itself is returned, so the
     * change-based field guard sees "unchanged" whatever the number format.
     */
    protected function currentFieldValue(?Model $model, string $field): mixed
    {
        if ($field !== 'suppliers' || ! $model instanceof Product) {
            return $this->defaultCurrentFieldValue($model, $field);
        }

        $persisted = $model->productSuppliers->map(static fn (ProductSupplier $row): array => [
            'supplier_id' => $row->supplier_id,
            'price' => number_format((float) $row->price, 2, '.', ''),
            'cost' => number_format((float) $row->cost, 2, '.', ''),
            'is_default' => $row->is_default,
        ])->sortBy('supplier_id')->values()->all();

        return $this->comparableSuppliers($this->input('suppliers')) === $this->comparableSuppliers($persisted)
            ? $this->input('suppliers')
            : $persisted;
    }

    /**
     * @return array<int, array{supplier_id: int, price: string, cost: string, is_default: bool}>
     */
    private function comparableSuppliers(mixed $rows): array
    {
        $rows = is_array($rows) ? $rows : [];
        $single = count($rows) === 1;

        $normalized = array_map(static fn (mixed $row): array => [
            'supplier_id' => (int) ($row['supplier_id'] ?? 0),
            'price' => number_format((float) ($row['price'] ?? 0), 2, '.', ''),
            'cost' => number_format((float) ($row['cost'] ?? 0), 2, '.', ''),
            'is_default' => $single || filter_var($row['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ], array_filter($rows, 'is_array'));

        usort($normalized, static fn (array $a, array $b): int => $a['supplier_id'] <=> $b['supplier_id']);

        return $normalized;
    }

    private function hasSupplierRowErrors(Validator $validator): bool
    {
        foreach ($validator->errors()->keys() as $key) {
            if ($key === 'suppliers' || str_starts_with($key, 'suppliers.')) {
                return true;
            }
        }

        return false;
    }
}
