import { useId, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { SearchableSelect } from '@/components/ui/searchable-select'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import { VAT_RATES_FOR_SELECT_RESOURCE } from '@/features/vat-rates/for-select-api'
import { QuoteProductSelect, type QuoteProductForSelectItem } from '@/features/quotes/quote-product-select'
import { QuoteLineAdditionalDescription } from '@/features/quotes/quote-line-additional-description'
import { computeLineAmounts } from '@/features/quotes/quote-totals'
import { formatQuoteAmount } from '@/features/quotes/quote-summary'
import type { ForSelectItem } from '@/features/for-select/types'
import { errorAria } from '@/features/work-order-costs/cost-row-aria'
import { CostRowError, type CostRowErrors } from '@/features/work-order-costs/cost-row-error'
import {
  DOCUMENT_REFERENCE_MAX_LENGTH,
  type WorkOrderCostRowValues,
} from '@/features/work-order-costs/work-order-costs-schema'

/** One revenue line of the work order the cost may refer to. */
export interface RevenueLineOption {
  quoteLineId: number
  label: string
}

/** Filters the supplier picker's `registries` for-select to `is_supplier` records only. */
const SUPPLIER_PARAMS: Record<string, string | number> = { is_supplier: 1 }

/** Header strip and every row share this grid; first line only, the second spans the full width. */
export const COST_ROW_GRID_CLASS =
  'grid grid-cols-[minmax(200px,1.4fr)_88px_96px_56px_112px_140px_180px_90px_90px_100px_36px] gap-2'
export const COST_ROW_MIN_WIDTH_CLASS = 'min-w-[1180px]'

interface VatRateForSelectItem extends ForSelectItem {
  meta: { rate: string | null }
}

interface WorkOrderCostRowProps {
  index: number
  row: WorkOrderCostRowValues
  disabled: boolean
  revenueOptions: RevenueLineOption[]
  error?: CostRowErrors
  onChangeField: (patch: Partial<WorkOrderCostRowValues>) => void
  onChangeProduct: (item: QuoteProductForSelectItem | null) => void
  onRemove: () => void
}

function numberInputValue(value: number | null): string {
  return value === null ? '' : String(value)
}

function parseNumber(raw: string): number | null {
  return raw === '' ? null : Number(raw)
}

/**
 * One real cost row (spec 0190 D-2/D-3): first line mirrors the offer cost
 * row (product, quantity, unit price, VAT, revenue-line reference, live
 * amounts), second line carries what is specific to a real cost (date,
 * supplier, document reference, additional description).
 */
export function WorkOrderCostRow({
  index,
  row,
  disabled,
  revenueOptions,
  error,
  onChangeField,
  onChangeProduct,
  onRemove,
}: WorkOrderCostRowProps) {
  const { t } = useTranslation()
  const rowId = useId()
  const n = index + 1
  const amounts = computeLineAmounts(row.quantity ?? 0, row.unit_price ?? 0, row.display.vat_percent)

  const revenueSelectOptions = useMemo(
    () => revenueOptions.map((option) => ({ id: option.quoteLineId, name: option.label })),
    [revenueOptions],
  )

  const productItem: ForSelectItem | null =
    row.product_id !== null && row.display.product_name
      ? { id: row.product_id, label: row.display.product_name, subtitle: row.display.product_category }
      : null
  const vatItem: ForSelectItem | null =
    row.vat_rate_id !== null && row.display.vat_name ? { id: row.vat_rate_id, label: row.display.vat_name } : null
  const supplierItem: ForSelectItem | null =
    row.supplier_id !== null && row.display.supplier_name
      ? { id: row.supplier_id, label: row.display.supplier_name }
      : null

  const id = (field: string) => `${rowId}-${field}`
  const errorId = (field: string) => `${rowId}-${field}-error`

  return (
    <div className="grid gap-2 border-b px-2 py-2 last:border-b-0">
      <div className={`${COST_ROW_GRID_CLASS} items-start`}>
        <div className="flex flex-col gap-1">
          <QuoteProductSelect
            value={row.product_id}
            onChange={(_, item) => onChangeProduct(item)}
            selectedItem={productItem}
            usage="COST"
            disabled={disabled}
            triggerLabel={t('workOrders.costs.editor.product', { n })}
            id={id('product')}
            {...errorAria(errorId('product'), error?.product_id)}
          />
          <CostRowError id={errorId('product')} error={error?.product_id} />
        </div>

        <span className="truncate pt-2 font-mono text-xs text-muted-foreground">{row.display.product_code ?? '—'}</span>

        <div className="flex flex-col gap-1">
          <Input
            type="number"
            step="0.01"
            min={0}
            aria-label={t('workOrders.costs.editor.quantity', { n })}
            disabled={disabled}
            value={numberInputValue(row.quantity)}
            onChange={(event) => onChangeField({ quantity: parseNumber(event.target.value) })}
            {...errorAria(errorId('quantity'), error?.quantity)}
          />
          <CostRowError id={errorId('quantity')} error={error?.quantity} />
        </div>

        <span className="truncate pt-2 text-xs text-muted-foreground">{row.display.unit_symbol ?? '—'}</span>

        <div className="flex flex-col gap-1">
          <Input
            type="number"
            step="0.01"
            min={0}
            aria-label={t('workOrders.costs.editor.unitPrice', { n })}
            disabled={disabled}
            value={numberInputValue(row.unit_price)}
            onChange={(event) => onChangeField({ unit_price: parseNumber(event.target.value) })}
            {...errorAria(errorId('unitPrice'), error?.unit_price)}
          />
          <CostRowError id={errorId('unitPrice')} error={error?.unit_price} />
        </div>

        <div className="flex flex-col gap-1">
          <AsyncPaginatedSelect
            resource={VAT_RATES_FOR_SELECT_RESOURCE}
            value={row.vat_rate_id}
            onChange={() => {}}
            onItemChange={(item) => {
              const vat = item as VatRateForSelectItem | null
              onChangeField({
                vat_rate_id: vat?.id ?? null,
                display: {
                  ...row.display,
                  vat_name: vat?.label ?? null,
                  vat_percent: vat?.meta.rate != null ? Number(vat.meta.rate) : null,
                },
              })
            }}
            selectedItem={vatItem}
            disabled={disabled}
            id={id('vat')}
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

        <div className="flex flex-col gap-1">
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

        <span className="pt-2 text-right text-xs tabular-nums">{formatQuoteAmount(amounts.net)}</span>
        <span className="pt-2 text-right text-xs tabular-nums">{formatQuoteAmount(amounts.vat)}</span>
        <span className="pt-2 text-right text-xs font-medium tabular-nums">{formatQuoteAmount(amounts.total)}</span>

        {disabled ? (
          <span />
        ) : (
          <Button
            type="button"
            variant="ghost"
            size="icon-sm"
            aria-label={t('workOrders.costs.editor.remove', { n })}
            onClick={onRemove}
          >
            <Trash2 aria-hidden="true" />
          </Button>
        )}
      </div>

      <div className="grid grid-cols-1 items-start gap-2 sm:grid-cols-[140px_minmax(180px,1fr)_minmax(160px,1fr)_minmax(200px,2fr)]">
        <div className="flex flex-col gap-1">
          <Input
            type="date"
            aria-label={t('workOrders.costs.editor.incurredOn', { n })}
            disabled={disabled}
            value={row.incurred_on}
            onChange={(event) => onChangeField({ incurred_on: event.target.value })}
            {...errorAria(errorId('incurredOn'), error?.incurred_on)}
          />
          <CostRowError id={errorId('incurredOn')} error={error?.incurred_on} />
        </div>

        <div className="flex flex-col gap-1">
          <AsyncPaginatedSelect
            resource={REGISTRIES_FOR_SELECT_RESOURCE}
            params={SUPPLIER_PARAMS}
            value={row.supplier_id}
            onChange={() => {}}
            onItemChange={(item) =>
              onChangeField({
                supplier_id: item?.id ?? null,
                display: { ...row.display, supplier_name: item?.label ?? null },
              })
            }
            selectedItem={supplierItem}
            disabled={disabled}
            id={id('supplier')}
            {...errorAria(errorId('supplier'), error?.supplier_id)}
            labels={{
              placeholder: t('workOrders.costs.editor.supplierPlaceholder'),
              searchPlaceholder: t('workOrders.costs.editor.supplierSearch'),
              empty: t('workOrders.costs.editor.supplierEmpty'),
              error: t('workOrders.costs.editor.supplierLoadError'),
              clearLabel: t('common.clear'),
              triggerLabel: t('workOrders.costs.editor.supplier', { n }),
              retry: t('common.retry'),
            }}
          />
          <CostRowError id={errorId('supplier')} error={error?.supplier_id} />
        </div>

        <div className="flex flex-col gap-1">
          <Input
            aria-label={t('workOrders.costs.editor.documentReference', { n })}
            placeholder={t('workOrders.costs.editor.documentReferencePlaceholder')}
            maxLength={DOCUMENT_REFERENCE_MAX_LENGTH}
            disabled={disabled}
            value={row.document_reference ?? ''}
            onChange={(event) => onChangeField({ document_reference: event.target.value === '' ? null : event.target.value })}
            {...errorAria(errorId('documentReference'), error?.document_reference)}
          />
          <CostRowError id={errorId('documentReference')} error={error?.document_reference} />
        </div>

        <div className="flex flex-col gap-1">
          <QuoteLineAdditionalDescription
            lineNumber={n}
            value={row.additional_description}
            disabled={disabled}
            onChange={(additionalDescription) => onChangeField({ additional_description: additionalDescription })}
          />
          <CostRowError id={errorId('additionalDescription')} error={error?.additional_description} />
        </div>
      </div>
    </div>
  )
}
