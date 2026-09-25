/**
 * One Kanban column (spec 0157 D-2/D-4, spec 0164 D-1): a status or a
 * due-date bucket, rendered the same way regardless of which — the caller
 * only hands over a `TaskKanbanGroup`. Chrome is the shared
 * `KanbanColumnShell` (spec 0157 D-6, also used by the commessa board's own
 * column); this component owns what is specific to the `/tasks` Kanban: a
 * plain `useDroppable` target (no `SortableContext` — a card has no position
 * of its own here), the accent color resolved from the group's own stored
 * token, and — since spec 0164 — its OWN paginated row query
 * (`useTaskKanbanColumnRows`), loading the next block once a bottom sentinel
 * scrolls into view (mirrors `features/projects/project-card-grid.tsx`'s own
 * infinite-scroll pattern).
 */
import { useEffect, useMemo, useRef } from 'react'
import { useDroppable } from '@dnd-kit/core'
import { Plus } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { KanbanColumnShell } from '@/components/kanban/kanban-column-shell'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { cn } from '@/lib/utils'
import { TaskKanbanCard } from '@/features/tasks/task-kanban/task-kanban-card'
import { taskKanbanColumnDroppableId } from '@/features/tasks/task-kanban/use-task-kanban-dnd'
import { useTaskKanbanColumnRows } from '@/features/tasks/task-kanban/use-task-kanban-column-rows'
import { asTaskKanbanRow } from '@/features/tasks/task-kanban/task-kanban-types'
import type { UseTaskKanbanFiltersResult } from '@/features/tasks/task-kanban/use-task-kanban-filters'
import type { TaskKanbanGroup } from '@/features/tasks/task-kanban/task-kanban-types'

/** Skeleton rows shown while THIS column's own first block is loading. */
const INITIAL_SKELETON_COUNT = 3

interface TaskKanbanColumnProps<Key extends string> {
  group: TaskKanbanGroup<Key>
  /** The board's shared filter slice (search/advancedFilters/filterModel/sortModel) — every column reads it, none of them owns it. */
  filters: UseTaskKanbanFiltersResult
  today: string
  onOpenTask: (id: number) => void
  /** Omitted ⇒ no "+" affordance at all (e.g. the actor lacks `tasks.create`, or this column never accepts a manual create). */
  onAddTask?: () => void
}

export function TaskKanbanColumn<Key extends string>({
  group,
  filters,
  today,
  onOpenTask,
  onAddTask,
}: TaskKanbanColumnProps<Key>) {
  const { t } = useTranslation()
  const { setNodeRef, isOver } = useDroppable({
    id: taskKanbanColumnDroppableId(group.key),
    disabled: !group.droppable,
  })
  const sentinelRef = useRef<HTMLLIElement | null>(null)

  const { data, isPending, isError, refetch, hasNextPage, isFetchingNextPage, fetchNextPage } =
    useTaskKanbanColumnRows({
      kanbanGroup: group.kanbanGroup,
      search: filters.trimmedSearch,
      advancedFilters: filters.advancedFilters.activeValues,
      filterModel: filters.filterModel,
      sortModel: filters.sortModel,
      enabled: filters.isReady,
    })

  const rows = useMemo(
    () => (data?.pages ?? []).flatMap((page) => page.items.map(asTaskKanbanRow)),
    [data?.pages],
  )
  const total = data?.pages[0]?.pagination.total ?? 0

  // Loads this column's next block once its bottom sentinel scrolls into view.
  useEffect(() => {
    const sentinel = sentinelRef.current
    if (!sentinel || !hasNextPage) {
      return
    }
    const observer = new IntersectionObserver((entries) => {
      if (entries[0]?.isIntersecting && hasNextPage && !isFetchingNextPage) {
        void fetchNextPage()
      }
    })
    observer.observe(sentinel)
    return () => observer.disconnect()
  }, [hasNextPage, isFetchingNextPage, fetchNextPage])

  return (
    <KanbanColumnShell
      accentColor={swatchClassFor(group.color) ?? null}
      label={group.label}
      count={total}
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
        {isError ? (
          <li className="flex flex-col items-start gap-2 p-2">
            <p className="text-xs text-destructive" role="alert">
              {t('tasks.views.loadError')}
            </p>
            <Button variant="outline" size="xs" onClick={() => void refetch()}>
              {t('common.retry')}
            </Button>
          </li>
        ) : null}

        {isPending
          ? Array.from({ length: INITIAL_SKELETON_COUNT }).map((_, index) => (
              <li key={index}>
                <Skeleton className="h-20 w-full" />
              </li>
            ))
          : null}

        {rows.map((row) => (
          <TaskKanbanCard
            key={row.id}
            row={row}
            groupKey={String(group.key)}
            today={today}
            draggable={group.draggable}
            onOpen={onOpenTask}
          />
        ))}

        {isFetchingNextPage ? (
          <li>
            <Skeleton className="h-20 w-full" />
          </li>
        ) : null}

        {hasNextPage ? <li ref={sentinelRef} aria-hidden="true" /> : null}
      </ul>
    </KanbanColumnShell>
  )
}
