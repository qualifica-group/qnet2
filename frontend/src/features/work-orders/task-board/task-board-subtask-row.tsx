/**
 * A non-root row: indented under its parent, never draggable and never
 * selectable (D-3, only roots carry a fase/position). Recurses for its own
 * children so a sub-task's sub-task nests one level deeper again.
 */

import { useState } from 'react'
import { ChevronDown, ChevronRight } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { TaskBoardRowContent } from '@/features/work-orders/task-board/task-board-row-content'
import type { BoardTaskNode } from '@/features/work-orders/task-board/task-board-filters'

interface TaskBoardSubtaskRowProps {
  node: BoardTaskNode
  today: string
  onOpenTask: (taskId: number) => void
  depth: number
}

export function TaskBoardSubtaskRow({ node, today, onOpenTask, depth }: TaskBoardSubtaskRowProps) {
  const { t } = useTranslation()
  const [isExpanded, setIsExpanded] = useState(false)
  const { task, children } = node

  return (
    <li className="flex flex-col" style={{ marginLeft: `${Math.min(depth - 1, 3) * 1.25}rem` }}>
      <div className="flex items-start gap-2 rounded-md py-2 pr-2 transition-colors hover:bg-muted/30">
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
        <ul className="flex flex-col gap-1.5">
          {children.map((child) => (
            <TaskBoardSubtaskRow key={child.task.id} node={child} today={today} onOpenTask={onOpenTask} depth={depth + 1} />
          ))}
        </ul>
      ) : null}
    </li>
  )
}
