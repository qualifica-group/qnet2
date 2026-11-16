/**
 * A non-root row: never draggable and never selectable (D-3, only roots carry
 * a fase/position), so its leading slot stays empty and its cells line up
 * with the root rows'; only the title is indented by depth (spec 0401).
 * Recurses for its own children, one level deeper each time.
 */

import { useState } from 'react'
import { openTaskOnCardClick } from '@/features/work-orders/task-board/task-board-card-click'
import { TaskBoardRowContent } from '@/features/work-orders/task-board/task-board-row-content'
import { TASK_BOARD_LEADING_CLASS, TASK_BOARD_ROW_CLASS } from '@/features/work-orders/task-board/task-board-row-columns'
import type { BoardTaskNode } from '@/features/work-orders/task-board/task-board-filters'

interface TaskBoardSubtaskRowProps {
  node: BoardTaskNode
  today: string
  onOpenTask: (taskId: number) => void
  depth: number
}

export function TaskBoardSubtaskRow({ node, today, onOpenTask, depth }: TaskBoardSubtaskRowProps) {
  const [isExpanded, setIsExpanded] = useState(false)
  const { task, children } = node

  return (
    <li className="flex flex-col">
      <div onClick={(event) => openTaskOnCardClick(event, () => onOpenTask(task.id))} className={TASK_BOARD_ROW_CLASS}>
        <span className={TASK_BOARD_LEADING_CLASS} aria-hidden="true" />
        <TaskBoardRowContent
          task={task}
          today={today}
          onOpen={() => onOpenTask(task.id)}
          depth={depth}
          subtasksCount={children.length}
          isExpanded={isExpanded}
          onToggleExpand={() => setIsExpanded((current) => !current)}
        />
      </div>

      {isExpanded && children.length > 0 ? (
        <ul className="flex flex-col divide-y divide-border/40 border-t border-border/40">
          {children.map((child) => (
            <TaskBoardSubtaskRow key={child.task.id} node={child} today={today} onOpenTask={onOpenTask} depth={depth + 1} />
          ))}
        </ul>
      ) : null}
    </li>
  )
}
