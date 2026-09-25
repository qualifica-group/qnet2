/**
 * One Kanban card (spec 0157 D-2), shared shape for both boards. Chrome is
 * the shared `KanbanCardShell` (spec 0157 D-6, also used by the commessa
 * board's own card); this component only owns what is specific to `/tasks`:
 * a plain `useDraggable` (no in-column reordering — see
 * `use-task-kanban-dnd.ts`) and the field set the `/tasks` domain projects.
 * Reuses the board's own people/hours building blocks
 * (`TaskBoardPeople`/`TaskBoardHours`, `task-board-task-meta.tsx`) — plain
 * presentational helpers already agnostic of `BoardTask` — instead of
 * forking them.
 */
import { useDraggable } from '@dnd-kit/core'
import { GripVertical } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { CompletionBar } from '@/components/completion-bar'
import { KanbanCardShell } from '@/components/kanban/kanban-card-shell'
import { TaskLookupBadge } from '@/features/tasks/task-lookup-badge'
import { TaskKanbanDueChip } from '@/features/tasks/task-kanban/task-kanban-due-chip'
import { TaskBoardHours, TaskBoardPeople } from '@/features/work-orders/task-board/task-board-task-meta'
import type { TaskKanbanRow } from '@/features/tasks/task-kanban/task-kanban-types'

interface TaskKanbanCardProps {
  row: TaskKanbanRow
  today: string
  draggable: boolean
  onOpen: (id: number) => void
}

export function TaskKanbanCard({ row, today, draggable, onOpen }: TaskKanbanCardProps) {
  const { t } = useTranslation()
  const canDrag = draggable && row.editable !== false
  const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, isDragging } = useDraggable({
    id: String(row.id),
    disabled: !canDrag,
  })

  return (
    <KanbanCardShell
      cardRef={setNodeRef}
      style={transform ? { transform: `translate3d(${transform.x}px, ${transform.y}px, 0)` } : undefined}
      isDragging={isDragging}
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
      title={row.title}
      onTitleClick={() => onOpen(Number(row.id))}
      badges={
        <>
          <TaskLookupBadge value={row.task_status} />
          <TaskLookupBadge value={row.task_priority} />
          <TaskKanbanDueChip row={row} today={today} />
        </>
      }
      footer={
        <>
          <CompletionBar
            value={row.completion_percentage}
            label={t('tasks.views.kanbanColumns.completion')}
            barClassName="w-14"
            className="gap-1.5"
          />
          <div className="flex items-center gap-1.5">
            <TaskBoardHours
              actualMinutes={row.actual_minutes}
              estimatedMinutes={row.estimated_minutes}
              label={t('tasks.views.kanbanColumns.hours')}
            />
          </div>
          <div className="flex min-w-0 items-center gap-1.5">
            <TaskBoardPeople people={row.assignees} />
          </div>
        </>
      }
    />
  )
}
