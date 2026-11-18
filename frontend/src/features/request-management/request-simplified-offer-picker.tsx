import { useQueryClient, type QueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { AsyncPaginatedMultiSelect } from '@/components/ui/async-paginated-multi-select'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { fetchForSelect } from '@/features/for-select/api'
import { forSelectKeys } from '@/features/for-select/query-keys'
import type { ForSelectItem } from '@/features/for-select/types'
import { formatEuro } from '@/features/invoices/invoice-format'
import { PRODUCTS_FOR_SELECT_RESOURCE } from '@/features/products/for-select-api'
import type { QuoteCatalogProduct } from '@/features/quotes/line-editor/types'
import { useQuoteLineEditor } from '@/features/quotes/line-editor/use-quote-line-editor'
import type { QuoteLineRowErrors } from '@/features/quotes/quote-line-types'
import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'
import type { QuoteLine } from '@/features/quotes/types'

interface RequestSimplifiedOfferPickerProps {
  value: QuoteLineFormValues[]
  onChange: (rows: QuoteLineFormValues[]) => void
  disabled: boolean
  /** The categories the products come from; empty = nothing to pick yet. */
  categoryIds: number[]
  /** Shown while there is no category to pick from. */
  lockedHint: string
  maxRows: number
  /** `single` mode: one product, a plain select; otherwise a multi-select. */
  singleCategoryMode: boolean
  knownLines: QuoteLine[]
  errors?: (QuoteLineRowErrors | undefined)[]
  vatRatePercentFor: (vatRateId: number) => number | null
  rememberVatRatePercent: (vatRateId: number, percent: number) => void
}

/** Revenue rows sell SALE products only (spec 0142), the same filter the full catalogue sends. */
const SALE_USAGE = 'SALE'

/** The first message a row carries: a simplified row has no input to hang it on. */
function firstRowError(errors: (QuoteLineRowErrors | undefined)[] | undefined): string | undefined {
  for (const error of errors ?? []) {
    const message = error?.product_id?.message ?? error?.quantity?.message ?? error?.unit_price?.message ?? error?.supplier_id?.message
    if (message) {
      return message
    }
  }
  return undefined
}

/**
 * The products a multi-select pick names, with the meta a row is built from
 * (price, VAT rate, unit). Same query key as `useForSelectLabels`, so a
 * product already resolved by id comes from the cache.
 */
async function loadProducts(queryClient: QueryClient, ids: number[]): Promise<QuoteCatalogProduct[]> {
  const sortedIds = [...ids].sort((a, b) => a - b)
  const page = await queryClient.fetchQuery({
    queryKey: forSelectKeys.labels(PRODUCTS_FOR_SELECT_RESOURCE, sortedIds, undefined),
    queryFn: () => fetchForSelect(PRODUCTS_FOR_SELECT_RESOURCE, { offset: 0, limit: sortedIds.length, ids: sortedIds }),
  })
  // The endpoint returns the requested ids alongside a regular page: keep only the ids asked for.
  return (page.items as QuoteCatalogProduct[]).filter((item) => sortedIds.includes(item.id))
}

/**
 * The simplified offer of Gestione Richieste (spec 0414 D-4, on the spec 0114
 * rule): ONE product select, nothing else. The pick becomes the offer row
 * (quantity 1, the product's price and VAT rate, exactly what the server
 * congeals anyway); under it, the indicative value (VAT included). Same
 * `offer_lines` rows and row primitives as the full editor (`useQuoteLineEditor`).
 */
export function RequestSimplifiedOfferPicker({
  value,
  onChange,
  disabled,
  categoryIds,
  lockedHint,
  maxRows,
  singleCategoryMode,
  knownLines,
  errors,
  vatRatePercentFor,
  rememberVatRatePercent,
}: RequestSimplifiedOfferPickerProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const editor = useQuoteLineEditor({
    value,
    onChange,
    variant: 'revenue',
    knownLines,
    maxRows,
    simplified: true,
    allowDuplicates: false,
    vatRatePercentFor,
    rememberVatRatePercent,
  })
  const pickedIds = editor.visibleLines.flatMap(({ row }) => (row.product_id === null ? [] : [row.product_id]))
  const pickedItems: ForSelectItem[] = pickedIds.map((id) => ({ id, label: editor.productViews.get(id)?.name ?? '…' }))
  // Spec 0360 D-4: a row assigned to a work order never leaves from here.
  const isLocked = (productId: number) => {
    const line = editor.visibleLines.find(({ row }) => row.product_id === productId)
    return line !== undefined && editor.lockedCodesOf(line.row) !== null
  }
  const noCategory = categoryIds.length === 0
  const params = { usage: SALE_USAGE, category_ids: categoryIds }
  const errorMessage = firstRowError(errors)

  const removeProducts = (ids: number[]) => {
    const removable = ids.filter((id) => !isLocked(id))
    if (removable.length > 0) {
      onChange(value.filter((row) => row.product_id === null || !removable.includes(row.product_id)))
    }
  }

  const changeMany = async (nextIds: number[]) => {
    // Step 1: the badges removed from the select leave the offer.
    const removed = pickedIds.filter((id) => !nextIds.includes(id))
    if (removed.length > 0) {
      removeProducts(removed)
      return
    }
    // Step 2: the options picked become rows, built from the products' own meta.
    const added = nextIds.filter((id) => !pickedIds.includes(id)).slice(0, editor.remainingSlots)
    if (added.length > 0) {
      await editor.addProducts(await loadProducts(queryClient, added))
    }
  }

  const labels = {
    placeholder: t(singleCategoryMode ? 'requestManagement.offerLines.simplified.placeholder' : 'requestManagement.offerLines.simplified.placeholderMany'),
    searchPlaceholder: t('quotes.form.lineProductSearch'),
    empty: t('quotes.form.lineProductEmpty'),
    error: t('quotes.form.lineProductLoadError'),
    triggerLabel: t('requestManagement.offerLines.simplified.label'),
    retry: t('common.retry'),
  }

  return (
    <div className="flex w-full flex-col gap-1.5">
      {singleCategoryMode ? (
        <AsyncPaginatedSelect
          resource={PRODUCTS_FOR_SELECT_RESOURCE}
          value={pickedIds[0] ?? null}
          selectedItem={pickedItems[0] ?? null}
          onChange={(id) => (id === null && pickedIds[0] !== undefined ? removeProducts([pickedIds[0]]) : undefined)}
          onItemChange={(item) => (item ? void editor.pickSingle(item as QuoteCatalogProduct) : undefined)}
          params={params}
          disabled={disabled || noCategory}
          aria-invalid={!!errorMessage}
          labels={{ ...labels, clearLabel: t('common.clear') }}
        />
      ) : (
        <AsyncPaginatedMultiSelect
          resource={PRODUCTS_FOR_SELECT_RESOURCE}
          value={pickedIds}
          selectedItems={pickedItems}
          onChange={(ids) => void changeMany(ids)}
          params={params}
          disabled={disabled || noCategory}
          aria-invalid={!!errorMessage}
          labels={{ ...labels, removeLabel: t('requestManagement.offerLines.simplified.remove') }}
        />
      )}

      {errorMessage ? (
        <p role="alert" className="text-xs text-destructive">
          {errorMessage}
        </p>
      ) : null}
      {!noCategory && pickedIds.length > 0 ? (
        <dl className="flex items-baseline justify-between gap-3 rounded-md bg-muted/40 px-3 py-2">
          <dt className="text-xs text-muted-foreground">{t('requestManagement.offerLines.simplified.indicativeValue')}</dt>
          <dd className="text-sm font-semibold tabular-nums">{formatEuro(editor.totals.gross)}</dd>
        </dl>
      ) : (
        <p className="text-xs text-muted-foreground">
          {noCategory ? lockedHint : t('requestManagement.offerLines.hintSimplified')}
        </p>
      )}
    </div>
  )
}
