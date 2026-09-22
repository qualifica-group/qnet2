/**
 * The filters the board is showing, one chip per dimension (same idiom as
 * `RequestDashboardAppliedFilters`, user directive 2026-09-22): a dimension
 * that narrows the tasks is tinted, one left at "everything" stays neutral.
 * Stato and Scadenza always show (they are the two axes every reader scans);
 * the id-based ones appear only once something is picked, so an untouched
 * board keeps a short, calm strip.
 */

import { useTranslation } from 'react-i18next'
import { Filter } from 'lucide-react'
import type { TaskBoardFilterOptions } from '@/features/work-orders/task-board/task-board-filters'
import type { TaskBoardFilters } from '@/features/work-orders/task-board/types'
import { cn } from '@/lib/utils'

/** Picked names shown in a chip before the rest collapses into "+N". */
const NAMED_LABELS_MAX = 2

const CHIP_CLASS = 'inline-flex max-w-64 shrink-0 items-center gap-1 rounded-md px-2 py-0.5 text-xs ring-1 ring-inset'
const CHIP_ACTIVE_CLASS = 'bg-primary/10 text-primary ring-primary/20'
const CHIP_IDLE_CLASS = 'bg-surface text-foreground ring-border'

interface AppliedChipProps {
  label: string
  value: string
  title?: string
  active: boolean
}

function AppliedChip({ label, value, title, active }: AppliedChipProps) {
  return (
    <li className={cn(CHIP_CLASS, active ? CHIP_ACTIVE_CLASS : CHIP_IDLE_CLASS)} title={title ?? value}>
      <span className={cn('shrink-0', active ? 'text-primary/80' : 'text-muted-foreground')}>{label}</span>
      <span className="truncate font-medium">{value}</span>
    </li>
  )
}

interface NamedOption {
  id: number
  name: string
}

interface IdDimension {
  key: string
  label: string
  ids: number[]
  options: NamedOption[]
}

export interface TaskBoardAppliedFiltersProps {
  filters: TaskBoardFilters
  options: TaskBoardFilterOptions
}

export function TaskBoardAppliedFilters({ filters, options }: TaskBoardAppliedFiltersProps) {
  const { t } = useTranslation()

  const namesOf = (ids: number[], items: NamedOption[]): string[] =>
    items.filter((item) => ids.includes(item.id)).map((item) => item.name)

  const summarize = (names: string[]): string => {
    const named = names.slice(0, NAMED_LABELS_MAX).join(', ')
    const rest = names.length - NAMED_LABELS_MAX

    return rest > 0 ? `${named} ${t('workOrders.taskBoard.filters.moreSelected', { count: rest })}` : named
  }

  const idDimensions: IdDimension[] = [
    { key: 'type', label: t('workOrders.taskBoard.filters.type'), ids: filters.taskTypeIds, options: options.taskTypes },
    {
      key: 'priority',
      label: t('workOrders.taskBoard.filters.priority'),
      ids: filters.taskPriorityIds,
      options: options.taskPriorities,
    },
    {
      key: 'requester',
      label: t('workOrders.taskBoard.filters.requester'),
      ids: filters.requesterIds,
      options: options.requesters,
    },
    {
      key: 'assignee',
      label: t('workOrders.taskBoard.filters.assignee'),
      ids: filters.assigneeIds,
      options: options.assignees,
    },
    { key: 'watcher', label: t('workOrders.taskBoard.filters.watcher'), ids: filters.watcherIds, options: options.watchers },
    {
      key: 'taskStatus',
      label: t('workOrders.taskBoard.filters.taskStatus'),
      ids: filters.taskStatusIds,
      options: options.taskStatuses,
    },
    {
      key: 'importance',
      label: t('workOrders.taskBoard.filters.importance'),
      ids: filters.taskImportanceIds,
      options: options.taskImportances,
    },
  ]

  return (
    <div className="flex min-w-0 items-center gap-2">
      <span className="flex shrink-0 items-center gap-1 text-xs font-medium text-muted-foreground">
        <Filter aria-hidden="true" className="size-3.5" />
        {t('workOrders.taskBoard.filters.applied')}
      </span>
      {/* One horizontal line (user directive 2026-09-22): chips never wrap under each
          other; a long selection scrolls sideways inside the bar, never the page. */}
      <ul
        aria-label={t('workOrders.taskBoard.filters.applied')}
        className="flex min-w-0 flex-1 flex-nowrap items-center gap-1.5 overflow-x-auto py-0.5"
      >
        <AppliedChip
          label={t('workOrders.taskBoard.filters.status')}
          value={t(`workOrders.taskBoard.filters.statusOption.${filters.status}`)}
          active={filters.status !== 'all'}
        />
        <AppliedChip
          label={t('workOrders.taskBoard.filters.due')}
          value={t(`workOrders.taskBoard.filters.dueOption.${filters.due}`)}
          active={filters.due !== 'all'}
        />
        {filters.assignment !== 'all' ? (
          <AppliedChip
            label={t('workOrders.taskBoard.filters.assignment')}
            value={t(`workOrders.taskBoard.filters.assignmentOption.${filters.assignment}`)}
            active
          />
        ) : null}
        {idDimensions.map((dimension) => {
          const names = namesOf(dimension.ids, dimension.options)

          return names.length > 0 ? (
            <AppliedChip
              key={dimension.key}
              label={dimension.label}
              value={summarize(names)}
              title={names.join(', ')}
              active
            />
          ) : null
        })}
      </ul>
    </div>
  )
}
