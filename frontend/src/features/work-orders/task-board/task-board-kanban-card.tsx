/**
 * Board kanban's own compact card (D-5): a leaf, no recursion — sub-tasks
 * collapse into a single "closed/total" count here, the list view is where
 * they expand. Same building blocks as the list row (end date chip,
 * completion, hours, people with the profile hover card), just stacked.
 * Draggable across columns via the SAME `move` semantics the list view rows
 * use (`use-task-board-dnd.ts`).
 *
 * Chrome (the `<li>`, drag-transform, header row, badges/footer slots) is
 * the shared `KanbanCardShell` (spec 0157 D-6); this component only owns
 * what is board-specific: `useSortable` (in-column reorder), the selection
 * checkbox, and the sub-task count badge.
 */

import { useSortable } from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { GripVertical } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { CompletionBar } from '@/components/completion-bar'
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import { KanbanCardShell } from '@/components/kanban/kanban-card-shell'
import { TaskLookupBadge } from '@/features/tasks/task-lookup-badge'
import { openTaskOnCardClick } from '@/features/work-orders/task-board/task-board-card-click'
import { TaskBoardEndDate } from '@/features/work-orders/task-board/task-board-end-date'
import { isClosedTask } from '@/features/work-orders/task-board/task-board-metrics'
import { TaskBoardHours, TaskBoardPeople } from '@/features/work-orders/task-board/task-board-task-meta'
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
    <KanbanCardShell
      cardRef={setNodeRef}
      style={{ transform: CSS.Transform.toString(transform), transition }}
      isDragging={isDragging}
      onCardClick={(event) => openTaskOnCardClick(event, () => onOpenTask(task.id))}
      dragHandle={
        canDrag ? (
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
        ) : null
      }
      selection={
        !isReadOnly ? (
          <Checkbox
            checked={isSelected}
            onCheckedChange={() => onToggleSelection(task.id)}
            aria-label={task.title}
            className="mt-0.5 shrink-0"
          />
        ) : null
      }
      title={task.title}
      onTitleClick={() => onOpenTask(task.id)}
      description={task.description_excerpt}
      badges={
        <>
          <TaskLookupBadge value={task.task_status} />
          <TaskLookupBadge value={task.task_priority} />
          <TaskBoardEndDate task={task} today={today} />
          {children.length > 0 ? (
            <Badge variant="outline">
              {closedSubtasks}/{children.length}
            </Badge>
          ) : null}
        </>
      }
      footer={
        <>
          <CompletionBar
            value={task.task_status.completion_percentage}
            label={t('workOrders.taskBoard.task.columns.completion')}
            barClassName="w-14"
            className="gap-1.5"
          />
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
        </>
      }
    />
  )
}
