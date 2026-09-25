/**
 * One kanban column: a fase, or "Senza fase" (D-5). Same accent stripe and
 * counters as the list view's group header, without the collapse/rename/
 * close menu (out of scope for the kanban surface, still available in Lista).
 *
 * Chrome (accent dot/label/count/"+") is the shared `KanbanColumnShell`
 * (spec 0157 D-6); this component only owns what is board-specific: the fase
 * metrics summary line, and the `SortableContext` in-column reorder the
 * board's own `use-task-board-dnd.ts` needs (the `/tasks` Kanban has no
 * position of its own, so its column has no `SortableContext`).
 */

import { useDroppable } from '@dnd-kit/core'
import { SortableContext, verticalListSortingStrategy } from '@dnd-kit/sortable'
import { Plus } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { KanbanColumnShell } from '@/components/kanban/kanban-column-shell'
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
  /** The commessa's `unstaged_logged_minutes` (spec 0163 AC-007): read only when `group.stage === null`. */
  unstagedLoggedMinutes?: number
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
  unstagedLoggedMinutes,
}: TaskBoardKanbanColumnProps) {
  const { t } = useTranslation()
  const { stage, roots } = group
  const key = stage ? String(stage.id) : 'none'
  const { setNodeRef } = useDroppable({ id: groupDroppableId(key) })
  const metrics = computeStageMetrics(roots.map((node) => node.task))
  const loggedMinutes = stage ? stage.logged_minutes : (unstagedLoggedMinutes ?? 0)
  const isClosed = stage?.closed_at != null

  return (
    <KanbanColumnShell
      accentColor={accent.dot}
      label={stage ? stage.name : t('workOrders.taskBoard.noStage')}
      count={metrics.count}
      headerExtra={<TaskBoardStageSummary metrics={metrics} loggedMinutes={loggedMinutes} />}
      addSlot={
        !isReadOnly && !isClosed ? (
          <Button size="xs" variant="ghost" onClick={() => onAddTask(stage?.id ?? null)}>
            <Plus aria-hidden="true" />
            {t('workOrders.taskBoard.stage.addTask')}
          </Button>
        ) : null
      }
    >
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
    </KanbanColumnShell>
  )
}
