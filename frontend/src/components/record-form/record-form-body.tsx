import type { ReactNode } from 'react'
import {
  RECORD_BODY_GRID_CLASS,
  RECORD_BODY_WITH_SIDE_CLASS,
  RECORD_COLUMN_CLASS,
} from '@/components/detail/record-layout'
import { cn } from '@/lib/utils'

interface RecordFormBodyProps {
  /** Main column: the form's record card and its repeated actions. */
  children: ReactNode
  /** Side column (summary, attachments, duplicate warning); absent = full-width form. */
  side?: ReactNode
}

/**
 * Two-column body of the CREATE forms: the form on the left, its side column
 * on the right at wide container widths, stacked in that order while narrow.
 * The record DETAILS use `RecordBody` instead.
 */
export function RecordFormBody({ children, side }: RecordFormBodyProps) {
  const hasSide = side !== null && side !== undefined && side !== false

  return (
    <div className={cn(RECORD_BODY_GRID_CLASS, hasSide && RECORD_BODY_WITH_SIDE_CLASS)}>
      <div className={RECORD_COLUMN_CLASS}>{children}</div>
      {hasSide ? <div className={RECORD_COLUMN_CLASS}>{side}</div> : null}
    </div>
  )
}
