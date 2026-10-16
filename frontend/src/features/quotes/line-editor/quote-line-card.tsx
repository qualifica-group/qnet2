import { useId } from 'react'
import { useTranslation } from 'react-i18next'
import { Trash2 } from 'lucide-react'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import type { ForSelectItem } from '@/features/for-select/types'
import { ProductTypologyBadge } from '@/features/product-typologies/product-typology-badge'
import type { QuoteLineRowErrors } from '@/features/quotes/quote-line-types'
import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'
import { formatQuoteAmount } from '@/features/quotes/quote-summary'
import { computeLineAmounts } from '@/features/quotes/quote-totals'
import type { QuoteLineVatRateRef } from '@/features/quotes/types'
import { VAT_RATES_FOR_SELECT_RESOURCE } from '@/features/vat-rates/for-select-api'
import type { QuoteLineCardSlots, QuoteLineProductView } from './types'

/** `GET /vat-rates/for-select` meta: the decimal percentage of the picked rate (feeds the live preview). */
interface VatRateForSelectItem extends ForSelectItem {
  meta: { rate: string | null }
}

interface QuoteLineCardProps {
  /** 1-based position among the visible rows: the row's name in every label. */
  position: number
  row: QuoteLineFormValues
  /** `undefined` while its label is still loading, or when the row lost its product (its category was dropped). */
  product?: QuoteLineProductView
  categoryPath: string | null
  knownVatRate?: QuoteLineVatRateRef
  disabled: boolean
  /** Spec 0114: amounts only, the server congeals quantity/price/VAT. */
  simplified: boolean
  error?: QuoteLineRowErrors
  vatRatePercentFor: (vatRateId: number) => number | null
  rememberVatRatePercent: (vatRateId: number, percent: number) => void
  onChangeField: (patch: Partial<QuoteLineFormValues>) => void
  onRemove: () => void
  /** What the channel adds to the card (commissions, associated product, additional description). */
  slots?: QuoteLineCardSlots
}

/** One height for the row's three controls: the VAT trigger otherwise stands taller than the inputs. */
const INPUT_CLASS = 'h-8 text-sm'
const VAT_TRIGGER_CLASS = 'min-h-8 py-1'

/** Shown while a row's product label is still resolving: never the bare id. */
const PENDING_LABEL = '…'

function numberInputValue(value: number | null): string {
  return value === null ? '' : String(value)
}

function parseNumberInput(text: string): number | null {
  return text === '' ? null : Number(text)
}

/**
 * One line on two levels (spec 0268): the product — what the operator reads
 * first — then the editable amounts and their live result; a channel's own
 * extras ride the `slots` (spec 0269). Same fields and accessible names as
 * the former Offerte row (`quotes.form.line*`).
 */
export function QuoteLineCard({
  position,
  row,
  product,
  categoryPath,
  knownVatRate,
  disabled,
  simplified,
  error,
  vatRatePercentFor,
  rememberVatRatePercent,
  onChangeField,
  onRemove,
  slots,
}: QuoteLineCardProps) {
  const { t } = useTranslation()
  const rowId = useId()
  const vatPercent = row.vat_rate_id === null ? null : vatRatePercentFor(row.vat_rate_id)
  const amounts = computeLineAmounts(row.quantity ?? 0, row.unit_price ?? 0, vatPercent)
  const quantityErrorId = `${rowId}-quantity-error`
  const unitPriceErrorId = `${rowId}-unit-price-error`
  const productErrorId = `${rowId}-product-error`
  const vatRateItem: ForSelectItem | null = knownVatRate ? { id: knownVatRate.id, label: knownVatRate.name } : null

  return (
    <li className="flex flex-col gap-2.5 rounded-lg border bg-card p-3 shadow-sm">
      <div className="flex items-start gap-2">
        <span className="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-primary/10 text-[11px] font-semibold text-primary">
          {position}
        </span>
        <div className="flex min-w-0 flex-1 flex-col gap-0.5">
          <div className="flex min-w-0 flex-wrap items-center gap-1.5">
            <span className="min-w-0 text-sm font-medium break-words">
              {product?.name ??
                (row.product_id === null ? t('quotes.lineEditor.rows.productMissing') : PENDING_LABEL)}
            </span>
            {product?.typology ? (
              <ProductTypologyBadge name={product.typology.name} color={product.typology.color} />
            ) : null}
          </div>
          {product ? (
            <div className="flex min-w-0 items-center gap-1.5 text-xs text-muted-foreground">
              {product.code ? <span className="shrink-0 font-mono">{product.code}</span> : null}
              {product.code && categoryPath ? <span aria-hidden="true">·</span> : null}
              {categoryPath ? <span className="truncate" title={categoryPath}>{categoryPath}</span> : null}
            </div>
          ) : null}
          {error?.product_id ? (
            <span id={productErrorId} role="alert" className="text-[11px] text-destructive">
              {error.product_id.message}
            </span>
          ) : null}
        </div>
        {slots?.actions}
        <Button
          type="button"
          variant="ghost"
          size="icon-sm"
          aria-label={t('quotes.form.lineRemove', { n: position })}
          disabled={disabled}
          onClick={onRemove}
        >
          <Trash2 aria-hidden="true" />
        </Button>
      </div>

      {/* Tinted strip (ui-design §1-bis: a wash on top of the card): the
          editable amounts read as one block under the product. */}
      <div className="ml-7 flex flex-wrap items-start gap-x-3 gap-y-2 rounded-md bg-muted/40 px-2.5 py-2">
        {simplified ? null : (
          <div className="flex flex-wrap items-start gap-2">
            <div className="flex w-20 flex-col gap-1">
              <Input
                type="number"
                step="0.01"
                min={0}
                aria-label={t('quotes.form.lineQuantity', { n: position })}
                aria-invalid={!!error?.quantity}
                aria-describedby={error?.quantity ? quantityErrorId : undefined}
                disabled={disabled}
                value={numberInputValue(row.quantity)}
                onChange={(event) => onChangeField({ quantity: parseNumberInput(event.target.value) })}
                className={INPUT_CLASS}
              />
              {error?.quantity ? (
                <span id={quantityErrorId} role="alert" className="text-[11px] text-destructive">
                  {error.quantity.message}
                </span>
              ) : null}
            </div>
            <span className="pt-2 text-xs text-muted-foreground">{row.unit_of_measure?.symbol ?? ''} ×</span>
            <div className="flex w-28 flex-col gap-1">
              <Input
                type="number"
                step="0.01"
                min={0}
                aria-label={t('quotes.form.lineUnitPrice', { n: position })}
                aria-invalid={!!error?.unit_price}
                aria-describedby={error?.unit_price ? unitPriceErrorId : undefined}
                disabled={disabled}
                value={numberInputValue(row.unit_price)}
                onChange={(event) => onChangeField({ unit_price: parseNumberInput(event.target.value) })}
                className={INPUT_CLASS}
              />
              {error?.unit_price ? (
                <span id={unitPriceErrorId} role="alert" className="text-[11px] text-destructive">
                  {error.unit_price.message}
                </span>
              ) : null}
            </div>
            <div className="w-36">
              <AsyncPaginatedSelect
                resource={VAT_RATES_FOR_SELECT_RESOURCE}
                value={row.vat_rate_id}
                onChange={(vatRateId) => onChangeField({ vat_rate_id: vatRateId })}
                onItemChange={(item) => {
                  const vatItem = item as VatRateForSelectItem | null
                  if (vatItem?.meta.rate != null) {
                    rememberVatRatePercent(vatItem.id, Number(vatItem.meta.rate))
                  }
                }}
                selectedItem={vatRateItem}
                disabled={disabled}
                className={VAT_TRIGGER_CLASS}
                labels={{
                  placeholder: t('quotes.form.lineVatRatePlaceholder'),
                  searchPlaceholder: t('quotes.form.lineVatRateSearch'),
                  empty: t('quotes.form.lineVatRateEmpty'),
                  error: t('quotes.form.lineVatRateLoadError'),
                  clearLabel: t('common.clear'),
                  triggerLabel: t('quotes.form.lineVatRate', { n: position }),
                  retry: t('common.retry'),
                }}
              />
            </div>
          </div>
        )}

        {slots?.extraField ? <div className="w-full max-w-60">{slots.extraField}</div> : null}

        <dl className="ml-auto grid grid-cols-3 gap-x-4 text-right text-xs">
          <dt className="text-muted-foreground">{t('quotes.form.lineNetHeader')}</dt>
          <dt className="text-muted-foreground">{t('quotes.form.lineVatHeader')}</dt>
          <dt className="text-muted-foreground">{t('quotes.form.lineTotalHeader')}</dt>
          <dd className="tabular-nums">{formatQuoteAmount(amounts.net)}</dd>
          <dd className="tabular-nums">{formatQuoteAmount(amounts.vat)}</dd>
          <dd className="font-semibold tabular-nums">{formatQuoteAmount(amounts.total)}</dd>
        </dl>
      </div>

      {slots?.footer ? <div className="ml-7">{slots.footer}</div> : null}
    </li>
  )
}
