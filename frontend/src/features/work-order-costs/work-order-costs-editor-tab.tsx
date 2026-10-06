import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { WorkOrderCostsLinesField } from '@/features/work-order-costs/work-order-costs-lines-field'
import type { RevenueLineOption } from '@/features/work-order-costs/work-order-cost-row'
import { useWorkOrderCostsEditor } from '@/features/work-order-costs/use-work-order-costs-editor'
import type { WorkOrderCostOverview } from '@/features/work-order-costs/types'

interface WorkOrderCostsEditorTabProps {
  workOrderId: number
  overview: WorkOrderCostOverview
  /** `work-orders.manageCosts`: without it the grid is read-only (no add row, no save). */
  canManage: boolean
}

/** "Costi effettivi": the inline grid of real costs with one Save replacing the whole set (D-3). */
export function WorkOrderCostsEditorTab({ workOrderId, overview, canManage }: WorkOrderCostsEditorTabProps) {
  const { t } = useTranslation()
  const editor = useWorkOrderCostsEditor(workOrderId, overview.lines)
  const revenueOptions = useMemo<RevenueLineOption[]>(
    () =>
      overview.comparison.rows.map((row) => ({
        quoteLineId: row.quote_line_id,
        label: `${row.product.code} — ${row.product.name}`,
      })),
    [overview.comparison.rows],
  )

  return (
    <form className="flex flex-col gap-3" noValidate onSubmit={(event) => void editor.submit(event)}>
      {canManage ? null : (
        <p className="text-xs text-muted-foreground">{t('workOrders.costs.editor.readOnly')}</p>
      )}

      <WorkOrderCostsLinesField
        rowKeys={editor.fields.map((field) => field.id)}
        rows={editor.rows}
        readOnly={!canManage}
        revenueOptions={revenueOptions}
        rowErrors={editor.rowErrors}
        onChangeField={editor.changeField}
        onChangeProduct={editor.changeProduct}
        onRemove={editor.removeRow}
        onAdd={editor.addRow}
      />

      {canManage ? (
        <div className="flex justify-end gap-2">
          <Button type="button" variant="outline" size="sm" className="bg-card" disabled={editor.isSaving} onClick={editor.cancel}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" size="sm" disabled={editor.isSaving}>
            {t('workOrders.costs.editor.save')}
          </Button>
        </div>
      ) : null}
    </form>
  )
}
