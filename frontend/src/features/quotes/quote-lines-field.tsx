/* eslint-disable react-refresh/only-export-components -- small dedupe helpers (`knownProductsFrom`/`knownVatRatesFrom`) shared by both tab files, colocated with the row editor they feed rather than split into a components-only file for two 5-line pure functions */
import { useTranslation } from 'react-i18next'
import { Plus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useQuoteLinesField } from '@/features/quotes/use-quote-lines-field'
import { QuoteLineRow, type QuoteLineRowErrors } from '@/features/quotes/quote-line-row'
import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'
import type { QuoteLine, QuoteLineProductRef, QuoteLineVatRateRef } from '@/features/quotes/types'

/** Dedupe by id: several persisted rows may point at the same product. */
export function knownProductsFrom(lines: QuoteLine[]): QuoteLineProductRef[] {
  const byId = new Map<number, QuoteLineProductRef>()
  for (const line of lines) {
    byId.set(line.product.id, line.product)
  }
  return [...byId.values()]
}

/** Dedupe by id: several persisted rows may share the same VAT rate. */
export function knownVatRatesFrom(lines: QuoteLine[]): QuoteLineVatRateRef[] {
  const byId = new Map<number, QuoteLineVatRateRef>()
  for (const line of lines) {
    if (line.vat_rate) {
      byId.set(line.vat_rate.id, line.vat_rate)
    }
  }
  return [...byId.values()]
}

const HEADER_GRID_CLASS =
  'grid grid-cols-[minmax(200px,1.4fr)_88px_112px_128px_140px_90px_90px_100px_36px] gap-2 border-b bg-muted/40 px-2 py-1.5 text-[11px] font-medium text-muted-foreground'

interface QuoteLinesFieldProps {
  value: QuoteLineFormValues[]
  onChange: (rows: QuoteLineFormValues[]) => void
  variant: 'revenue' | 'cost'
  disabled: boolean
  /** `undefined` = unfiltered (Cost tab always; Offer tab once unlocked). */
  categoryIds?: number[]
  errors?: (QuoteLineRowErrors | undefined)[]
  knownProducts: QuoteLineProductRef[]
  knownVatRates: QuoteLineVatRateRef[]
  vatRatePercentFor: (vatRateId: number) => number | null
  rememberVatRatePercent: (vatRateId: number, percent: number) => void
}

/**
 * One tab's (`offer_lines`/`cost_lines`) repeatable row editor (D-11: both
 * tabs share this exact component, `variant` only changes which product price
 * precompiles `unit_price`, D-6). Horizontally scrollable so the 8-column
 * grid never forces the page itself to scroll (ui-design.md §3).
 */
export function QuoteLinesField({
  value,
  onChange,
  variant,
  disabled,
  categoryIds,
  errors,
  knownProducts,
  knownVatRates,
  vatRatePercentFor,
  rememberVatRatePercent,
}: QuoteLinesFieldProps) {
  const { t } = useTranslation()
  const { addRow, removeRow, setField, setProduct } = useQuoteLinesField({
    value,
    onChange,
    variant,
    rememberVatRatePercent,
  })

  const productById = (id: number | null) => (id === null ? undefined : knownProducts.find((p) => p.id === id))
  const vatRateById = (id: number | null) => (id === null ? undefined : knownVatRates.find((v) => v.id === id))

  return (
    <div className="flex flex-col gap-2">
      <div className="overflow-x-auto rounded-lg border bg-surface">
        <div className="min-w-[900px]">
          <div className={HEADER_GRID_CLASS}>
            <span>{t('quotes.form.lineProductHeader')}</span>
            <span>{t('quotes.form.lineCodeHeader')}</span>
            <span>{t('quotes.form.lineQuantityHeader')}</span>
            <span>{t('quotes.form.lineUnitPriceHeader')}</span>
            <span>{t('quotes.form.lineVatRateHeader')}</span>
            <span className="text-right">{t('quotes.form.lineNetHeader')}</span>
            <span className="text-right">{t('quotes.form.lineVatHeader')}</span>
            <span className="text-right">{t('quotes.form.lineTotalHeader')}</span>
            <span className="sr-only">{t('quotes.form.lineRemoveHeader')}</span>
          </div>

          {value.length === 0 ? (
            <p className="px-2 py-3 text-xs text-muted-foreground">{t('quotes.form.linesEmpty')}</p>
          ) : (
            value.map((row, index) => (
              // The row's identity IS its position (mirrors `ProductLinesField`/`ManagerSlotsField`).
              <QuoteLineRow
                key={index}
                index={index}
                row={row}
                disabled={disabled}
                categoryIds={categoryIds}
                knownProduct={productById(row.product_id)}
                knownVatRate={vatRateById(row.vat_rate_id)}
                vatRatePercentFor={vatRatePercentFor}
                rememberVatRatePercent={rememberVatRatePercent}
                error={errors?.[index]}
                onChangeProduct={(productId, item) => setProduct(index, productId, item)}
                onChangeField={(patch) => setField(index, patch)}
                onRemove={() => removeRow(index)}
              />
            ))
          )}
        </div>
      </div>

      <Button
        type="button"
        variant="outline"
        size="sm"
        disabled={disabled}
        onClick={addRow}
        className="w-full justify-center border-dashed text-muted-foreground hover:border-solid hover:text-foreground"
      >
        <Plus aria-hidden="true" className="size-3.5" />
        {t('quotes.form.lineAdd')}
      </Button>
    </div>
  )
}
