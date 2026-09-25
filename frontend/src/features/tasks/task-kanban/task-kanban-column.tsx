/**
 * One Kanban column (spec 0157 D-2/D-4): a status or a due-date bucket,
 * rendered the same way regardless of which — the caller only hands over a
 * `TaskKanbanGroup`. Chrome is the shared `KanbanColumnShell` (spec 0157
 * D-6, also used by the commessa board's own column); this component only
 * owns what is specific to the `/tasks` Kanban: a plain `useDroppable`
 * target (no `SortableContext` — a card has no position of its own here)
 * and the accent color resolved from the group's own stored token.
 */
import { useDroppable } from '@dnd-kit/core'
import { Plus } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { KanbanColumnShell } from '@/components/kanban/kanban-column-shell'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { cn } from '@/lib/utils'
import { TaskKanbanCard } from '@/features/tasks/task-kanban/task-kanban-card'
import { taskKanbanColumnDroppableId } from '@/features/tasks/task-kanban/use-task-kanban-dnd'
import type { TaskKanbanGroup } from '@/features/tasks/task-kanban/task-kanban-types'

interface TaskKanbanColumnProps<Key extends string> {
  group: TaskKanbanGroup<Key>
  today: string
  onOpenTask: (id: number) => void
  /** Omitted ⇒ no "+" affordance at all (e.g. the actor lacks `tasks.create`, or this column never accepts a manual create). */
  onAddTask?: () => void
}

export function TaskKanbanColumn<Key extends string>({
  group,
  today,
  onOpenTask,
  onAddTask,
}: TaskKanbanColumnProps<Key>) {
  const { t } = useTranslation()
  const { setNodeRef, isOver } = useDroppable({
    id: taskKanbanColumnDroppableId(group.key),
    disabled: !group.droppable,
  })

  return (
    <KanbanColumnShell
      accentColor={swatchClassFor(group.color) ?? null}
      label={group.label}
      count={group.rows.length}
      addSlot={
        onAddTask ? (
          <Button size="xs" variant="ghost" onClick={onAddTask}>
            <Plus aria-hidden="true" />
            {t('tasks.views.kanbanColumns.addTask')}
          </Button>
        ) : null
      }
    >
      <ul
        ref={setNodeRef}
        className={cn(
          'flex min-h-24 flex-1 flex-col gap-2 overflow-y-auto rounded-xl bg-muted/40 p-2 transition-colors',
          isOver && group.droppable && 'bg-primary/5 ring-2 ring-primary/40',
        )}
      >
        {group.rows.map((row) => (
          <TaskKanbanCard key={row.id} row={row} today={today} draggable={group.draggable} onOpen={onOpenTask} />
        ))}
      </ul>
    </KanbanColumnShell>
  )
}
