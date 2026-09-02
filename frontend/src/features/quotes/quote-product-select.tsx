import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { PRODUCTS_FOR_SELECT_RESOURCE } from '@/features/products/for-select-api'
import type { ForSelectItem } from '@/features/for-select/types'
import type { QuoteLineCategoryRef, QuoteLineUnitOfMeasureRef } from '@/features/quotes/types'

/**
 * The `meta` block additive to `GET /products/for-select` (spec 0065 D-1/
 * AC-009): `code`/`price`/`cost`/`vat_rate_id`/`vat_rate_name`/`vat_rate`/
 * `unit_of_measure`/`product_typology`.
 * Typed HERE (not in `features/products/for-select-api.ts`, out of this
 * module's write surface) the same way `ProjectForSelectItem`/
 * `OperationalSiteForSelectItem` extend the base `ForSelectItem` for their
 * own single consumer. This is what lets a quote line precompile
 * `unit_price`/`vat_rate_id` and show the unit on pick (AC-074).
 */
export interface QuoteProductForSelectMeta {
  code: string
  price: string | null
  cost: string | null
  vat_rate_id: number | null
  vat_rate_name: string | null
  vat_rate: string | null
  /** Spec 0088: the product's own unit, shown on the row before the save congelates the line's own. */
  unit_of_measure: QuoteLineUnitOfMeasureRef | null
  /**
   * Spec 0099: the product's typology, so a freshly picked row lands in the
   * right bucket of the live per-typology summary with no round trip. Never
   * an input — the line stores no typology (D-5).
   */
  product_typology: QuoteLineCategoryRef | null
}

export interface QuoteProductForSelectItem extends ForSelectItem {
  meta: QuoteProductForSelectMeta
}

interface QuoteProductSelectProps {
  value: number | null
  /**
   * Fired on pick/clear with both the id and the full item (`meta` included) —
   * the only channel the caller needs to precompile `unit_price`/
   * `vat_rate_id` (AC-074), so this wraps `AsyncPaginatedSelect.onItemChange`
   * rather than exposing its own separate `onChange`.
   */
  onChange: (value: number | null, item: QuoteProductForSelectItem | null) => void
  selectedItem?: ForSelectItem | null
  /**
   * Category ids to scope the picker to (Offer tab, AC-072); `undefined` =
   * unfiltered (Cost tab always, AC-073; Offer tab once unlocked). An empty
   * array locks the picker with nothing to scope to — the caller disables it.
   */
  categoryIds?: number[]
  disabled?: boolean
  triggerLabel: string
  id?: string
  'aria-describedby'?: string
  'aria-invalid'?: boolean
}

/**
 * Single-product picker for one quote line row (spec 0065). Thin wrapper over
 * the shared `AsyncPaginatedSelect`: all async/paging state stays inside it,
 * this component only shapes `params` for the quotes domain and re-exposes
 * the richer `meta`-carrying item on change.
 */
export function QuoteProductSelect({
  value,
  onChange,
  selectedItem = null,
  categoryIds,
  disabled = false,
  triggerLabel,
  id,
  'aria-describedby': ariaDescribedBy,
  'aria-invalid': ariaInvalid,
}: QuoteProductSelectProps) {
  const { t } = useTranslation()

  const params = useMemo(
    () => (categoryIds ? { category_ids: categoryIds } : undefined),
    [categoryIds],
  )

  return (
    <AsyncPaginatedSelect
      resource={PRODUCTS_FOR_SELECT_RESOURCE}
      value={value}
      onChange={() => {}}
      onItemChange={(item) => onChange(item?.id ?? null, (item as QuoteProductForSelectItem | null) ?? null)}
      selectedItem={selectedItem}
      disabled={disabled}
      id={id}
      aria-describedby={ariaDescribedBy}
      aria-invalid={ariaInvalid}
      params={params}
      labels={{
        placeholder: t('quotes.form.lineProductPlaceholder'),
        searchPlaceholder: t('quotes.form.lineProductSearch'),
        empty: t('quotes.form.lineProductEmpty'),
        error: t('quotes.form.lineProductLoadError'),
        clearLabel: t('common.clear'),
        triggerLabel,
        retry: t('common.retry'),
      }}
    />
  )
}
