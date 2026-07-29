import { useId, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Trash2 } from 'lucide-react'
import type { FieldError } from 'react-hook-form'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { cn } from '@/lib/utils'
import { VAT_RATES_FOR_SELECT_RESOURCE } from '@/features/vat-rates/for-select-api'
import { computeLineAmounts } from '@/features/quotes/quote-totals'
import { formatQuoteAmount } from '@/features/quotes/quote-summary'
import { QuoteProductSelect, type QuoteProductForSelectItem } from '@/features/quotes/quote-product-select'
import type { ForSelectItem } from '@/features/for-select/types'
import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'
import type { QuoteLineProductRef, QuoteLineVatRateRef } from '@/features/quotes/types'

/** Per-row validation errors this component wires to the accessible triad (AC-075). */
export interface QuoteLineRowErrors {
  product_id?: FieldError
  quantity?: FieldError
  unit_price?: FieldError
}

/**
 * The `meta.rate` block additive to `GET /vat-rates/for-select`: the decimal
 * percentage of the picked rate (same string style as
 * `QuoteProductForSelectMeta.vat_rate`). Typed HERE, the same way
 * `quote-product-select.tsx` types the product picker's own `meta` for its
 * single consumer, rather than widening `features/vat-rates/for-select-api.ts`
 * (out of this module's write surface). Lets a manually-picked VAT rate feed
 * the live-preview percent cache directly, so the summary stays accurate for
 * a rate the row never saw via the product or edit hydration (AC-071/D-10).
 */
interface QuoteVatRateForSelectMeta {
  rate: string | null
}

interface QuoteVatRateForSelectItem extends ForSelectItem {
  meta: QuoteVatRateForSelectMeta
}

interface QuoteLineRowProps {
  index: number
  row: QuoteLineFormValues
  disabled: boolean
  /** `undefined` = unfiltered picker (Cost tab always, Offer tab once unlocked). An empty array locks it with nothing to scope to. */
  categoryIds?: number[]
  knownProduct?: QuoteLineProductRef
  knownVatRate?: QuoteLineVatRateRef
  vatRatePercentFor: (vatRateId: number) => number | null
  /** Feeds the shared VAT-percent cache when the user manually picks a rate the row hasn't seen yet (AC-071). Omitted in call sites that don't need it (e.g. tests exercising unrelated behaviour). */
  rememberVatRatePercent?: (vatRateId: number, percent: number) => void
  error?: QuoteLineRowErrors
  onChangeProduct: (productId: number | null, item: QuoteProductForSelectItem | null) => void
  onChangeField: (patch: Partial<QuoteLineFormValues>) => void
  onRemove: () => void
}

/** Formats a nullable numeric RHF value for a controlled `<input type="number">`. */
function numberInputValue(value: number | null): string {
  return value === null ? '' : String(value)
}

const ROW_GRID_CLASS =
  'grid grid-cols-[minmax(200px,1.4fr)_88px_112px_128px_140px_90px_90px_100px_36px] items-start gap-2 px-2 py-2'

/**
 * One `offer_lines`/`cost_lines` row (D-11): product picker, quantity/unit
 * price inputs, VAT rate picker, and three server-mirrored read-only amounts
 * (`net`/`vat`/`total`, D-12) computed client-side via the shared
 * `computeLineAmounts` — never a separate calculation (AC-071). The
 * precompilation on product pick (AC-074) lives in `useQuoteLinesField`; this
 * component only renders the result and bubbles raw edits up. The product's
 * own `code` (D-7: read-only, never part of the submitted row) is
 * remembered locally once picked, since neither the row's own form value nor
 * the for-select response stay around after the pick — seeded from the
 * persisted line on edit load via `knownProduct`.
 */
export function QuoteLineRow({
  index,
  row,
  disabled,
  categoryIds,
  knownProduct,
  knownVatRate,
  vatRatePercentFor,
  rememberVatRatePercent,
  error,
  onChangeProduct,
  onChangeField,
  onRemove,
}: QuoteLineRowProps) {
  const { t } = useTranslation()
  const rowId = useId()
  const [pickedCode, setPickedCode] = useState<string | null>(knownProduct?.code ?? null)

  const productSelectDisabled = disabled || (categoryIds !== undefined && categoryIds.length === 0)
  const quantityErrorId = `${rowId}-quantity-error`
  const unitPriceErrorId = `${rowId}-unit-price-error`
  const productErrorId = `${rowId}-product-error`

  const vatPercent = row.vat_rate_id !== null ? vatRatePercentFor(row.vat_rate_id) : null
  const amounts = computeLineAmounts(row.quantity ?? 0, row.unit_price ?? 0, vatPercent)

  const productItem: ForSelectItem | null = knownProduct
    ? { id: knownProduct.id, label: knownProduct.name, subtitle: knownProduct.category?.name ?? null }
    : null
  const vatRateItem: ForSelectItem | null = knownVatRate
    ? { id: knownVatRate.id, label: knownVatRate.name }
    : null

  const handleProductChange = (productId: number | null, item: QuoteProductForSelectItem | null) => {
    setPickedCode(item?.meta.code ?? null)
    onChangeProduct(productId, item)
  }

  const code = row.product_id === null ? null : (pickedCode ?? knownProduct?.code ?? null)

  return (
    <div className={cn(ROW_GRID_CLASS, 'border-b last:border-b-0')}>
      <div className="flex flex-col gap-1">
        <QuoteProductSelect
          value={row.product_id}
          onChange={handleProductChange}
          selectedItem={productItem}
          categoryIds={categoryIds}
          disabled={productSelectDisabled}
          triggerLabel={t('quotes.form.lineProduct', { n: index + 1 })}
          id={`${rowId}-product`}
          aria-describedby={error?.product_id ? productErrorId : undefined}
          aria-invalid={!!error?.product_id}
        />
        {error?.product_id ? (
          <span id={productErrorId} role="alert" className="text-[11px] text-destructive">
            {error.product_id.message}
          </span>
        ) : null}
      </div>

      <span className="truncate pt-2 font-mono text-xs text-muted-foreground">{code ?? '—'}</span>

      <div className="flex flex-col gap-1">
        <Input
          type="number"
          step="0.01"
          min={0}
          aria-label={t('quotes.form.lineQuantity', { n: index + 1 })}
          aria-invalid={!!error?.quantity}
          aria-describedby={error?.quantity ? quantityErrorId : undefined}
          disabled={disabled}
          value={numberInputValue(row.quantity)}
          onChange={(event) =>
            onChangeField({ quantity: event.target.value === '' ? null : Number(event.target.value) })
          }
        />
        {error?.quantity ? (
          <span id={quantityErrorId} role="alert" className="text-[11px] text-destructive">
            {error.quantity.message}
          </span>
        ) : null}
      </div>

      <div className="flex flex-col gap-1">
        <Input
          type="number"
          step="0.01"
          min={0}
          aria-label={t('quotes.form.lineUnitPrice', { n: index + 1 })}
          aria-invalid={!!error?.unit_price}
          aria-describedby={error?.unit_price ? unitPriceErrorId : undefined}
          disabled={disabled}
          value={numberInputValue(row.unit_price)}
          onChange={(event) =>
            onChangeField({ unit_price: event.target.value === '' ? null : Number(event.target.value) })
          }
        />
        {error?.unit_price ? (
          <span id={unitPriceErrorId} role="alert" className="text-[11px] text-destructive">
            {error.unit_price.message}
          </span>
        ) : null}
      </div>

      <AsyncPaginatedSelect
        resource={VAT_RATES_FOR_SELECT_RESOURCE}
        value={row.vat_rate_id}
        onChange={(vatRateId) => onChangeField({ vat_rate_id: vatRateId })}
        onItemChange={(item) => {
          const vatItem = item as QuoteVatRateForSelectItem | null
          if (vatItem?.meta.rate != null) {
            rememberVatRatePercent?.(vatItem.id, Number(vatItem.meta.rate))
          }
        }}
        selectedItem={vatRateItem}
        disabled={disabled}
        labels={{
          placeholder: t('quotes.form.lineVatRatePlaceholder'),
          searchPlaceholder: t('quotes.form.lineVatRateSearch'),
          empty: t('quotes.form.lineVatRateEmpty'),
          error: t('quotes.form.lineVatRateLoadError'),
          clearLabel: t('common.clear'),
          triggerLabel: t('quotes.form.lineVatRate', { n: index + 1 }),
          retry: t('common.retry'),
        }}
      />

      <span className="pt-2 text-right text-xs tabular-nums">{formatQuoteAmount(amounts.net)}</span>
      <span className="pt-2 text-right text-xs tabular-nums">{formatQuoteAmount(amounts.vat)}</span>
      <span className="pt-2 text-right text-xs font-medium tabular-nums">{formatQuoteAmount(amounts.total)}</span>

      <Button
        type="button"
        variant="ghost"
        size="icon-sm"
        aria-label={t('quotes.form.lineRemove', { n: index + 1 })}
        disabled={disabled}
        onClick={onRemove}
      >
        <Trash2 aria-hidden="true" />
      </Button>
    </div>
  )
}
