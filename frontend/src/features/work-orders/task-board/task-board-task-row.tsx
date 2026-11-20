/**
 * A ROOT row of the Task board's list view (D-3): the only rows that carry a
 * fase/position, so the only ones with a drag handle and a selection
 * checkbox (D-7's bulk actions select roots only). Its own sub-tasks render
 * as non-draggable `TaskBoardSubtaskRow`s underneath, expandable, their
 * titles indented while their other cells stay in the columns (spec 0401).
 */

import { useState } from 'react'
import { useSortable } from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { GripVertical } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Checkbox } from '@/components/ui/checkbox'
import { cn } from '@/lib/utils'
import { openTaskOnCardClick } from '@/features/work-orders/task-board/task-board-card-click'
import { TaskBoardRowContent } from '@/features/work-orders/task-board/task-board-row-content'
import { TASK_BOARD_LEADING_CLASS, TASK_BOARD_ROW_CLASS } from '@/features/work-orders/task-board/task-board-row-columns'
import { TaskBoardSubtaskRow } from '@/features/work-orders/task-board/task-board-subtask-row'
import type { BoardTaskNode } from '@/features/work-orders/task-board/task-board-filters'

interface TaskBoardTaskRowProps {
  node: BoardTaskNode
  today: string
  isReadOnly: boolean
  isSelected: boolean
  onToggleSelection: (taskId: number) => void
  onOpenTask: (taskId: number) => void
}

export function TaskBoardTaskRow({
  node,
  today,
  isReadOnly,
  isSelected,
  onToggleSelection,
  onOpenTask,
}: TaskBoardTaskRowProps) {
  const { t } = useTranslation()
  const [isExpanded, setIsExpanded] = useState(false)
  const { task, children } = node

  const canDrag = !isReadOnly && task.permissions.resource.update
  const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } = useSortable({
    id: String(task.id),
    data: { type: 'task', stageId: task.work_order_stage_id },
    disabled: !canDrag,
  })

  const style = { transform: CSS.Transform.toString(transform), transition }

  return (
    <li ref={setNodeRef} style={style} className={cn('flex flex-col bg-card', isDragging && 'z-10 opacity-40')}>
      <div
        onClick={(event) => openTaskOnCardClick(event, () => onOpenTask(task.id))}
        className={cn(TASK_BOARD_ROW_CLASS, isSelected && 'bg-primary/5 hover:bg-primary/10')}
      >
        <div className={TASK_BOARD_LEADING_CLASS}>
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
          ) : (
            <span className="size-4.5 shrink-0" aria-hidden="true" />
          )}
          {!isReadOnly ? (
            <Checkbox
              checked={isSelected}
              onCheckedChange={() => onToggleSelection(task.id)}
              aria-label={task.title}
              className="shrink-0"
            />
          ) : null}
        </div>

        <TaskBoardRowContent
          task={task}
          today={today}
          onOpen={() => onOpenTask(task.id)}
          subtasksCount={children.length}
          isExpanded={isExpanded}
          onToggleExpand={() => setIsExpanded((current) => !current)}
        />
      </div>

      {isExpanded && children.length > 0 ? (
        <ul className="flex flex-col divide-y divide-border/40 border-t border-border/40">
          {children.map((child) => (
            <TaskBoardSubtaskRow key={child.task.id} node={child} today={today} onOpenTask={onOpenTask} depth={1} />
          ))}
        </ul>
      ) : null}
    </li>
  )
}
