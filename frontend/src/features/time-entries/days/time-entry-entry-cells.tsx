/**
 * The two richer cell renderers of the entries table (spec 0122 D-14): the
 * title cell (type-change dropdown + title + notes) and the actions cell
 * (Modifica/Elimina menu). Split out of `time-entry-entries-columns.tsx`
 * to keep both files under the 300-line soft limit (`engineering.md` §6).
 */

import { createElement } from 'react'
import { useTranslation } from 'react-i18next'
import { MoreHorizontal, Pencil, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { badgeColorClass } from '@/features/table/cell-renderers'
import { cn } from '@/lib/utils'
import { resolveTaskTypeIcon } from '@/features/time-entries/days/time-entry-day-view-model'
import type { TaskTypeForSelectItem } from '@/features/task-types/for-select-api'
import type { TimeEntry } from '@/features/time-entries/types'

/**
 * `createElement`, not JSX: `resolveTaskTypeIcon` returns a component
 * resolved from data, and JSX-ing a variable-held component (`<Icon />`)
 * reads as "declared during render" to `react-hooks/static-components`.
 * Mirrors `cell-renderers.tsx`'s `enumIcon()`.
 */
function renderTaskTypeIcon(icon: string | null, className: string) {
  return createElement(resolveTaskTypeIcon(icon), { 'aria-hidden': 'true', className })
}

interface EntryTitleCellProps {
  entry: TimeEntry
  canWrite: boolean
  taskTypeOptions: TaskTypeForSelectItem[]
  onChangeType: (entry: TimeEntry, taskTypeId: number) => void
  onEditEntry: (entryId: number) => void
}

export function EntryTitleCell({ entry, canWrite, taskTypeOptions, onChangeType, onEditEntry }: EntryTitleCellProps) {
  const { t } = useTranslation()
  const canChangeType = canWrite && entry.permissions.update && taskTypeOptions.length > 0
  const canOpenEntry = canWrite && entry.permissions.update

  return (
    <div className="flex h-full min-w-0 items-center gap-2">
      <DropdownMenu>
        <Tooltip>
          <TooltipTrigger asChild>
            <DropdownMenuTrigger asChild>
              <Button
                aria-label={t('timeEntries.table.changeType')}
                className={cn(
                  'inline-flex size-5 shrink-0 items-center justify-center rounded-md border p-0',
                  badgeColorClass(entry.task_type.color),
                )}
                disabled={!canChangeType}
                type="button"
                variant="ghost"
              >
                {renderTaskTypeIcon(entry.task_type.icon, 'size-3')}
              </Button>
            </DropdownMenuTrigger>
          </TooltipTrigger>
          <TooltipContent side="top">{entry.task_type.name}</TooltipContent>
        </Tooltip>
        <DropdownMenuContent align="start">
          {taskTypeOptions.map((option) => (
            <DropdownMenuItem
              className={cn(option.id === entry.task_type.id ? 'bg-muted' : undefined)}
              key={option.id}
              onSelect={() => onChangeType(entry, option.id)}
            >
              {renderTaskTypeIcon(option.meta.icon, 'size-4')}
              <span>{option.label}</span>
            </DropdownMenuItem>
          ))}
        </DropdownMenuContent>
      </DropdownMenu>
      {/* Single line to fit the shared compact row height: the notes trail the title, muted. */}
      <div className="flex min-w-0 items-baseline gap-1.5">
        <button
          className="min-w-0 shrink truncate text-left font-medium enabled:hover:underline disabled:cursor-not-allowed"
          disabled={!canOpenEntry}
          onClick={() => onEditEntry(entry.id)}
          type="button"
        >
          {entry.title}
        </button>
        {entry.notes ? <span className="min-w-0 flex-1 truncate text-muted-foreground">{entry.notes}</span> : null}
      </div>
    </div>
  )
}

interface EntryActionsCellProps {
  entry: TimeEntry
  canWrite: boolean
  actionsLabel: string
  editLabel: string
  deleteLabel: string
  onEditEntry: (entryId: number) => void
  onRequestDelete: (entry: TimeEntry) => void
}

export function EntryActionsCell({
  entry,
  canWrite,
  actionsLabel,
  editLabel,
  deleteLabel,
  onEditEntry,
  onRequestDelete,
}: EntryActionsCellProps) {
  const canEdit = canWrite && entry.permissions.update
  const canDelete = canWrite && entry.permissions.delete

  if (!canEdit && !canDelete) {
    return null
  }

  return (
    <div className="flex h-full items-center justify-center">
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button aria-label={actionsLabel} size="icon-xs" type="button" variant="ghost">
            <MoreHorizontal aria-hidden="true" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          {canEdit ? (
            <DropdownMenuItem onSelect={() => onEditEntry(entry.id)}>
              <Pencil aria-hidden="true" className="size-4" />
              {editLabel}
            </DropdownMenuItem>
          ) : null}
          {canDelete ? (
            <DropdownMenuItem onSelect={() => onRequestDelete(entry)} variant="destructive">
              <Trash2 aria-hidden="true" className="size-4" />
              {deleteLabel}
            </DropdownMenuItem>
          ) : null}
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  )
}
