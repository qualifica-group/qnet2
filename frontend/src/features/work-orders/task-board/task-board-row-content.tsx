/**
 * The cells shared by a root row (`task-board-task-row.tsx`) and a sub-task
 * row (`task-board-subtask-row.tsx`), laid out like a Task grid row (spec
 * 0401): one line, values in the columns of `TASK_BOARD_CELLS_CLASS` under
 * the phase's header. The title carries the complete check (D-3) and opens
 * the task; every other cell the actor may edit opens its editor in place
 * (D-4/D-6). Without a `TaskBoardCellEditContext` (the drag overlay) the row
 * is read-only.
 */

import { useContext, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { ChevronDown, ChevronRight, Lock, Pencil } from 'lucide-react'
import { CompletionBar } from '@/components/completion-bar'
import { Badge } from '@/components/ui/badge'
import { TaskLookupBadge } from '@/features/tasks/task-lookup-badge'
import { taskLookupOptionRenderer } from '@/features/tasks/task-lookup-option-renderer'
import { TaskBoardInputCell } from '@/features/work-orders/task-board/task-board-cell-shell'
import { TaskBoardCompleteToggle } from '@/features/work-orders/task-board/task-board-complete-toggle'
import { TaskBoardEndDate } from '@/features/work-orders/task-board/task-board-end-date'
import { TaskBoardMultiSelectCell, TaskBoardSelectCell } from '@/features/work-orders/task-board/task-board-picker-cells'
import {
  TASK_BOARD_CELLS_CLASS,
  TASK_BOARD_TITLE_CELL_CLASS,
  TaskBoardCellEditContext,
  canEditTaskCell,
  type TaskBoardCellValue,
  type TaskBoardEditableColumn,
} from '@/features/work-orders/task-board/task-board-row-columns'
import { TaskBoardHours, TaskBoardPeople } from '@/features/work-orders/task-board/task-board-task-meta'
import type { BoardTask } from '@/features/work-orders/task-board/types'
import { formatDate } from '@/lib/formatting/date-display'
import { cn } from '@/lib/utils'

/** Indentation step of a sub-task title, capped so a deep tree keeps its title readable. */
const INDENT_REM = 1.25
const INDENT_DEPTH_MAX = 3
const EMPTY_VALUE = '—'
const NON_NEGATIVE_INTEGER = /^\d+$/

const ICON_BUTTON_CLASS =
  'flex shrink-0 items-center justify-center rounded-sm p-0.5 text-muted-foreground outline-none hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50'

interface TaskBoardRowContentProps {
  task: BoardTask
  today: string
  onOpen: () => void
  /** 0 for a root task, 1+ for a sub-task: indents the title cell only, so the other cells stay under their headers. */
  depth?: number
  subtasksCount?: number
  isExpanded?: boolean
  onToggleExpand?: () => void
}

/** A cell with its own label, shown only while the row is stacked (below `lg`, where the header row is hidden). */
function RowCell({ label, className, children }: { label: string; className?: string; children: ReactNode }) {
  return (
    <div className={cn('flex min-w-0 flex-col gap-0.5', className)}>
      <span className="px-1.5 text-[10px] leading-tight font-medium tracking-wide text-muted-foreground uppercase lg:hidden">
        {label}
      </span>
      {children}
    </div>
  )
}

function EmptyValue() {
  return <span className="text-muted-foreground">{EMPTY_VALUE}</span>
}

export function TaskBoardRowContent({
  task,
  today,
  onOpen,
  depth = 0,
  subtasksCount = 0,
  isExpanded = false,
  onToggleExpand,
}: TaskBoardRowContentProps) {
  const { t } = useTranslation()
  const cellEdit = useContext(TaskBoardCellEditContext)
  const column = (key: string) => t(`workOrders.taskBoard.task.columns.${key}`)
  const canEdit = (key: TaskBoardEditableColumn) => cellEdit !== null && canEditTaskCell(task, key)
  const commit = (key: TaskBoardEditableColumn, value: TaskBoardCellValue) => cellEdit?.commit(task, key, value)
  const estimatedLabel = t('tasks.form.estimatedMinutes')

  return (
    <div className={TASK_BOARD_CELLS_CLASS}>
      <div
        className={cn('group/title flex min-w-0 items-center gap-1.5', TASK_BOARD_TITLE_CELL_CLASS)}
        style={{ paddingLeft: `${Math.min(depth, INDENT_DEPTH_MAX) * INDENT_REM}rem` }}
      >
        {subtasksCount > 0 && onToggleExpand ? (
          <button
            type="button"
            onClick={onToggleExpand}
            aria-label={isExpanded ? t('workOrders.taskBoard.stage.collapse') : t('workOrders.taskBoard.stage.expand')}
            className={ICON_BUTTON_CLASS}
          >
            {isExpanded ? <ChevronDown className="size-3.5" aria-hidden="true" /> : <ChevronRight className="size-3.5" aria-hidden="true" />}
          </button>
        ) : (
          <span className="size-4.5 shrink-0" aria-hidden="true" />
        )}
        <TaskBoardCompleteToggle task={task} />
        <button
          type="button"
          onClick={onOpen}
          title={task.description_excerpt ?? task.title}
          className="min-w-0 truncate text-left text-sm font-medium text-foreground hover:text-primary hover:underline"
        >
          {task.title}
        </button>
        {task.is_blocked ? (
          <span className="inline-flex shrink-0 items-center gap-1 rounded-md bg-destructive/10 px-1.5 py-0.5 text-xs font-medium text-destructive ring-1 ring-inset ring-destructive/30">
            <Lock className="size-3" aria-hidden="true" />
            {t('tasks.detail.blocked')}
          </span>
        ) : null}
        {subtasksCount > 0 ? (
          <Badge variant="outline" className="shrink-0">
            {t('workOrders.taskBoard.task.subtasksCount', { count: subtasksCount })}
          </Badge>
        ) : null}
        <TaskBoardTitleEditor task={task} isEditable={canEdit('title')} onCommit={(title) => commit('title', title)} />
      </div>

      <RowCell label={column('status')}>
        <TaskBoardSelectCell
          label={column('status')}
          isEditable={canEdit('task_status')}
          display={<TaskLookupBadge value={task.task_status} />}
          resource="task-statuses"
          value={task.task_status.id}
          isNullable={false}
          onCommit={(id) => commit('task_status', id)}
          renderItem={taskLookupOptionRenderer}
        />
      </RowCell>
      <RowCell label={column('type')}>
        <TaskBoardSelectCell
          label={column('type')}
          isEditable={canEdit('task_type')}
          display={task.task_type ? <TaskLookupBadge value={task.task_type} /> : <EmptyValue />}
          resource="task-types"
          value={task.task_type?.id ?? null}
          isNullable
          onCommit={(id) => commit('task_type', id)}
          renderItem={taskLookupOptionRenderer}
        />
      </RowCell>
      <RowCell label={column('priority')}>
        <TaskBoardSelectCell
          label={column('priority')}
          isEditable={canEdit('task_priority')}
          display={task.task_priority ? <TaskLookupBadge value={task.task_priority} /> : <EmptyValue />}
          resource="task-priorities"
          value={task.task_priority?.id ?? null}
          isNullable
          onCommit={(id) => commit('task_priority', id)}
          renderItem={taskLookupOptionRenderer}
        />
      </RowCell>
      <RowCell label={column('start')}>
        <TaskBoardInputCell
          label={column('start')}
          isEditable={canEdit('start_date')}
          display={task.start_date ? <span className="tabular-nums">{formatDate(task.start_date)}</span> : <EmptyValue />}
          type="date"
          value={task.start_date ?? ''}
          onCommit={(draft) => commit('start_date', draft === '' ? null : draft)}
        />
      </RowCell>
      <RowCell label={column('due')}>
        <TaskBoardInputCell
          label={column('due')}
          isEditable={canEdit('end_date')}
          display={<TaskBoardEndDate task={task} today={today} />}
          type="date"
          value={task.end_date ?? ''}
          // "Data fine" is required on a task (TaskColumnCatalog `nullable: false`): an emptied field is not a change.
          onCommit={(draft) => {
            if (draft !== '') {
              commit('end_date', draft)
            }
          }}
        />
      </RowCell>
      <RowCell label={column('requester')}>
        <TaskBoardSelectCell
          label={column('requester')}
          isEditable={canEdit('requester')}
          display={<TaskBoardPeople people={task.requester ? [task.requester] : []} isStatic={canEdit('requester')} />}
          resource="users"
          value={task.requester?.id ?? null}
          isNullable={false}
          onCommit={(id) => commit('requester', id)}
          showAvatar
        />
      </RowCell>
      <RowCell label={column('assignees')}>
        <TaskBoardMultiSelectCell
          label={column('assignees')}
          isEditable={canEdit('assignees')}
          display={<TaskBoardPeople people={task.assignees} isStatic={canEdit('assignees')} />}
          resource="users"
          value={task.assignees.map((person) => person.id)}
          onCommit={(ids) => commit('assignees', ids)}
        />
      </RowCell>
      <RowCell label={column('completion')}>
        {/* Derived from the status, never edited directly (as the Task grid's own column). */}
        <div className="flex min-h-7 items-center px-1.5">
          <CompletionBar
            value={task.task_status.completion_percentage}
            label={column('completion')}
            barClassName="w-12"
            className="gap-1.5 text-xs"
          />
        </div>
      </RowCell>
      <RowCell label={column('hours')}>
        <TaskBoardInputCell
          label={estimatedLabel}
          isEditable={canEdit('estimated_minutes')}
          display={<TaskBoardHours actualMinutes={task.actual_minutes} estimatedMinutes={task.estimated_minutes} label={column('hours')} />}
          type="number"
          value={task.estimated_minutes === null ? '' : String(task.estimated_minutes)}
          onCommit={(draft) => {
            if (draft === '') {
              commit('estimated_minutes', null)
            } else if (NON_NEGATIVE_INTEGER.test(draft)) {
              commit('estimated_minutes', Number(draft))
            }
          }}
        />
      </RowCell>
    </div>
  )
}

interface TaskBoardTitleEditorProps {
  task: BoardTask
  isEditable: boolean
  onCommit: (title: string) => void
}

/** The title opens the task (as before), so renaming lives on the pencil next to it, shown on hover/focus. */
function TaskBoardTitleEditor({ task, isEditable, onCommit }: TaskBoardTitleEditorProps) {
  const { t } = useTranslation()

  if (!isEditable) {
    return null
  }

  return (
    <TaskBoardInputCell
      label={t('workOrders.taskBoard.task.columns.title')}
      isEditable
      display={<Pencil className="size-3.5" aria-hidden="true" />}
      type="text"
      value={task.title}
      onCommit={(draft) => {
        const title = draft.trim()
        if (title !== '' && title !== task.title) {
          onCommit(title)
        }
      }}
      contentClassName="w-80 max-w-[calc(100vw-2rem)]"
      triggerClassName="w-auto shrink-0 text-muted-foreground lg:opacity-0 lg:group-hover/title:opacity-100 lg:focus-visible:opacity-100 lg:data-[state=open]:opacity-100"
    />
  )
}
