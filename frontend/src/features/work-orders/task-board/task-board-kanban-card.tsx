/**
 * Board kanban's own compact card (D-5): a leaf, no recursion — sub-tasks
 * collapse into a single "closed/total" count here, the list view is where
 * they expand. Same building blocks as the list row (end date chip,
 * completion, hours, people with the profile hover card), just stacked. Draggable across columns via the SAME `move` semantics the
 * list view rows use (`use-task-board-dnd.ts`).
 */

import { useSortable } from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { GripVertical } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import { cn } from '@/lib/utils'
import { TaskLookupBadge } from '@/features/tasks/task-lookup-badge'
import { openTaskOnCardClick } from '@/features/work-orders/task-board/task-board-card-click'
import { TaskBoardEndDate } from '@/features/work-orders/task-board/task-board-end-date'
import { isClosedTask } from '@/features/work-orders/task-board/task-board-metrics'
import { TaskBoardCompletion, TaskBoardHours, TaskBoardPeople } from '@/features/work-orders/task-board/task-board-task-meta'
import type { BoardTaskNode } from '@/features/work-orders/task-board/task-board-filters'

interface TaskBoardKanbanCardProps {
  node: BoardTaskNode
  today: string
  isReadOnly: boolean
  isSelected: boolean
  onToggleSelection: (taskId: number) => void
  onOpenTask: (taskId: number) => void
}

export function TaskBoardKanbanCard({
  node,
  today,
  isReadOnly,
  isSelected,
  onToggleSelection,
  onOpenTask,
}: TaskBoardKanbanCardProps) {
  const { t } = useTranslation()
  const { task, children } = node

  const canDrag = !isReadOnly && task.permissions.resource.update
  const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } = useSortable({
    id: String(task.id),
    data: { type: 'task', stageId: task.work_order_stage_id },
    disabled: !canDrag,
  })

  const closedSubtasks = children.filter((child) => isClosedTask(child.task)).length

  return (
    <li
      ref={setNodeRef}
      style={{ transform: CSS.Transform.toString(transform), transition }}
      onClick={(event) => openTaskOnCardClick(event, () => onOpenTask(task.id))}
      className={cn(
        'flex cursor-pointer flex-col gap-2 rounded-lg bg-card p-3 shadow-sm ring-1 ring-border/70 transition-shadow hover:shadow-md',
        isDragging && 'z-10 rotate-2 opacity-90 shadow-md',
      )}
    >
      <div className="flex items-start gap-1.5">
        {canDrag ? (
          <button
            type="button"
            ref={setActivatorNodeRef}
            aria-label={t('workOrders.taskBoard.task.dragHandleLabel')}
            className="flex shrink-0 touch-none items-center justify-center rounded-sm p-0.5 text-muted-foreground outline-none hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 active:cursor-grabbing"
            {...attributes}
            {...listeners}
          >
            <GripVertical className="size-3.5" aria-hidden="true" />
          </button>
        ) : null}
        {!isReadOnly ? (
          <Checkbox
            checked={isSelected}
            onCheckedChange={() => onToggleSelection(task.id)}
            aria-label={task.title}
            className="mt-0.5 shrink-0"
          />
        ) : null}
        <button
          type="button"
          onClick={() => onOpenTask(task.id)}
          className="min-w-0 flex-1 truncate text-left text-sm font-semibold text-foreground hover:text-primary hover:underline"
        >
          {task.title}
        </button>
      </div>

      {task.description_excerpt ? (
        <p className="line-clamp-2 text-xs text-muted-foreground/80" title={task.description_excerpt}>
          {task.description_excerpt}
        </p>
      ) : null}

      <div className="flex flex-wrap items-center gap-1.5">
        <TaskLookupBadge value={task.task_status} />
        <TaskLookupBadge value={task.task_priority} />
        <TaskBoardEndDate task={task} today={today} />
        {children.length > 0 ? (
          <Badge variant="outline">
            {closedSubtasks}/{children.length}
          </Badge>
        ) : null}
      </div>

      <div className="flex flex-col gap-1.5 border-t border-border/60 pt-2 text-xs">
        <div className="flex items-center gap-1.5">
          <TaskBoardCompletion
            percentage={task.task_status.completion_percentage}
            label={t('workOrders.taskBoard.task.columns.completion')}
          />
        </div>
        <div className="flex items-center gap-1.5">
          <TaskBoardHours
            actualMinutes={task.actual_minutes}
            estimatedMinutes={task.estimated_minutes}
            label={t('workOrders.taskBoard.task.columns.hours')}
          />
        </div>
        <div className="flex min-w-0 items-center gap-1.5">
          <TaskBoardPeople people={task.assignees} />
        </div>
      </div>
    </li>
  )
}
