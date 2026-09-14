/* eslint-disable react-refresh/only-export-components -- cell renderer module (mirrors `features/table/cell-renderers.tsx`): AG Grid render functions, not route/page components */
/**
 * Fixed `ColDef<TimeEntry>[]` of the day entries table (spec 0122 D-14): one
 * factory, no backend-driven schema (unlike the generic `DataTable`) — the
 * seven columns are frozen by the spec, so a plain array is the minimal
 * solution. The title/actions cell renderers live in
 * `time-entry-entry-cells.tsx` (engineering.md §6, 300-line soft limit).
 */

import { Link } from 'react-router-dom'
import type { ColDef, ICellRendererParams } from 'ag-grid-community'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { formatMinutesLabel } from '@/features/time-entries/time-entry-format'
import { EntryActionsCell, EntryTitleCell } from '@/features/time-entries/days/time-entry-entry-cells'
import type { TaskTypeForSelectItem } from '@/features/task-types/for-select-api'
import type { TimeEntry } from '@/features/time-entries/types'

const EMPTY_VALUE = '—'

function EmptyCell() {
  return <span className="text-muted-foreground">{EMPTY_VALUE}</span>
}

function TruncatedText({ text }: { text: string }) {
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <span className="block min-w-0 truncate">{text}</span>
      </TooltipTrigger>
      <TooltipContent className="max-w-sm" side="top">
        {text}
      </TooltipContent>
    </Tooltip>
  )
}

/** `HH:MM - HH:MM` (D-14); em dash when the entry carries no schedule (D-6 makes both optional). */
function formatScheduleLabel(entry: TimeEntry): string {
  return entry.start_time && entry.end_time ? `${entry.start_time} - ${entry.end_time}` : EMPTY_VALUE
}

interface BuildTimeEntryColumnsOptions {
  canWrite: boolean
  taskTypeOptions: TaskTypeForSelectItem[]
  onEditEntry: (entryId: number) => void
  onChangeType: (entry: TimeEntry, taskTypeId: number) => void
  onRequestDelete: (entry: TimeEntry) => void
  t: (key: string) => string
}

/** No column is sortable/filterable/resizable/movable (D-14): the schema is frozen. */
const FROZEN_COLUMN_DEFAULTS: Partial<ColDef<TimeEntry>> = {
  sortable: false,
  filter: false,
  resizable: false,
  suppressMovable: true,
  suppressHeaderMenuButton: true,
  suppressHeaderFilterButton: true,
}

export function buildTimeEntryColumns({
  canWrite,
  taskTypeOptions,
  onEditEntry,
  onChangeType,
  onRequestDelete,
  t,
}: BuildTimeEntryColumnsOptions): ColDef<TimeEntry>[] {
  return [
    {
      ...FROZEN_COLUMN_DEFAULTS,
      colId: 'actions',
      headerName: t('timeEntries.table.actions'),
      // Leading column pinned left, like the shared `DataTable` actions column.
      pinned: 'left',
      width: 72,
      cellRenderer: (params: ICellRendererParams<TimeEntry>) =>
        params.data ? (
          <EntryActionsCell
            actionsLabel={t('timeEntries.table.actions')}
            canWrite={canWrite}
            deleteLabel={t('timeEntries.table.delete')}
            editLabel={t('timeEntries.table.edit')}
            entry={params.data}
            onEditEntry={onEditEntry}
            onRequestDelete={onRequestDelete}
          />
        ) : null,
    },
    {
      ...FROZEN_COLUMN_DEFAULTS,
      colId: 'title',
      headerName: t('timeEntries.table.title'),
      flex: 2,
      minWidth: 220,
      cellRenderer: (params: ICellRendererParams<TimeEntry>) =>
        params.data ? (
          <EntryTitleCell
            canWrite={canWrite}
            entry={params.data}
            onChangeType={onChangeType}
            onEditEntry={onEditEntry}
            taskTypeOptions={taskTypeOptions}
          />
        ) : null,
    },
    {
      ...FROZEN_COLUMN_DEFAULTS,
      colId: 'duration',
      headerName: t('timeEntries.table.duration'),
      width: 100,
      cellRenderer: (params: ICellRendererParams<TimeEntry>) =>
        params.data ? (
          <div className="flex h-full items-center tabular-nums">
            {formatMinutesLabel(params.data.minutes)}
          </div>
        ) : null,
    },
    {
      ...FROZEN_COLUMN_DEFAULTS,
      colId: 'schedule',
      headerName: t('timeEntries.table.schedule'),
      width: 130,
      cellRenderer: (params: ICellRendererParams<TimeEntry>) =>
        params.data ? (
          <div className="flex h-full items-center tabular-nums text-muted-foreground">
            {formatScheduleLabel(params.data)}
          </div>
        ) : null,
    },
    {
      ...FROZEN_COLUMN_DEFAULTS,
      colId: 'task',
      headerName: t('timeEntries.table.task'),
      flex: 1,
      minWidth: 140,
      cellRenderer: (params: ICellRendererParams<TimeEntry>) => {
        const task = params.data?.task
        if (!task) {
          return (
            <div className="flex h-full items-center">
              <EmptyCell />
            </div>
          )
        }
        return (
          <div className="flex h-full min-w-0 items-center">
            <Tooltip>
              <TooltipTrigger asChild>
                <Link className="block min-w-0 truncate text-primary hover:underline" to={`/tasks/${task.id}`}>
                  {task.title}
                </Link>
              </TooltipTrigger>
              <TooltipContent className="max-w-sm" side="top">
                {task.title}
              </TooltipContent>
            </Tooltip>
          </div>
        )
      },
    },
    {
      ...FROZEN_COLUMN_DEFAULTS,
      colId: 'workOrder',
      headerName: t('timeEntries.table.workOrder'),
      flex: 1,
      minWidth: 140,
      cellRenderer: (params: ICellRendererParams<TimeEntry>) => {
        const workOrder = params.data?.work_order
        return (
          <div className="flex h-full min-w-0 items-center">
            {workOrder ? <TruncatedText text={`${workOrder.code} - ${workOrder.title}`} /> : <EmptyCell />}
          </div>
        )
      },
    },
    {
      ...FROZEN_COLUMN_DEFAULTS,
      colId: 'opportunity',
      headerName: t('timeEntries.table.opportunity'),
      flex: 1,
      minWidth: 140,
      cellRenderer: (params: ICellRendererParams<TimeEntry>) => {
        const opportunity = params.data?.opportunity
        return (
          <div className="flex h-full min-w-0 items-center">
            {opportunity ? <TruncatedText text={opportunity.name} /> : <EmptyCell />}
          </div>
        )
      },
    },
    {
      ...FROZEN_COLUMN_DEFAULTS,
      colId: 'registry',
      headerName: t('timeEntries.table.registry'),
      flex: 1,
      minWidth: 140,
      cellRenderer: (params: ICellRendererParams<TimeEntry>) => {
        const registry = params.data?.registry
        return (
          <div className="flex h-full min-w-0 items-center">
            {registry ? <TruncatedText text={registry.name} /> : <EmptyCell />}
          </div>
        )
      },
    },
  ]
}
