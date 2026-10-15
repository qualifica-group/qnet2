import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { FilePlus2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { CommissionAdjustmentSheet } from '@/features/commission-settlements/commission-adjustment-sheet'
import { commissionSettlementColumnRenderers } from '@/features/commission-settlements/column-renderers'
import { CommissionSettlementDetailSheet } from '@/features/commission-settlements/commission-settlement-detail-sheet'
import { CommissionSettlementQuickFilterToolbar } from '@/features/commission-settlements/commission-settlement-quick-filter-toolbar'
import { CommissionSettlementTotalsFooter } from '@/features/commission-settlements/commission-settlement-totals-footer'
import { COMMISSION_SETTLEMENTS_DOMAIN } from '@/features/commission-settlements/types'
import { useSettlementQuickFilter } from '@/features/commission-settlements/use-settlement-quick-filter'

/**
 * Settlements adapter over the generic table (spec 0213): the quick filter
 * writes into the grid filter model (default Bozze), totals come from
 * `meta.aggregates`, row grouping is driven by the table config. `view` opens
 * the detail sheet where the state transitions live; "Nuova rettifica" opens
 * the standalone adjustment form.
 */
export function CommissionSettlementsTable() {
  const { t } = useTranslation()
  const tableRef = useRef<TableViewHandle>(null)
  const quickFilter = useSettlementQuickFilter(tableRef)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [viewId, setViewId] = useState<number | null>(null)
  const [adjusting, setAdjusting] = useState(false)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const handleAction: RowActionHandler = useCallback((action: TableActionDefinition, row: TableRow) => {
    if (action.key === 'view') {
      setViewId(Number(row.id))
    } else if (action.key === 'activity') {
      setActivityRow(row)
    }
  }, [])

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Can permission="commission-settlements.adjust">
            <Button onClick={() => setAdjusting(true)}>
              <FilePlus2 aria-hidden="true" />
              {t('commissionSettlements.actions.newAdjustment')}
            </Button>
          </Can>
        }
      />
      <CommissionSettlementQuickFilterToolbar value={quickFilter.quickFilter} onChange={quickFilter.setQuickFilter} />

      <TableView
        ref={tableRef}
        domain={COMMISSION_SETTLEMENTS_DOMAIN}
        renderers={commissionSettlementColumnRenderers}
        onAction={handleAction}
        forcedFilterModel={quickFilter.forcedFilterModel}
        onFilterModelChange={quickFilter.onFilterModelChange}
        renderFooter={(aggregates) => <CommissionSettlementTotalsFooter aggregates={aggregates} />}
      />

      <CommissionSettlementDetailSheet settlementId={viewId} onClose={() => setViewId(null)} onChanged={refreshGrid} />
      <CommissionAdjustmentSheet
        mode={adjusting ? { type: 'standalone' } : null}
        onClose={() => setAdjusting(false)}
        onSaved={refreshGrid}
      />
      <ResourceActivityDialog
        resource={COMMISSION_SETTLEMENTS_DOMAIN}
        row={activityRow}
        onOpenChange={(open) => (open ? undefined : setActivityRow(null))}
      />
    </div>
  )
}
