/**
 * One kanban column: a fase, or "Senza fase" (D-5). Same accent stripe and
 * counters as the list view's group header, without the collapse/rename/
 * close menu (out of scope for the kanban surface, still available in Lista).
 */

import { useDroppable } from '@dnd-kit/core'
import { SortableContext, verticalListSortingStrategy } from '@dnd-kit/sortable'
import { Plus } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import type { BoardStageGroup } from '@/features/work-orders/task-board/task-board-filters'
import { computeStageMetrics } from '@/features/work-orders/task-board/task-board-metrics'
import type { StageAccent } from '@/features/work-orders/task-board/task-board-stage-accent'
import { TaskBoardKanbanCard } from '@/features/work-orders/task-board/task-board-kanban-card'
import { TaskBoardStageSummary } from '@/features/work-orders/task-board/task-board-task-meta'
import { groupDroppableId } from '@/features/work-orders/task-board/use-task-board-dnd'

interface TaskBoardKanbanColumnProps {
  group: BoardStageGroup
  accent: StageAccent
  isReadOnly: boolean
  isDragOver: boolean
  today: string
  selectedTaskIds: Set<number>
  onToggleSelection: (taskId: number) => void
  onOpenTask: (taskId: number) => void
  onAddTask: (stageId: number | null) => void
}

export function TaskBoardKanbanColumn({
  group,
  accent,
  isReadOnly,
  isDragOver,
  today,
  selectedTaskIds,
  onToggleSelection,
  onOpenTask,
  onAddTask,
}: TaskBoardKanbanColumnProps) {
  const { t } = useTranslation()
  const { stage, roots } = group
  const key = stage ? String(stage.id) : 'none'
  const { setNodeRef } = useDroppable({ id: groupDroppableId(key) })
  const metrics = computeStageMetrics(roots.map((node) => node.task))
  const isClosed = stage?.closed_at != null

  return (
    <div className="flex w-72 shrink-0 flex-col gap-2">
      <div className="flex flex-col gap-1.5 px-1">
        <div className="flex items-center gap-2">
          <span aria-hidden="true" className={cn('size-2.5 shrink-0 rounded-full', accent.dot)} />
          <h3 className="min-w-0 flex-1 truncate text-sm font-semibold text-foreground">
            {stage ? stage.name : t('workOrders.taskBoard.noStage')}
          </h3>
          <span className="shrink-0 rounded-full bg-muted px-2 py-0.5 text-xs font-medium tabular-nums text-muted-foreground">
            {metrics.count}
          </span>
        </div>
        <TaskBoardStageSummary metrics={metrics} />
      </div>

      <SortableContext items={roots.map((node) => String(node.task.id))} strategy={verticalListSortingStrategy}>
        <ul
          ref={setNodeRef}
          className={cn(
            'flex min-h-24 flex-1 flex-col gap-2 overflow-y-auto rounded-xl bg-muted/40 p-2 transition-colors',
            isDragOver && 'bg-primary/5 ring-2 ring-primary/40',
          )}
        >
          {roots.map((node) => (
            <TaskBoardKanbanCard
              key={node.task.id}
              node={node}
              today={today}
              isReadOnly={isReadOnly}
              isSelected={selectedTaskIds.has(node.task.id)}
              onToggleSelection={onToggleSelection}
              onOpenTask={onOpenTask}
            />
          ))}
        </ul>
      </SortableContext>

      {!isReadOnly && !isClosed ? (
        <Button size="xs" variant="ghost" onClick={() => onAddTask(stage?.id ?? null)}>
          <Plus aria-hidden="true" />
          {t('workOrders.taskBoard.stage.addTask')}
        </Button>
      ) : null}
    </div>
  )
}
