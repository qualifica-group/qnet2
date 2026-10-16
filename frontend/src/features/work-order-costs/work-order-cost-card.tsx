import { useId, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Trash2 } from 'lucide-react'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { SearchableSelect } from '@/components/ui/searchable-select'
import type { ForSelectItem } from '@/features/for-select/types'
import { formatQuoteAmount } from '@/features/quotes/quote-summary'
import { VAT_RATES_FOR_SELECT_RESOURCE } from '@/features/vat-rates/for-select-api'
import { fromCents, lineAmounts } from '@/lib/amounts/line-amounts'
import { errorAria } from '@/features/work-order-costs/cost-row-aria'
import { CostRowError, type CostRowErrors } from '@/features/work-order-costs/cost-row-error'
import { WorkOrderCostCardDetails } from '@/features/work-order-costs/work-order-cost-card-details'
import type { WorkOrderCostRowValues } from '@/features/work-order-costs/work-order-costs-schema'

/** One revenue line of the work order the cost may refer to. */
export interface RevenueLineOption {
  quoteLineId: number
  label: string
}

/** Quantity decimals of a work order cost (spec 0253 D-5). */
export const COST_QUANTITY_SCALE = 2

/** One height for the card's controls: the selects otherwise stand taller than the inputs. */
const INPUT_CLASS = 'h-8 text-sm'
const SELECT_TRIGGER_CLASS = 'min-h-8 py-1'

interface VatRateForSelectItem extends ForSelectItem {
  meta: { rate: string | null }
}

interface WorkOrderCostCardProps {
  index: number
  row: WorkOrderCostRowValues
  disabled: boolean
  revenueOptions: RevenueLineOption[]
  error?: CostRowErrors
  onChangeField: (patch: Partial<WorkOrderCostRowValues>) => void
  onRemove: () => void
}

function numberInputValue(value: number | null): string {
  return value === null ? '' : String(value)
}

function parseNumber(raw: string): number | null {
  return raw === '' ? null : Number(raw)
}

/**
 * One real cost as a card (spec 0270 D-3): the product first, then the
 * amounts on a tinted strip (quantity x unit price, VAT, revenue line, live
 * net/VAT/total), then the real-cost details. Same fields, accessible names
 * and error triad as the former grid row.
 */
export function WorkOrderCostCard({ index, row, disabled, revenueOptions, error, onChangeField, onRemove }: WorkOrderCostCardProps) {
  const { t } = useTranslation()
  const rowId = useId()
  const n = index + 1
  const errorId = (field: string) => `${rowId}-${field}-error`
  const amounts = lineAmounts(row.quantity ?? 0, row.unit_price ?? 0, row.display.vat_percent, COST_QUANTITY_SCALE)
  const revenueSelectOptions = useMemo(
    () => revenueOptions.map((option) => ({ id: option.quoteLineId, name: option.label })),
    [revenueOptions],
  )
  const vatItem: ForSelectItem | null =
    row.vat_rate_id !== null && row.display.vat_name ? { id: row.vat_rate_id, label: row.display.vat_name } : null

  return (
    <li className="@container flex flex-col gap-2.5 rounded-lg border bg-card p-3 shadow-sm">
      <div className="flex items-start gap-2">
        <span className="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-primary/10 text-[11px] font-semibold text-primary">
          {n}
        </span>
        <div className="flex min-w-0 flex-1 flex-col gap-0.5">
          <span className="min-w-0 text-sm font-medium break-words">{row.display.product_name ?? '…'}</span>
          <div className="flex min-w-0 items-center gap-1.5 text-xs text-muted-foreground">
            {row.display.product_code ? <span className="shrink-0 font-mono">{row.display.product_code}</span> : null}
            {row.display.product_code && row.display.product_category ? <span aria-hidden="true">·</span> : null}
            {row.display.product_category ? (
              <span className="truncate" title={row.display.product_category}>
                {row.display.product_category}
              </span>
            ) : null}
          </div>
          <CostRowError id={errorId('product')} error={error?.product_id} />
        </div>
        {disabled ? null : (
          <Button type="button" variant="ghost" size="icon-sm" aria-label={t('workOrders.costs.editor.remove', { n })} onClick={onRemove}>
            <Trash2 aria-hidden="true" />
          </Button>
        )}
      </div>

      {/* Tinted strip (ui-design §1-bis): the amounts read as one block under the product. */}
      <div className="ml-7 flex flex-wrap items-start gap-x-3 gap-y-2 rounded-md bg-muted/40 px-2.5 py-2">
        <div className="flex w-20 flex-col gap-1">
          <Input
            type="number"
            step="0.01"
            min={0}
            aria-label={t('workOrders.costs.editor.quantity', { n })}
            disabled={disabled}
            value={numberInputValue(row.quantity)}
            onChange={(event) => onChangeField({ quantity: parseNumber(event.target.value) })}
            className={INPUT_CLASS}
            {...errorAria(errorId('quantity'), error?.quantity)}
          />
          <CostRowError id={errorId('quantity')} error={error?.quantity} />
        </div>
        <span className="pt-2 text-xs text-muted-foreground">{row.display.unit_symbol ?? ''} ×</span>
        <div className="flex w-28 flex-col gap-1">
          <Input
            type="number"
            step="0.01"
            min={0}
            aria-label={t('workOrders.costs.editor.unitPrice', { n })}
            disabled={disabled}
            value={numberInputValue(row.unit_price)}
            onChange={(event) => onChangeField({ unit_price: parseNumber(event.target.value) })}
            className={INPUT_CLASS}
            {...errorAria(errorId('unitPrice'), error?.unit_price)}
          />
          <CostRowError id={errorId('unitPrice')} error={error?.unit_price} />
        </div>
        <div className="flex w-36 flex-col gap-1">
          <AsyncPaginatedSelect
            resource={VAT_RATES_FOR_SELECT_RESOURCE}
            value={row.vat_rate_id}
            onChange={() => {}}
            onItemChange={(item) => {
              const vat = item as VatRateForSelectItem | null
              onChangeField({
                vat_rate_id: vat?.id ?? null,
                display: { ...row.display, vat_name: vat?.label ?? null, vat_percent: vat?.meta.rate != null ? Number(vat.meta.rate) : null },
              })
            }}
            selectedItem={vatItem}
            disabled={disabled}
            className={SELECT_TRIGGER_CLASS}
            {...errorAria(errorId('vat'), error?.vat_rate_id)}
            labels={{
              placeholder: t('quotes.form.lineVatRatePlaceholder'),
              searchPlaceholder: t('quotes.form.lineVatRateSearch'),
              empty: t('quotes.form.lineVatRateEmpty'),
              error: t('quotes.form.lineVatRateLoadError'),
              clearLabel: t('common.clear'),
              triggerLabel: t('workOrders.costs.editor.vatRate', { n }),
              retry: t('common.retry'),
            }}
          />
          <CostRowError id={errorId('vat')} error={error?.vat_rate_id} />
        </div>
        <div className="flex w-full max-w-60 flex-col gap-1">
          <SearchableSelect
            value={row.quote_line_id}
            onChange={(quoteLineId) => onChangeField({ quote_line_id: quoteLineId })}
            onClear={() => onChangeField({ quote_line_id: null })}
            options={revenueSelectOptions}
            disabled={disabled}
            labels={{
              placeholder: t('workOrders.costs.editor.revenueLineNone'),
              searchPlaceholder: t('workOrders.costs.editor.revenueLineSearch'),
              empty: t('workOrders.costs.editor.revenueLineEmpty'),
              noMatch: t('workOrders.costs.editor.revenueLineEmpty'),
              error: t('workOrders.costs.editor.revenueLineEmpty'),
              retry: t('common.retry'),
              triggerLabel: t('workOrders.costs.editor.revenueLine', { n }),
              clearLabel: t('common.clear'),
            }}
          />
          <CostRowError id={errorId('revenueLine')} error={error?.quote_line_id} />
        </div>
        <dl className="ml-auto grid grid-cols-3 gap-x-4 text-right text-xs">
          <dt className="text-muted-foreground">{t('workOrders.costs.editor.headers.net')}</dt>
          <dt className="text-muted-foreground">{t('workOrders.costs.editor.headers.vat')}</dt>
          <dt className="text-muted-foreground">{t('workOrders.costs.editor.headers.total')}</dt>
          <dd className="tabular-nums">{formatQuoteAmount(fromCents(amounts.net))}</dd>
          <dd className="tabular-nums">{formatQuoteAmount(fromCents(amounts.vat))}</dd>
          <dd className="font-semibold tabular-nums">{formatQuoteAmount(fromCents(amounts.total))}</dd>
        </dl>
      </div>

      <div className="ml-7">
        <WorkOrderCostCardDetails n={n} row={row} disabled={disabled} error={error} rowId={rowId} onChangeField={onChangeField} />
      </div>
    </li>
  )
}
