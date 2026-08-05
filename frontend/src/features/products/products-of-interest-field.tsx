import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { AsyncPaginatedMultiSelect } from '@/components/ui/async-paginated-multi-select'
import type { ForSelectItem } from '@/features/for-select/types'
import { PRODUCTS_FOR_SELECT_RESOURCE } from '@/features/products/for-select-api'

interface ProductsOfInterestFieldProps {
  /** Selected product ids (controlled). */
  value: number[]
  onChange: (next: number[]) => void
  /**
   * Product-category ids of the record's product lines: the picker's scope.
   * Empty means the record classifies nothing yet, so there is nothing to
   * scope to (see the empty-scope branch below).
   */
  categoryIds: number[]
  /** `{id, label}` of the already-selected products, so a badge never falls back to `#id`. */
  selectedItems?: ForSelectItem[]
  disabled?: boolean
  /** Forwarded by `FormControl` for the accessible-error triad (frontend.md §10). */
  id?: string
  'aria-describedby'?: string
  'aria-invalid'?: boolean
}

/** Stable empty default: an inline `[]` would be a new reference on every render. */
const EMPTY_ITEMS: ForSelectItem[] = []

/**
 * The "prodotti di interesse" picker (user directive 2026-07-22), shared
 * VERBATIM by the opportunity form and the request-management screens so the
 * two never drift.
 *
 * The options are ALWAYS scoped to the products of the record's own
 * product-line categories, with no whole-catalogue escape: since the user
 * directive 2026-08-05 both modules REFUSE a product outside them
 * (`ProductCategoryCoherence`) instead of covering it with a new product line,
 * so the picker only ever shows what the server would accept — spec 0075's D-4
 * `lockScope`, now the single behaviour.
 *
 * The other half of that guarantee — dropping a selection orphaned by a
 * category removal — belongs to the caller that owns both fields
 * (`useProductsOfInterestCoherence`), not to this picker.
 */
export function ProductsOfInterestField({
  value,
  onChange,
  categoryIds,
  selectedItems = EMPTY_ITEMS,
  disabled = false,
  id,
  'aria-describedby': ariaDescribedBy,
  'aria-invalid': ariaInvalid,
}: ProductsOfInterestFieldProps) {
  const { t } = useTranslation()

  // Referentially stable, else every render would restart the paginated query.
  const params = useMemo(() => ({ category_ids: categoryIds }), [categoryIds])

  // Nothing to scope to: filtering by an empty set would silently show the
  // WHOLE catalogue, i.e. the opposite of what the scope promises.
  const withoutScope = categoryIds.length === 0

  return (
    <div className="flex flex-col gap-2">
      <AsyncPaginatedMultiSelect
        resource={PRODUCTS_FOR_SELECT_RESOURCE}
        value={value}
        onChange={onChange}
        selectedItems={selectedItems}
        params={params}
        disabled={disabled || withoutScope}
        id={id}
        aria-describedby={ariaDescribedBy}
        aria-invalid={ariaInvalid}
        labels={{
          placeholder: t('products.ofInterest.placeholder'),
          searchPlaceholder: t('products.ofInterest.searchPlaceholder'),
          empty: t('products.ofInterest.empty'),
          error: t('opportunities.form.selectError'),
          removeLabel: t('products.ofInterest.remove'),
          triggerLabel: t('products.ofInterest.fieldLabel'),
          retry: t('common.retry'),
        }}
      />

      <p className="text-xs text-muted-foreground">
        {withoutScope ? t('products.ofInterest.hintNoCategories') : t('products.ofInterest.hintScoped')}
      </p>
    </div>
  )
}
