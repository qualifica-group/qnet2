import type { ICellRendererParams } from 'ag-grid-community'
import { CELL_WRAPPER, EmptyCell } from '@/features/table/cell-renderers'
import { OpportunityStatusBadge } from '@/features/opportunities/opportunity-status-badge'
import type { OpportunityStatusSummary } from '@/features/opportunities/types'

/**
 * The `status` grid cell (spec 0082): the COMPUTED status summary the backend
 * projects, drawn by the SAME badge the detail, the form and the request panel
 * use. Em dash when the row has neither a quote nor a working state.
 */
export function OpportunityStatusCell({ value }: ICellRendererParams) {
  const summary = value as OpportunityStatusSummary | null | undefined

  if (!summary || summary.entries.length === 0) {
    return <EmptyCell />
  }

  return (
    <div className={CELL_WRAPPER}>
      <OpportunityStatusBadge summary={summary} />
    </div>
  )
}
