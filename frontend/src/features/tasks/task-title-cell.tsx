import { useContext } from 'react'
import { useTranslation } from 'react-i18next'
import type { ICellRendererParams } from 'ag-grid-community'
import { CheckCircle2 } from 'lucide-react'
import { TaskCompleteRowContext } from '@/features/tasks/task-complete-row-context'
import type { TableRow } from '@/features/table/types'
import { cn } from '@/lib/utils'

const ICON_CLASS = 'size-4 shrink-0'

/** Filled green disc with the check cut out of it (the stroke takes the card color). */
const COMPLETED_ICON_CLASS = 'fill-success text-card'

/**
 * Reads as clickable: hand cursor, a tinted fill and a small pop on hover,
 * a press-in on click (motion only when the user has not asked to reduce it).
 */
const TOGGLE_CLASS = cn(
  'group/complete cursor-pointer rounded-full text-muted-foreground outline-none transition-[color,transform] duration-150',
  'hover:text-success focus-visible:text-success focus-visible:ring-2 focus-visible:ring-ring',
  'motion-safe:hover:scale-125 motion-safe:active:scale-95',
)

/** Keys that activate a focused button: kept from the grid, which would open the title editor on them. */
const ACTIVATION_KEYS = new Set(['Enter', ' '])

/**
 * Wires the toggle with NATIVE listeners: the grid opens the title editor on
 * a single click (spec 0053 D-9) through its own listener on an ancestor,
 * which runs before React's root-delegated `onClick` could stop it.
 */
function bindCompleteToggle(button: HTMLButtonElement | null, onComplete: () => void) {
  if (!button) {
    return
  }
  const handleClick = (event: MouseEvent) => {
    event.stopPropagation()
    onComplete()
  }
  const stop = (event: Event) => event.stopPropagation()
  const handleKeyDown = (event: KeyboardEvent) => {
    if (ACTIVATION_KEYS.has(event.key)) {
      event.stopPropagation()
    }
  }
  button.addEventListener('click', handleClick)
  button.addEventListener('dblclick', stop)
  button.addEventListener('keydown', handleKeyDown)
  return () => {
    button.removeEventListener('click', handleClick)
    button.removeEventListener('dblclick', stop)
    button.removeEventListener('keydown', handleKeyDown)
  }
}

/** A completed task: its status phase is the positive closure (the row carries `task_status.group`, spec 0156 D-2). */
function isCompletedRow(row: TableRow | undefined): boolean {
  const status = row?.task_status as { group?: string } | null | undefined
  return status?.group === 'closed_positive'
}

/**
 * The grid's "Titolo" cell (user directive 2026-10-06): the complete toggle
 * before the title. Completed task: the icon filled in green. Completable row (its
 * `complete` row action): a muted icon that turns green on hover and opens
 * the same complete dialog as the row action. Otherwise a faded icon that
 * only keeps the titles aligned.
 */
export function TaskTitleCell({ value, data }: ICellRendererParams<TableRow>) {
  const { t } = useTranslation()
  const completeRow = useContext(TaskCompleteRowContext)
  const title = typeof value === 'string' ? value : ''
  const isCompleted = isCompletedRow(data)
  const canComplete = !isCompleted && completeRow !== null && data?.actions.includes('complete') === true

  return (
    <span className="flex h-full min-w-0 items-center gap-1.5">
      {isCompleted ? (
        <CheckCircle2
          role="img"
          aria-label={t('tasks.actions.complete.done')}
          className={cn(ICON_CLASS, COMPLETED_ICON_CLASS)}
        />
      ) : canComplete && data ? (
        <button
          type="button"
          ref={(button) => bindCompleteToggle(button, () => completeRow(data))}
          aria-label={t('tasks.actions.complete.label')}
          title={t('tasks.actions.complete.label')}
          className={TOGGLE_CLASS}
        >
          <CheckCircle2 aria-hidden="true" className={cn(ICON_CLASS, 'fill-transparent transition-[fill] group-hover/complete:fill-success/15')} />
        </button>
      ) : (
        <CheckCircle2 aria-hidden="true" className={cn(ICON_CLASS, 'text-muted-foreground/40')} />
      )}
      <span className="truncate">{title}</span>
    </span>
  )
}
