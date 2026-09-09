import { useEffect, useMemo, useRef, useState } from 'react'
import { useQueries } from '@tanstack/react-query'
import { useController, type Control, type FieldValues, type Path } from 'react-hook-form'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { fetchForSelect } from '@/features/for-select/api'
import { PRODUCTS_FOR_SELECT_RESOURCE } from '@/features/products/for-select-api'
import { isPristineLineRow } from '@/features/quotes/quote-line-values'
import type { QuoteProductForSelectItem } from '@/features/quotes/quote-product-select'
import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'
import { DEFAULT_LINE_QUANTITY, EMPTY_LINE_ROW, lineValuesFromProduct } from '@/features/quotes/use-quote-lines-field'
import { requestManagementKeys } from '@/features/request-management/query-keys'

/** Two rows are all it takes to tell "exactly one product" from "more than one". */
const SOLE_PRODUCT_PROBE_LIMIT = 2

/** A category's catalogue changes rarely; re-probing on every pick would be pure churn. */
const SOLE_PRODUCT_STALE_TIME = 5 * 60 * 1000

/** Authorization key of the collection this hook writes: the one `RequestOfferLinesField` mounts under. */
const OFFER_LINES_META_KEY = 'offer_lines'

/** Stable empty reference: a locked field probes nothing and must not build a fresh array per render. */
const NO_CATEGORY_IDS: number[] = []

/**
 * The sole product of each probed category, keyed by category id. `null` when
 * the category exposes none or more than one — resolved either way, which is
 * what lets the caller settle a category it will never seed from. A key is
 * ABSENT while its probe is in flight (or has failed): nothing is decided yet.
 */
function useSoleCategoryProducts(categoryIds: number[]): Map<number, QuoteProductForSelectItem | null> {
  return useQueries({
    queries: categoryIds.map((categoryId) => ({
      queryKey: requestManagementKeys.categorySoleProduct(categoryId),
      queryFn: async (): Promise<QuoteProductForSelectItem | null> => {
        const page = await fetchForSelect(PRODUCTS_FOR_SELECT_RESOURCE, {
          limit: SOLE_PRODUCT_PROBE_LIMIT,
          params: { category_ids: [categoryId] },
        })

        return page.pagination.total === 1
          ? ((page.items[0] as QuoteProductForSelectItem | undefined) ?? null)
          : null
      },
      staleTime: SOLE_PRODUCT_STALE_TIME,
    })),
    combine: (results) => {
      const products = new Map<number, QuoteProductForSelectItem | null>()
      results.forEach((result, index) => {
        const categoryId = categoryIds[index]
        if (categoryId !== undefined && result.isSuccess) {
          products.set(categoryId, result.data)
        }
      })

      return products
    },
  })
}

/**
 * Fills the pristine rows first, appends what is left up to `maxRows`. A
 * product the card already carries is never seeded twice. Returns the SAME
 * array when nothing was added, so the caller can skip the write entirely.
 */
export function seedSoleProductRows(
  rows: QuoteLineFormValues[],
  products: QuoteProductForSelectItem[],
  maxRows: number,
): QuoteLineFormValues[] {
  const seeded = [...rows]
  let added = false

  for (const product of products) {
    if (seeded.some((row) => row.product_id === product.id)) {
      continue
    }

    // Exactly what a manual pick produces (`useQuoteLinesField.setProduct`):
    // same precompiled unit price, VAT rate, unit and opening quantity.
    const row: QuoteLineFormValues = {
      ...EMPTY_LINE_ROW,
      ...lineValuesFromProduct(product, 'revenue'),
      quantity: DEFAULT_LINE_QUANTITY,
    }
    const pristineIndex = seeded.findIndex(isPristineLineRow)

    if (pristineIndex !== -1) {
      seeded[pristineIndex] = row
    } else if (seeded.length < maxRows) {
      seeded.push(row)
    } else {
      continue
    }

    added = true
  }

  return added ? seeded : rows
}

interface UseOfferLinesAutofillArgs<TFieldValues extends FieldValues> {
  control: Control<TFieldValues>
  /** The offer-rows path on the host form (`offer_lines` on all three surfaces). */
  name: Path<TFieldValues>
  /** The categories the classification carries right now. */
  categoryIds: number[]
  /** Row ceiling: 1 while a `single`-managed category governs the card, the schema's own max otherwise. */
  maxRows: number
  /** The same percent cache a manual pick feeds (AC-071), so a seeded row shows its IVA/totale at once. */
  rememberVatRatePercent: (vatRateId: number, percent: number) => void
}

/**
 * Auto-creates the offer row of a category that exposes a single product
 * (user directive 2026-09-09): the operator picks the classification and the
 * only row that category can produce is already there, precompiled exactly as
 * a manual pick would leave it.
 *
 * Scoped to the categories picked IN THIS SESSION — the ones the form opened
 * on are neither probed nor seeded, so hydrating a persisted request never
 * appends a row next to those it already carries — and each of them is acted
 * on exactly once: seeding again from a probe that stays cached would make
 * the row impossible to delete.
 */
export function useOfferLinesAutofill<TFieldValues extends FieldValues>({
  control,
  name,
  categoryIds,
  maxRows,
  rememberVatRatePercent,
}: UseOfferLinesAutofillArgs<TFieldValues>): void {
  const permission = useResourcePermissions().field(OFFER_LINES_META_KEY)
  // The SAME derivation `MetaField` applies before handing the rows to the
  // editor: a field the actor may not write is not written from here either.
  const editable = permission.visible && permission.editable && !permission.disabled

  // Bound to the path `MetaField` already renders: RHF keeps one field state
  // per name, so this controller only adds a programmatic writer to it.
  const { field } = useController({ control, name })

  // The categories the form OPENED on. Never probed and never seeded: the
  // directive is about a category the operator PICKS, so hydrating a
  // persisted request must not append a row next to the ones it carries.
  const [initialCategoryIds] = useState(() => categoryIds)

  const pendingCategoryIds = useMemo(
    () => categoryIds.filter((categoryId) => !initialCategoryIds.includes(categoryId)),
    [categoryIds, initialCategoryIds],
  )

  const soleProducts = useSoleCategoryProducts(editable ? pendingCategoryIds : NO_CATEGORY_IDS)

  // Seeded once each, and never again: re-seeding a category whose probe stays
  // cached would make the row impossible to delete.
  const seededCategoryIds = useRef<Set<number>>(new Set())

  useEffect(() => {
    // Step 1: the categories picked in this session whose probe has come back
    // and which have not been acted on yet. An absent key is a probe still in
    // flight; `null` is a category resolved to none or to several products.
    const products: QuoteProductForSelectItem[] = []
    for (const categoryId of pendingCategoryIds) {
      if (seededCategoryIds.current.has(categoryId) || !soleProducts.has(categoryId)) {
        continue
      }
      seededCategoryIds.current.add(categoryId)
      const product = soleProducts.get(categoryId) ?? null
      if (product !== null) {
        products.push(product)
      }
    }
    if (products.length === 0) {
      return
    }

    // Step 2: one row per sole product, on the pristine rows first.
    const rows = field.value as QuoteLineFormValues[]
    const seededRows = seedSoleProductRows(rows, products, maxRows)
    if (seededRows === rows) {
      return
    }

    // Step 3: the VAT percent the picker itself never exposes, so the seeded
    // row's live IVA/totale are right without waiting for a save.
    for (const product of products) {
      if (product.meta.vat_rate_id !== null && product.meta.vat_rate !== null) {
        rememberVatRatePercent(product.meta.vat_rate_id, Number(product.meta.vat_rate))
      }
    }
    field.onChange(seededRows)
  }, [field, maxRows, pendingCategoryIds, rememberVatRatePercent, soleProducts])
}
