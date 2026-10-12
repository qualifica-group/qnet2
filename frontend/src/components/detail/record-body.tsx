import type { ReactNode } from 'react'
import { RecordCollaborationBody } from '@/components/detail/record-collaboration-body'
import type { RecordCollaborationTab } from '@/components/detail/record-collaboration-card'
import { RECORD_BODY_GRID_CLASS, RECORD_COLUMN_CLASS } from '@/components/detail/record-layout'
import { RecordCard } from '@/components/detail/record-panel'

const NO_TABS: RecordCollaborationTab[] = []

interface RecordBodyProps {
  /** Top of the record card: identity band, domain actions, KPI strip. Always visible. */
  header: ReactNode
  /** The record's sections, under the header — the "Dettagli" tab on narrow bodies. None for a header-only record. */
  children?: ReactNode
  /** Already filtered by the caller's authorization gates; none = no side column, full-width record. */
  collaborationTabs?: RecordCollaborationTab[]
}

/**
 * The ONE layout of every record detail: a single record card (header, then
 * the sections) and, when there are collaboration tabs, the side column that
 * carries them — collapsible on wide bodies, merged into the card's own tab
 * strip on narrow ones (`RecordCollaborationBody`). A module only says what
 * goes in the header, in the sections and in the tabs.
 */
export function RecordBody({ header, children, collaborationTabs = NO_TABS }: RecordBodyProps) {
  if (collaborationTabs.length > 0) {
    return (
      <RecordCollaborationBody header={header} tabs={collaborationTabs}>
        {children}
      </RecordCollaborationBody>
    )
  }

  return (
    <div className={RECORD_BODY_GRID_CLASS}>
      <div className={RECORD_COLUMN_CLASS}>
        <RecordCard>
          {header}
          {children}
        </RecordCard>
      </div>
    </div>
  )
}
