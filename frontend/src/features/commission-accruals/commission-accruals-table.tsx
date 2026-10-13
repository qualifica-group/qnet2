import { useRef } from 'react'
import { PageHeader } from '@/components/page-header'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import { commissionAccrualColumnRenderers } from '@/features/commission-accruals/column-renderers'
import { CommissionAccrualQuickFilterToolbar } from '@/features/commission-accruals/commission-accrual-quick-filter-toolbar'
import { CommissionAccrualTotalsFooter } from '@/features/commission-accruals/commission-accrual-totals-footer'
import { COMMISSION_ACCRUALS_DOMAIN } from '@/features/commission-accruals/types'
import { useAccrualQuickFilter } from '@/features/commission-accruals/use-commission-accrual-quick-filter'

/** The ledger exposes no row action: the table still requires a handler, so it is an explicit no-op. */
const NO_ROW_ACTIONS: RowActionHandler = () => undefined

/**
 * Read-only commission accruals adapter over the generic table (spec 0212):
 * the quick filter writes into the grid filter model, totals come from
 * `meta.aggregates` and row grouping is driven by the table config. The
 * ledger has no write endpoint, so no row action or bulk action is wired.
 */
export function CommissionAccrualsTable() {
  const tableRef = useRef<TableViewHandle>(null)
  const quickFilter = useAccrualQuickFilter(tableRef)

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader />
      <CommissionAccrualQuickFilterToolbar value={quickFilter.quickFilter} onChange={quickFilter.setQuickFilter} />

      <TableView
        ref={tableRef}
        domain={COMMISSION_ACCRUALS_DOMAIN}
        renderers={commissionAccrualColumnRenderers}
        onAction={NO_ROW_ACTIONS}
        forcedFilterModel={quickFilter.forcedFilterModel}
        onFilterModelChange={quickFilter.onFilterModelChange}
        renderFooter={(aggregates) => <CommissionAccrualTotalsFooter aggregates={aggregates} />}
      />
    </div>
  )
}
