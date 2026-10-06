import { useTranslation } from 'react-i18next'
import { Plus } from 'lucide-react'
import { Button } from '@/components/ui/button'
import type { CostRowErrors } from '@/features/work-order-costs/cost-row-error'
import {
  COST_ROW_GRID_CLASS,
  COST_ROW_MIN_WIDTH_CLASS,
  WorkOrderCostRow,
  type RevenueLineOption,
} from '@/features/work-order-costs/work-order-cost-row'
import type { WorkOrderCostRowValues } from '@/features/work-order-costs/work-order-costs-schema'
import type { QuoteProductForSelectItem } from '@/features/quotes/quote-product-select'
import { LINE_TABLE_SCROLL_CLASS } from '@/components/record-form/layout'

interface WorkOrderCostsLinesFieldProps {
  /** Stable RHF ids, one per row: they key the rows so typing never remounts them. */
  rowKeys: string[]
  rows: WorkOrderCostRowValues[]
  readOnly: boolean
  revenueOptions: RevenueLineOption[]
  rowErrors: (index: number) => CostRowErrors | undefined
  onChangeField: (index: number, patch: Partial<WorkOrderCostRowValues>) => void
  onChangeProduct: (index: number, item: QuoteProductForSelectItem | null) => void
  onRemove: (index: number) => void
  onAdd: () => void
}

/** The repeatable real-cost row editor; horizontally scrollable so the wide grid never scrolls the page (ui-design §3). */
export function WorkOrderCostsLinesField({
  rowKeys,
  rows,
  readOnly,
  revenueOptions,
  rowErrors,
  onChangeField,
  onChangeProduct,
  onRemove,
  onAdd,
}: WorkOrderCostsLinesFieldProps) {
  const { t } = useTranslation()

  return (
    <div className="flex flex-col gap-2">
      <div className={LINE_TABLE_SCROLL_CLASS}>
        <div className={COST_ROW_MIN_WIDTH_CLASS}>
          <div
            className={`${COST_ROW_GRID_CLASS} border-b bg-muted/40 px-2 py-1.5 text-[11px] font-medium text-muted-foreground`}
          >
            <span>{t('workOrders.costs.editor.headers.product')}</span>
            <span>{t('workOrders.costs.editor.headers.code')}</span>
            <span>{t('workOrders.costs.editor.headers.quantity')}</span>
            <span>{t('workOrders.costs.editor.headers.unit')}</span>
            <span>{t('workOrders.costs.editor.headers.unitPrice')}</span>
            <span>{t('workOrders.costs.editor.headers.vatRate')}</span>
            <span>{t('workOrders.costs.editor.headers.revenueLine')}</span>
            <span className="text-right">{t('workOrders.costs.editor.headers.net')}</span>
            <span className="text-right">{t('workOrders.costs.editor.headers.vat')}</span>
            <span className="text-right">{t('workOrders.costs.editor.headers.total')}</span>
            <span className="sr-only">{t('workOrders.costs.editor.headers.remove')}</span>
          </div>

          {rows.length === 0 ? (
            <p className="px-2 py-3 text-xs text-muted-foreground">{t('workOrders.costs.editor.empty')}</p>
          ) : (
            rows.map((row, index) => (
              <WorkOrderCostRow
                key={rowKeys[index]}
                index={index}
                row={row}
                disabled={readOnly}
                revenueOptions={revenueOptions}
                error={rowErrors(index)}
                onChangeField={(patch) => onChangeField(index, patch)}
                onChangeProduct={(item) => onChangeProduct(index, item)}
                onRemove={() => onRemove(index)}
              />
            ))
          )}
        </div>
      </div>

      {readOnly ? null : (
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={onAdd}
          className="w-full justify-center border-dashed bg-card text-muted-foreground hover:border-solid hover:text-foreground"
        >
          <Plus aria-hidden="true" className="size-3.5" />
          {t('workOrders.costs.editor.add')}
        </Button>
      )}
    </div>
  )
}
