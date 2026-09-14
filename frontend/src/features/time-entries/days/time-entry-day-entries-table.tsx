/**
 * The expanded day card's entries table (spec 0122 D-14): a client-side AG
 * Grid, fixed columns, no SSRM — built directly on `AgGridReact` (like the
 * embedded `QuotesPanelGrid` panel) rather than the backend-driven `DataTable`
 * wrapper, since there is no `TableDefinition` for this frozen schema.
 */

import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { AgGridReact } from 'ag-grid-react'
import { AG_GRID_LOCALE_EN, AG_GRID_LOCALE_IT } from '@ag-grid-community/locale'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { setupAgGrid } from '@/components/data-table/ag-grid-setup'
import { buildDataTableTheme, estimateGridHeight } from '@/components/data-table/data-table-theme'
import { useUiScale } from '@/features/appearance/ui-scale-context'
import { cn } from '@/lib/utils'
import { buildTimeEntryColumns } from '@/features/time-entries/days/time-entry-entries-columns'
import { useTimeEntryRowMutations } from '@/features/time-entries/days/use-time-entry-row-mutations'
import { useTimeEntryTaskTypeOptions } from '@/features/time-entries/days/use-time-entry-task-type-options'
import type { TimeEntry } from '@/features/time-entries/types'

setupAgGrid()

/** Minimum visible rows (D-14: "altezza minima 10 righe come q-net"). */
const MIN_VISIBLE_ROWS = 10

/** Same framed surface as the shared `TableView` block, so this grid reads like every other module's table. */
const GRID_FRAME_CLASS = 'overflow-hidden rounded-xl border border-border bg-card shadow-sm'

interface TimeEntryDayEntriesTableProps {
  entries: TimeEntry[]
  canWrite: boolean
  onEditEntry: (entryId: number) => void
}

export function TimeEntryDayEntriesTable({ entries, canWrite, onEditEntry }: TimeEntryDayEntriesTableProps) {
  const { t, i18n } = useTranslation()
  const { factor } = useUiScale()
  const { options: taskTypeOptions } = useTimeEntryTaskTypeOptions(canWrite)
  const { changeType, deleteEntry, isDeleting } = useTimeEntryRowMutations()
  const [deletingEntry, setDeletingEntry] = useState<TimeEntry | null>(null)

  const theme = useMemo(() => buildDataTableTheme(factor), [factor])
  const localeText = useMemo(
    () => (i18n.language.startsWith('it') ? AG_GRID_LOCALE_IT : AG_GRID_LOCALE_EN),
    [i18n.language],
  )
  const columnDefs = useMemo(
    () =>
      buildTimeEntryColumns({
        canWrite,
        taskTypeOptions,
        onEditEntry,
        onChangeType: changeType,
        onRequestDelete: setDeletingEntry,
        t,
      }),
    [canWrite, changeType, onEditEntry, t, taskTypeOptions],
  )

  const gridHeight = estimateGridHeight(Math.max(entries.length, MIN_VISIBLE_ROWS), factor)

  const confirmDelete = () => {
    if (!deletingEntry) {
      return
    }
    deleteEntry(deletingEntry.id)
    setDeletingEntry(null)
  }

  if (entries.length === 0) {
    return (
      <div
        className={cn(GRID_FRAME_CLASS, 'flex items-center justify-center px-4 py-8 text-center text-sm text-muted-foreground')}
        style={{ minHeight: gridHeight }}
      >
        {t('timeEntries.dayCard.noEntries')}
      </div>
    )
  }

  return (
    <>
      <div className={GRID_FRAME_CLASS} style={{ height: gridHeight }}>
        <AgGridReact<TimeEntry>
          columnDefs={columnDefs}
          getRowId={(params) => String(params.data.id)}
          localeText={localeText}
          rowData={entries}
          suppressCellFocus
          suppressContextMenu
          theme={theme}
        />
      </div>

      <AlertDialog onOpenChange={(open) => !open && setDeletingEntry(null)} open={deletingEntry !== null}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('timeEntries.table.deleteTitle')}</AlertDialogTitle>
            <AlertDialogDescription>{t('timeEntries.table.deleteDescription')}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('timeEntries.table.cancel')}</AlertDialogCancel>
            <AlertDialogAction disabled={isDeleting} onClick={confirmDelete}>
              {t('timeEntries.table.delete')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}
