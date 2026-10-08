import { useContext } from 'react'
import { useTranslation } from 'react-i18next'
import type { ICellRendererParams } from 'ag-grid-community'
import { CheckCircle2 } from 'lucide-react'
import { TaskCompleteRowContext } from '@/features/tasks/task-complete-row-context'
import {
  COMPLETABLE_ICON_CLASS,
  COMPLETED_ICON_CLASS,
  COMPLETE_ICON_CLASS,
  COMPLETE_TOGGLE_CLASS,
  INERT_ICON_CLASS,
} from '@/features/tasks/task-complete-icon-styles'
import type { TableRow } from '@/features/table/types'
import { cn } from '@/lib/utils'

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
          className={cn(COMPLETE_ICON_CLASS, COMPLETED_ICON_CLASS)}
        />
      ) : canComplete && data ? (
        <button
          type="button"
          ref={(button) => bindCompleteToggle(button, () => completeRow(data))}
          aria-label={t('tasks.actions.complete.label')}
          title={t('tasks.actions.complete.label')}
          className={COMPLETE_TOGGLE_CLASS}
        >
          <CheckCircle2 aria-hidden="true" className={cn(COMPLETE_ICON_CLASS, COMPLETABLE_ICON_CLASS)} />
        </button>
      ) : (
        <CheckCircle2 aria-hidden="true" className={cn(COMPLETE_ICON_CLASS, INERT_ICON_CLASS)} />
      )}
      <span className="truncate">{title}</span>
    </span>
  )
}
