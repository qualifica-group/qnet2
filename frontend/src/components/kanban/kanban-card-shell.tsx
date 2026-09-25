/**
 * Generic Kanban card chrome (spec 0157 D-6): the `<li>` wrapper, its
 * drag-transform styling, the header row (drag handle / selection / title)
 * and the badges/footer slots — shared by the commessa Task board's own card
 * (`task-board-kanban-card.tsx`) and the `/tasks` Kanban's
 * (`task-kanban-card.tsx`).
 *
 * Deliberately dumb: no dnd-kit dependency. Each caller owns its OWN drag
 * hook (`useSortable` for the board's in-column reorder, plain `useDraggable`
 * for `/tasks`, which has no position of its own) and hands down the
 * resulting ref/style/drag-handle — the shell only lays them out.
 */
import type { CSSProperties, MouseEvent, ReactNode } from 'react'
import { cn } from '@/lib/utils'

export interface KanbanCardShellProps {
  cardRef: (node: HTMLLIElement | null) => void
  style?: CSSProperties
  isDragging?: boolean
  /** Whole-card click (e.g. `openTaskOnCardClick`); omitted, the card has no click target of its own beyond the title button. */
  onCardClick?: (event: MouseEvent<HTMLLIElement>) => void
  /** The drag handle button, already wired to its own hook's `attributes`/`listeners`; `null` when the row is not draggable. */
  dragHandle?: ReactNode
  /** A leading selection control (e.g. a bulk-select checkbox); omitted for a board with no selection. */
  selection?: ReactNode
  title: string
  onTitleClick: () => void
  /** A short excerpt shown under the header row; omitted/`null`, no paragraph renders. */
  description?: string | null
  badges: ReactNode
  footer: ReactNode
}

export function KanbanCardShell({
  cardRef,
  style,
  isDragging,
  onCardClick,
  dragHandle,
  selection,
  title,
  onTitleClick,
  description,
  badges,
  footer,
}: KanbanCardShellProps) {
  return (
    <li
      ref={cardRef}
      style={style}
      onClick={onCardClick}
      className={cn(
        'flex flex-col gap-2 rounded-lg bg-card p-3 shadow-sm ring-1 ring-border/70 transition-shadow hover:shadow-md',
        onCardClick && 'cursor-pointer',
        isDragging && 'z-10 rotate-2 opacity-90 shadow-md',
      )}
    >
      <div className="flex items-start gap-1.5">
        {dragHandle}
        {selection}
        <button
          type="button"
          onClick={onTitleClick}
          className="min-w-0 flex-1 truncate text-left text-sm font-semibold text-foreground hover:text-primary hover:underline"
        >
          {title}
        </button>
      </div>

      {description ? (
        <p className="line-clamp-2 text-xs text-muted-foreground/80" title={description}>
          {description}
        </p>
      ) : null}

      <div className="flex flex-wrap items-center gap-1.5">{badges}</div>

      <div className="flex flex-col gap-1.5 border-t border-border/60 pt-2 text-xs">{footer}</div>
    </li>
  )
}
