/**
 * The Commessa detail's Task board (spec 0146, D-5/AC-024): replaces the
 * `TableView domain="tasks"` panel (`work-order-tasks-section.tsx`, spec
 * 0133/D-10) with the grouped Lista/Board kanban pair. Orchestrates
 * `use-task-board-state.ts` (filters/selection/derived groups) and
 * `use-task-board-view-mode.ts` (persisted Lista/Board toggle); every view
 * below only renders what it is handed.
 */

import { useCallback, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Layers, MoreHorizontal, Plus } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { Skeleton } from '@/components/ui/skeleton'
import { RecordCard, RecordCardHeader } from '@/components/detail/record-panel'
import { useAbilities } from '@/features/auth/use-abilities'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { TASKS_DOMAIN } from '@/features/tasks/api'
import { TaskBoardBulkBar } from '@/features/work-orders/task-board/task-board-bulk-bar'
import { TaskBoardKanbanView } from '@/features/work-orders/task-board/task-board-kanban-view'
import { TaskBoardKpis } from '@/features/work-orders/task-board/task-board-kpis'
import { TaskBoardListView } from '@/features/work-orders/task-board/task-board-list-view'
import { TaskBoardStageCreateDialog } from '@/features/work-orders/task-board/task-board-stage-create-dialog'
import { TaskBoardToolbar } from '@/features/work-orders/task-board/task-board-toolbar'
import { DEFAULT_TASK_BOARD_FILTERS } from '@/features/work-orders/task-board/task-board-filters'
import { useInvalidateTaskBoard } from '@/features/work-orders/task-board/use-task-board-mutations'
import { useTaskBoardState } from '@/features/work-orders/task-board/use-task-board-state'
import { useTaskBoardViewMode } from '@/features/work-orders/task-board/use-task-board-view-mode'

interface WorkOrderTaskBoardProps {
  workOrderId: number
}

export function WorkOrderTaskBoard({ workOrderId }: WorkOrderTaskBoardProps) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const invalidateBoard = useInvalidateTaskBoard(workOrderId)

  const { openCreateWith, openView, sheet } = useModuleOpener(TASKS_DOMAIN, {
    onSaved: invalidateBoard,
    forceMode: OPEN_MODE_MODAL,
    viewAfterCreate: true,
  })
  const openTask = useCallback((taskId: number) => openView({ id: taskId, actions: [] }), [openView])
  const addTask = useCallback(
    (stageId: number | null) =>
      openCreateWith(
        stageId === null ? { work_order_id: workOrderId } : { work_order_id: workOrderId, work_order_stage_id: stageId },
      ),
    [openCreateWith, workOrderId],
  )

  const state = useTaskBoardState(workOrderId)
  const { viewMode, setViewMode } = useTaskBoardViewMode()
  const [isCreatingStage, setIsCreatingStage] = useState(false)

  const hasAnyStage = (state.payload?.stages.length ?? 0) > 0
  const rootCount = state.payload?.tasks.filter((task) => task.parent_task_id === null).length ?? 0
  const selectedIds = [...state.selectedTaskIds]

  return (
    <RecordCard>
      <RecordCardHeader
        title={
          <span className="flex items-center gap-2">
            <span className="truncate">{t('workOrders.taskBoard.title')}</span>
            <Badge variant="secondary" aria-label={t('workOrders.taskBoard.countLabel', { count: rootCount })}>
              {rootCount}
            </Badge>
          </span>
        }
        actions={
          <span className="flex items-center gap-1.5">
            {!state.isReadOnly && can('tasks.create') ? (
              <Button size="sm" onClick={() => addTask(null)}>
                <Plus aria-hidden="true" />
                {t('tasks.form.newTask')}
              </Button>
            ) : null}
            {!state.isReadOnly ? (
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button size="icon-sm" variant="outline" className="bg-card" aria-label={t('workOrders.taskBoard.headerMenuLabel')}>
                    <MoreHorizontal aria-hidden="true" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                  <DropdownMenuItem onSelect={() => setIsCreatingStage(true)}>
                    <Layers aria-hidden="true" />
                    {t('workOrders.taskBoard.stage.new')}
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            ) : null}
          </span>
        }
      />

      {/* Rung-2 canvas under the white header: toolbar, KPI tiles and each phase's task card are raised on it. */}
      <div className="flex min-w-0 flex-col gap-4 bg-surface p-4">
        {state.isReadOnly && state.payload ? (
          <p className="rounded-lg bg-card px-3 py-2 text-xs text-muted-foreground ring-1 ring-border/70" role="status">
            {t('workOrders.taskBoard.readOnly')}
          </p>
        ) : null}

        {state.boardQuery.isLoading ? (
          <div className="flex flex-col gap-2" aria-hidden="true">
            <Skeleton className="h-9 w-full" />
            <Skeleton className="h-24 w-full" />
            <Skeleton className="h-24 w-full" />
          </div>
        ) : state.boardQuery.isError ? (
          <div className="flex flex-col items-start gap-3">
            <p className="text-sm text-destructive" role="alert">
              {t('workOrders.taskBoard.loadError')}
            </p>
            <Button variant="outline" size="sm" onClick={() => state.boardQuery.refetch()}>
              {t('common.retry')}
            </Button>
          </div>
        ) : !state.hasAnyTask && !hasAnyStage ? (
          <p className="py-6 text-center text-sm text-muted-foreground">{t('workOrders.taskBoard.empty.noTasks')}</p>
        ) : (
          <>
            <TaskBoardKpis metrics={state.metrics} />

            <TaskBoardToolbar
              filters={state.filters}
              onFiltersChange={state.setFilters}
              options={state.filterOptions}
              viewMode={viewMode}
              onViewModeChange={setViewMode}
            />

            {state.hasAnyTask && !state.hasVisibleTask ? (
              <div className="flex flex-col items-center gap-2 py-6">
                <p className="text-sm text-muted-foreground">{t('workOrders.taskBoard.empty.noResults')}</p>
                <Button variant="outline" size="sm" className="bg-card" onClick={() => state.setFilters(DEFAULT_TASK_BOARD_FILTERS)}>
                  {t('workOrders.taskBoard.filters.reset')}
                </Button>
              </div>
            ) : viewMode === 'kanban' ? (
              <TaskBoardKanbanView
                workOrderId={workOrderId}
                groups={state.groups}
                allGroups={state.allGroups}
                today={state.today}
                isReadOnly={state.isReadOnly}
                selectedTaskIds={state.selectedTaskIds}
                onToggleSelection={state.toggleTaskSelection}
                onOpenTask={openTask}
                onAddTask={addTask}
                unstagedLoggedMinutes={state.payload?.unstaged_logged_minutes ?? 0}
              />
            ) : (
              <TaskBoardListView
                workOrderId={workOrderId}
                groups={state.groups}
                allGroups={state.allGroups}
                today={state.today}
                isReadOnly={state.isReadOnly}
                selectedTaskIds={state.selectedTaskIds}
                onToggleSelection={state.toggleTaskSelection}
                onOpenTask={openTask}
                onAddTask={addTask}
                unstagedLoggedMinutes={state.payload?.unstaged_logged_minutes ?? 0}
              />
            )}
          </>
        )}
      </div>

      <TaskBoardBulkBar workOrderId={workOrderId} selectedTaskIds={selectedIds} onClear={state.clearSelection} />

      <TaskBoardStageCreateDialog workOrderId={workOrderId} open={isCreatingStage} onOpenChange={setIsCreatingStage} />

      {sheet}
    </RecordCard>
  )
}
