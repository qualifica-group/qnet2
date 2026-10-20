import { useCallback, useState, type RefObject } from 'react'
import { useTranslation } from 'react-i18next'
import { FilterTabs } from '@/components/ui/filter-tabs'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import { ABSENCE_SCOPES, ABSENCE_SCOPE_DOMAINS, type AbsenceScope } from '@/features/absences/types'

interface AbsenceRequestsGridProps {
  tableRef: RefObject<TableViewHandle | null>
  /** Opens the detail of the clicked request (every row action leads there: the abilities live in the detail). */
  onOpen: (id: number) => void
}

/**
 * Requests grid with the "Mine / To approve / All" tabs (spec 0278): each tab is
 * its own table domain, and the grid is remounted on every switch so search,
 * filters and paging restart. The server restricts "All" to the actor's team.
 */
export function AbsenceRequestsGrid({ tableRef, onOpen }: AbsenceRequestsGridProps) {
  const { t } = useTranslation()
  const [scope, setScope] = useState<AbsenceScope>('mine')

  const handleAction: RowActionHandler = useCallback((_action, row) => onOpen(Number(row.id)), [onOpen])

  return (
    <div className="flex flex-1 flex-col gap-4">
      <FilterTabs<AbsenceScope>
        aria-label={t('absences.page.tabsLabel')}
        value={scope}
        options={ABSENCE_SCOPES.map((value) => ({ value, label: t(`absences.page.tabs.${value}`) }))}
        onValueChange={setScope}
      />
      <TableView key={scope} ref={tableRef} domain={ABSENCE_SCOPE_DOMAINS[scope]} onAction={handleAction} inlineAllActions />
    </div>
  )
}
