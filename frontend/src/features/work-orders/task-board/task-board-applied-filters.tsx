/**
 * The filters narrowing the board, one chip per dimension (same idiom as
 * `RequestDashboardAppliedFilters`, user directive 2026-09-22). A dimension
 * left at its default ("everything") has no chip (user directive 2026-11-16),
 * and with no chip at all the strip is not rendered.
 */

import { useTranslation } from 'react-i18next'
import { Filter } from 'lucide-react'
import { DEFAULT_TASK_BOARD_FILTERS, type TaskBoardFilterOptions } from '@/features/work-orders/task-board/task-board-filters'
import type { TaskBoardFilters } from '@/features/work-orders/task-board/types'

/** Picked names shown in a chip before the rest collapses into "+N". */
const NAMED_LABELS_MAX = 2

const CHIP_CLASS =
  'inline-flex max-w-64 shrink-0 items-center gap-1 rounded-md bg-primary/10 px-2 py-0.5 text-xs text-primary ring-1 ring-primary/20 ring-inset'

interface AppliedChip {
  key: string
  label: string
  value: string
  title?: string
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

  const chips: AppliedChip[] = []
  if (filters.status !== DEFAULT_TASK_BOARD_FILTERS.status) {
    chips.push({
      key: 'status',
      label: t('workOrders.taskBoard.filters.status'),
      value: t(`workOrders.taskBoard.filters.statusOption.${filters.status}`),
    })
  }
  if (filters.due !== DEFAULT_TASK_BOARD_FILTERS.due) {
    chips.push({
      key: 'due',
      label: t('workOrders.taskBoard.filters.due'),
      value: t(`workOrders.taskBoard.filters.dueOption.${filters.due}`),
    })
  }
  if (filters.assignment !== DEFAULT_TASK_BOARD_FILTERS.assignment) {
    chips.push({
      key: 'assignment',
      label: t('workOrders.taskBoard.filters.assignment'),
      value: t(`workOrders.taskBoard.filters.assignmentOption.${filters.assignment}`),
    })
  }
  for (const dimension of idDimensions) {
    const names = namesOf(dimension.ids, dimension.options)
    if (names.length > 0) {
      chips.push({ key: dimension.key, label: dimension.label, value: summarize(names), title: names.join(', ') })
    }
  }

  if (chips.length === 0) {
    return null
  }

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
        {chips.map((chip) => (
          <li key={chip.key} className={CHIP_CLASS} title={chip.title ?? chip.value}>
            <span className="shrink-0 text-primary/80">{chip.label}</span>
            <span className="truncate font-medium">{chip.value}</span>
          </li>
        ))}
      </ul>
    </div>
  )
}
