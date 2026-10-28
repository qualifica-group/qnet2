<?php

declare(strict_types=1);

namespace App\Quotes;

use App\Products\ProductSupplierRule;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * `{field}.*.supplier_id` of a quote line row (spec 0320, D-5): the supplier
 * must be one of the product's suppliers (ProductSupplierRule). Applied only
 * to rows that are NEW or whose product/supplier differ from the persisted
 * row, so a historic line stays valid once its product changes supplier list.
 * A row that omits the key keeps the stored supplier (unless new or product
 * changed, where it counts as null).
 *
 * Implicit: an absent/null supplier on a product with suppliers is the
 * `required` violation. The rows are evaluated in ONE batch on first use (a
 * rule instance is shared across the wildcard expansion): one query, no N+1.
 */
final class QuoteLineSupplierRule implements DataAwareRule, ValidationRule
{
    public bool $implicit = true;

    /** @var array<string, mixed> */
    private array $data = [];

    /** @var array<int, string>|null violations keyed by row index */
    private ?array $violations = null;

    /**
     * @param  array<int, array{product_id: int, supplier_id: int|null}>  $persistedLines  the saved rows of the record, keyed by line id
     */
    public function __construct(
        private readonly string $field,
        private readonly array $persistedLines = [],
    ) {}

    /** @param array<string, mixed> $data */
    public function setData(array $data): static
    {
        $this->data = $data;
        $this->violations = null;

        return $this;
    }

    /**
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (preg_match('/^'.preg_quote($this->field, '/').'\.(\d+)\.supplier_id$/', $attribute, $matches) !== 1) {
            return;
        }

        $violation = $this->violations()[(int) $matches[1]] ?? null;

        if ($violation !== null) {
            $fail("quotes.supplier_{$violation}")->translate();
        }
    }

    /** @return array<int, string> */
    private function violations(): array
    {
        return $this->violations ??= ProductSupplierRule::violations($this->pairsToCheck());
    }

    /** @return array<int, array{product_id: int, supplier_id: int|null}> */
    private function pairsToCheck(): array
    {
        $rows = $this->data[$this->field] ?? [];
        $pairs = [];

        foreach (is_array($rows) ? $rows : [] as $index => $row) {
            if (! is_array($row) || ! isset($row['product_id']) || ! is_numeric($row['product_id'])) {
                continue;
            }

            $productId = (int) $row['product_id'];
            $persisted = isset($row['id']) && is_numeric($row['id']) ? ($this->persistedLines[(int) $row['id']] ?? null) : null;
            $samePersistedProduct = $persisted !== null && $persisted['product_id'] === $productId;
            $submitted = is_numeric($row['supplier_id'] ?? null) ? (int) $row['supplier_id'] : null;
            $supplierId = array_key_exists('supplier_id', $row) || ! $samePersistedProduct
                ? $submitted
                : $persisted['supplier_id'];

            if ($samePersistedProduct && $supplierId === $persisted['supplier_id']) {
                continue;
            }

            $pairs[(int) $index] = ['product_id' => $productId, 'supplier_id' => $supplierId];
        }

        return $pairs;
    }
}
