import { useCallback, type RefObject } from 'react'
import { useTranslation } from 'react-i18next'
import { FilterTabs } from '@/components/ui/filter-tabs'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { TableRow } from '@/features/table/types'
import { absenceColumnRenderers } from '@/features/absences/requests/absence-column-renderers'
import { useAbsenceBulkActions } from '@/features/absences/requests/use-absence-bulk-actions'
import { useAbsenceRowDecision } from '@/features/absences/requests/use-absence-row-decision'
import { ABSENCE_SCOPES, ABSENCE_SCOPE_DOMAINS, type AbsenceScope } from '@/features/absences/types'

interface AbsenceRequestsGridProps {
  tableRef: RefObject<TableViewHandle | null>
  /** Opens the detail of the clicked request. */
  onOpen: (id: number) => void
  scope: AbsenceScope
  onScopeChange: (scope: AbsenceScope) => void
  /** Requests the actor has to decide: the counter of the "To approve" tab. */
  toApproveCount: number
}

/** Only the rows the actor may decide can join a multiple decision. */
const isDecidable = (row: TableRow) => row.actions.includes('approve')

/**
 * Requests grid with the "Mine / To approve / All" tabs (spec 0278): each tab is
 * its own table domain, and the grid is remounted on every switch so search,
 * filters and paging restart. "To approve" adds the quick decisions (spec 0304
 * D-17): approve/reject on the row and on a multiple selection.
 */
export function AbsenceRequestsGrid({ tableRef, onOpen, scope, onScopeChange, toApproveCount }: AbsenceRequestsGridProps) {
  const { t } = useTranslation()
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [tableRef])
  const clearSelection = useCallback(() => tableRef.current?.clearSelection(), [tableRef])

  const row = useAbsenceRowDecision(onOpen, refreshGrid)
  const bulk = useAbsenceBulkActions({ refreshGrid, clearSelection })
  const decidable = scope === 'to_approve'

  return (
    <div className="flex flex-1 flex-col gap-4">
      <FilterTabs<AbsenceScope>
        aria-label={t('absences.page.tabsLabel')}
        value={scope}
        options={ABSENCE_SCOPES.map((value) => ({
          value,
          label: t(`absences.page.tabs.${value}`),
          count: value === 'to_approve' ? toApproveCount : undefined,
        }))}
        onValueChange={onScopeChange}
      />
      <TableView
        key={scope}
        ref={tableRef}
        domain={ABSENCE_SCOPE_DOMAINS[scope]}
        renderers={absenceColumnRenderers}
        onAction={row.handleAction}
        inlineAllActions
        isRowSelectable={decidable ? isDecidable : undefined}
        getBulkActions={decidable ? bulk.getBulkActions : undefined}
        disableBuiltinDelete
      />
      {row.dialogSlot}
      {bulk.dialogSlot}
    </div>
  )
}
