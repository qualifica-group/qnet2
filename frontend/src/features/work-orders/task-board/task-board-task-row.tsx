/**
 * A ROOT row of the Task board's list view (D-3): the only rows that carry a
 * fase/position, so the only ones with a drag handle and a selection
 * checkbox (D-7's bulk actions select roots only). Its own sub-tasks render
 * as indented, non-draggable `TaskBoardSubtaskRow`s underneath, expandable.
 */

import { useState } from 'react'
import { useSortable } from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { ChevronDown, ChevronRight, GripVertical } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { Checkbox } from '@/components/ui/checkbox'
import { cn } from '@/lib/utils'
import { TaskBoardRowContent } from '@/features/work-orders/task-board/task-board-row-content'
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
        className={cn(
          'flex items-start gap-2 px-3 py-3 transition-colors hover:bg-muted/30',
          isSelected && 'bg-primary/5 hover:bg-primary/10',
        )}
      >
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
          <span className="size-3.5 shrink-0" aria-hidden="true" />
        )}

        {!isReadOnly ? (
          <Checkbox
            checked={isSelected}
            onCheckedChange={() => onToggleSelection(task.id)}
            aria-label={task.title}
            className="shrink-0"
          />
        ) : null}

        {children.length > 0 ? (
          <button
            type="button"
            onClick={() => setIsExpanded((current) => !current)}
            aria-label={isExpanded ? t('workOrders.taskBoard.stage.collapse') : t('workOrders.taskBoard.stage.expand')}
            className="flex shrink-0 items-center justify-center rounded-sm p-0.5 text-muted-foreground outline-none hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50"
          >
            {isExpanded ? <ChevronDown className="size-3.5" aria-hidden="true" /> : <ChevronRight className="size-3.5" aria-hidden="true" />}
          </button>
        ) : (
          <span className="size-3.5 shrink-0" aria-hidden="true" />
        )}

        <TaskBoardRowContent task={task} today={today} onOpen={() => onOpenTask(task.id)} />

        {children.length > 0 ? (
          <Badge variant="outline" className="shrink-0">
            {t('workOrders.taskBoard.task.subtasksCount', { count: children.length })}
          </Badge>
        ) : null}
      </div>

      {isExpanded && children.length > 0 ? (
        <ul className="mb-3 ml-12 flex flex-col border-l-2 border-border/70 pl-3">
          {children.map((child) => (
            <TaskBoardSubtaskRow key={child.task.id} node={child} today={today} onOpenTask={onOpenTask} depth={1} />
          ))}
        </ul>
      ) : null}
    </li>
  )
}
