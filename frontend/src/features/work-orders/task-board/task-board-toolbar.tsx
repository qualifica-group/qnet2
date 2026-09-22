/**
 * The filter bar above the board (D-5/D-6, AC-025), reshaped on Gestione
 * Richieste's dashboard bar (user directive 2026-09-22): one contained strip,
 * controls on top (live title search, "Modifica filtri", Lista/Board) and the
 * applied-filter chips on their own horizontal line below. "Modifica filtri"
 * opens `TaskBoardFiltersSheet`, the only place the other filters are edited. CONTROLLED over `TaskBoardFilters`:
 * the mounting panel owns the value.
 */

import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Search, SlidersHorizontal, X } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { TaskBoardAppliedFilters } from '@/features/work-orders/task-board/task-board-applied-filters'
import { TaskBoardFiltersSheet } from '@/features/work-orders/task-board/task-board-filters-sheet'
import {
  DEFAULT_TASK_BOARD_FILTERS,
  type TaskBoardFilterOptions,
} from '@/features/work-orders/task-board/task-board-filters'
import type { TaskBoardFilters, TaskBoardViewMode } from '@/features/work-orders/task-board/types'
import { cn } from '@/lib/utils'

/** A raised white strip (rung 3) floating on the board's rung-2 canvas: the controls read as a toolbar, not as another panel of content. */
const BAR_CLASS = 'flex flex-col gap-2 rounded-xl bg-card px-3 py-2.5 shadow-sm ring-1 ring-border/70'
const VIEW_MODES: readonly TaskBoardViewMode[] = ['list', 'kanban']

function isDefaultFilters(filters: TaskBoardFilters): boolean {
  return (
    filters.search === DEFAULT_TASK_BOARD_FILTERS.search &&
    filters.due === DEFAULT_TASK_BOARD_FILTERS.due &&
    filters.status === DEFAULT_TASK_BOARD_FILTERS.status &&
    filters.assignment === DEFAULT_TASK_BOARD_FILTERS.assignment &&
    filters.taskTypeIds.length === 0 &&
    filters.requesterIds.length === 0 &&
    filters.assigneeIds.length === 0 &&
    filters.watcherIds.length === 0 &&
    filters.taskPriorityIds.length === 0
  )
}

interface TaskBoardToolbarProps {
  filters: TaskBoardFilters
  onFiltersChange: (filters: TaskBoardFilters) => void
  options: TaskBoardFilterOptions
  viewMode: TaskBoardViewMode
  onViewModeChange: (mode: TaskBoardViewMode) => void
}

export function TaskBoardToolbar({ filters, onFiltersChange, options, viewMode, onViewModeChange }: TaskBoardToolbarProps) {
  const { t } = useTranslation()
  const [isSheetOpen, setIsSheetOpen] = useState(false)

  return (
    <div className={BAR_CLASS}>
      <div className="flex flex-wrap items-center gap-2">
        <div className="relative min-w-0 flex-1 sm:max-w-64">
          <Search
            className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground"
            aria-hidden="true"
          />
          <Input
            type="search"
            value={filters.search}
            onChange={(event) => onFiltersChange({ ...filters, search: event.target.value })}
            placeholder={t('workOrders.taskBoard.filters.searchPlaceholder')}
            aria-label={t('workOrders.taskBoard.filters.searchPlaceholder')}
            className="h-8 pl-8 text-xs"
          />
        </div>

        <div className="ml-auto flex flex-wrap items-center gap-2">
          {!isDefaultFilters(filters) ? (
            <Button
              type="button"
              size="sm"
              variant="ghost"
              className="text-muted-foreground"
              onClick={() => onFiltersChange(DEFAULT_TASK_BOARD_FILTERS)}
            >
              <X aria-hidden="true" className="size-3.5" />
              {t('workOrders.taskBoard.filters.reset')}
            </Button>
          ) : null}

          {/* Icon-only (user directive 2026-09-22): the label stays as the accessible name and hover hint. */}
          <Button
            type="button"
            size="icon-sm"
            variant="outline"
            className="bg-surface"
            onClick={() => setIsSheetOpen(true)}
            aria-label={t('workOrders.taskBoard.filters.edit')}
            title={t('workOrders.taskBoard.filters.edit')}
          >
            <SlidersHorizontal aria-hidden="true" className="size-3.5" />
          </Button>

          {/* Same 32px height as the icon-sm filter button next to it. */}
          <div className="flex h-8 items-center gap-0.5 rounded-md bg-muted/60 p-0.5">
            {VIEW_MODES.map((mode) => (
              <Button
                key={mode}
                type="button"
                size="xs"
                variant={viewMode === mode ? 'default' : 'ghost'}
                className={cn('h-7 px-2.5', viewMode !== mode && 'text-muted-foreground')}
                onClick={() => onViewModeChange(mode)}
                aria-pressed={viewMode === mode}
              >
                {t(`workOrders.taskBoard.viewToggle.${mode}`)}
              </Button>
            ))}
          </div>
        </div>
      </div>

      <TaskBoardAppliedFilters filters={filters} options={options} />

      <TaskBoardFiltersSheet
        open={isSheetOpen}
        onOpenChange={setIsSheetOpen}
        filters={filters}
        options={options}
        onApply={onFiltersChange}
      />
    </div>
  )
}
