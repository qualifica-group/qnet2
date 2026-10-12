import type { ReactNode } from 'react'
import { RecordCollaborationBody } from '@/components/detail/record-collaboration-body'
import type { RecordCollaborationTab } from '@/components/detail/record-collaboration-card'
import {
  RECORD_BODY_GRID_CLASS,
  RECORD_BODY_WITH_SIDE_CLASS,
  RECORD_COLUMN_CLASS,
} from '@/components/detail/record-layout'
import { cn } from '@/lib/utils'

interface RecordBodyProps {
  /** Main column: the record card (identity header with Edit, KPI strip, sections). */
  children: ReactNode
  /**
   * Side column (collaboration card, related cards). Pass `null` when there is
   * nothing to show: the layout cannot inspect whether a child renders, so
   * the caller decides, and the record then keeps the full width.
   */
  side?: ReactNode
  /**
   * Alternative to `side` when the side column is the collaboration card:
   * fixed-width and collapsible when wide, merged into the record's tab strip
   * when narrow (the caller wraps its sections in `RecordDetailsTabs`).
   */
  collaborationTabs?: RecordCollaborationTab[]
}

/**
 * Two-column body of every record detail (Opportunita' reference layout): the
 * record on the left, the side column on the right at wide container widths,
 * stacked in that order while narrow.
 */
export function RecordBody({ children, side, collaborationTabs }: RecordBodyProps) {
  if (collaborationTabs && collaborationTabs.length > 0) {
    return <RecordCollaborationBody tabs={collaborationTabs}>{children}</RecordCollaborationBody>
  }

  const hasSide = side !== null && side !== undefined && side !== false

  return (
    <div className={cn(RECORD_BODY_GRID_CLASS, hasSide && RECORD_BODY_WITH_SIDE_CLASS)}>
      <div className={RECORD_COLUMN_CLASS}>{children}</div>
      {hasSide ? <div className={RECORD_COLUMN_CLASS}>{side}</div> : null}
    </div>
  )
}
