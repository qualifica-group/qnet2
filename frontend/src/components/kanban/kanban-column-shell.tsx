/**
 * Generic Kanban column chrome (spec 0157 D-6): accent dot + label + count
 * header, an optional extra header line (e.g. a KPI summary), and a trailing
 * "+" slot — shared by the commessa Task board
 * (`features/work-orders/task-board/task-board-kanban-column.tsx`) and the
 * `/tasks` Kanban (`features/tasks/task-kanban/task-kanban-column.tsx`).
 *
 * Deliberately dumb: no dnd-kit dependency, no domain data. The two boards'
 * own column components each own their OWN drop target (`useDroppable`
 * plain, or dnd-kit/sortable's `SortableContext` for the board's in-column
 * reorder) and pass the resulting list markup in as `children` — the one
 * real behavioral difference between the two, which a shared shell has no
 * business hiding.
 */
import type { ReactNode } from 'react'
import { cn } from '@/lib/utils'

export interface KanbanColumnShellProps {
  /** A resolved Tailwind class painting the accent dot (e.g. `bg-blue-500`), or `null` for a neutral one. Each caller resolves its OWN color semantics (a stored badge token vs a palette-by-index accent) before handing this down. */
  accentColor: string | null
  label: string
  count: number
  /** Extra content under the header row (e.g. the board's own KPI summary line). Omitted, the header is a single row. */
  headerExtra?: ReactNode
  /** The column's own row list, fully owned by the caller (plain `<ul>`, or a `<SortableContext><ul>…` for a board that supports in-column reorder). */
  children: ReactNode
  /** The "+" affordance, or `null`/omitted when this column offers none. */
  addSlot?: ReactNode
}

const NEUTRAL_ACCENT = 'bg-muted-foreground/40'

export function KanbanColumnShell({
  accentColor,
  label,
  count,
  headerExtra,
  children,
  addSlot,
}: KanbanColumnShellProps) {
  return (
    <div className="flex w-72 shrink-0 flex-col gap-2">
      <div className="flex flex-col gap-1.5 px-1">
        <div className="flex items-center gap-2">
          <span aria-hidden="true" className={cn('size-2.5 shrink-0 rounded-full', accentColor ?? NEUTRAL_ACCENT)} />
          <h3 className="min-w-0 flex-1 truncate text-sm font-semibold text-foreground">{label}</h3>
          <span className="shrink-0 rounded-full bg-muted px-2 py-0.5 text-xs font-medium tabular-nums text-muted-foreground">
            {count}
          </span>
        </div>
        {headerExtra}
      </div>

      {children}

      {addSlot}
    </div>
  )
}
