/**
 * Test double for {@link ProductCategoryTreeSelect}, shaped like the
 * `AsyncPaginatedSelect` stub the form suites already use: it exposes the
 * props as `data-testid` probes instead of rendering a real dropdown.
 *
 * Shared rather than copied into each suite because five of them need it for
 * the same reason — they render a whole form and only care that the row's
 * category picker is wired and scoped, not how it lists the tree. The listing
 * itself (parents shown disabled, business-function scoping) is covered
 * against the REAL component in `product-lines-field.test.tsx` and
 * `product-lines-field-management-mode.test.tsx`.
 *
 * Usage:
 *   vi.mock('@/features/product-lines/product-category-tree-select', async () =>
 *     await import('@/features/product-lines/product-category-tree-select-stub'))
 */
import type { ProductCategoryTreeSelectProps } from '@/features/product-lines/product-category-tree-select'

export function ProductCategoryTreeSelect({
  value,
  businessFunctionId,
  disabled = false,
  triggerLabel,
}: ProductCategoryTreeSelectProps) {
  // The real component disables itself while the row has no function: the
  // suites assert that state, so the double reproduces it.
  const isDisabled = disabled || businessFunctionId === null

  return (
    <div data-testid={`select-${triggerLabel}`}>
      <span data-testid={`value-${triggerLabel}`}>{value ?? ''}</span>
      <span data-testid={`disabled-${triggerLabel}`}>{String(isDisabled)}</span>
      <span data-testid={`scope-${triggerLabel}`}>
        {JSON.stringify({ business_function_id: businessFunctionId })}
      </span>
    </div>
  )
}
