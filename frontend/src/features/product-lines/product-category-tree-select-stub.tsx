/**
 * Test double for {@link ProductCategoryTreeSelect}, shaped like the
 * `AsyncPaginatedSelect` stub the form suites already use: it exposes the
 * props as `data-testid` probes instead of rendering a real dropdown.
 *
 * Shared rather than copied into each suite because several of them need it
 * for the same reason — they render a whole form and only care that the
 * row's category picker is wired and scoped, not how it lists the tree. The
 * listing itself (parents shown disabled, scope resolution) is covered
 * against the REAL component in `product-lines-field.test.tsx` and
 * `competence-lines-field.test.tsx`.
 *
 * Usage:
 *   vi.mock('@/features/product-lines/product-category-tree-select', async () =>
 *     await import('@/features/product-lines/product-category-tree-select-stub'))
 */
import type { CategoryPickScope, ProductCategoryTreeSelectProps } from '@/features/product-lines/product-category-tree-select'

/** Mirrors the real component's own resolution of "has the row's first step been picked yet". */
function scopeReady(scope: CategoryPickScope): boolean {
  return (scope.kind === 'root' ? scope.rootCategoryId : scope.businessFunctionId) !== null
}

export function ProductCategoryTreeSelect({
  value,
  scope,
  disabled = false,
  triggerLabel,
}: ProductCategoryTreeSelectProps) {
  // The real component disables itself while the row's scope has not
  // resolved yet: the suites assert that state, so the double reproduces it.
  const isDisabled = disabled || !scopeReady(scope)

  return (
    <div data-testid={`select-${triggerLabel}`}>
      <span data-testid={`value-${triggerLabel}`}>{value ?? ''}</span>
      <span data-testid={`disabled-${triggerLabel}`}>{String(isDisabled)}</span>
      <span data-testid={`scope-${triggerLabel}`}>{JSON.stringify(scope)}</span>
    </div>
  )
}
