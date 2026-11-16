/**
 * The relation cells of a task row (spec 0401 D-6): a single pick (status,
 * type, priority, requester) commits at once; a multiple pick (assignees)
 * keeps a draft while the popover is open and commits it on a click outside,
 * the Task grid's multiselect editor behaviour. Escape drops the draft.
 */

import { useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import type { ForSelectItem } from '@/features/for-select/types'
import { TaskBoardCellShell } from '@/features/work-orders/task-board/task-board-cell-shell'
import { TaskBoardOptionList } from '@/features/work-orders/task-board/task-board-option-list'

interface TaskBoardSelectCellProps {
  label: string
  isEditable: boolean
  display: ReactNode
  resource: string
  value: number | null
  /** Offers "Rimuovi" when the column admits an empty value. */
  isNullable: boolean
  onCommit: (id: number | null) => void
  showAvatar?: boolean
  renderItem?: (item: ForSelectItem) => ReactNode
}

export function TaskBoardSelectCell({
  label,
  isEditable,
  display,
  resource,
  value,
  isNullable,
  onCommit,
  showAvatar,
  renderItem,
}: TaskBoardSelectCellProps) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)

  const pick = (id: number | null) => {
    if (id !== value) {
      onCommit(id)
    }
    setOpen(false)
  }

  return (
    <TaskBoardCellShell label={label} isEditable={isEditable} display={display} open={open} onOpenChange={setOpen}>
      <TaskBoardOptionList
        resource={resource}
        label={label}
        selectedIds={value === null ? [] : [value]}
        onSelect={(item) => pick(item.id)}
        showAvatar={showAvatar}
        renderItem={renderItem}
      />
      {isNullable && value !== null ? (
        <button
          type="button"
          onClick={() => pick(null)}
          className="mt-1 w-full rounded-sm px-2 py-1.5 text-left text-xs text-muted-foreground hover:bg-accent hover:text-foreground"
        >
          {t('table.relationEditor.clear')}
        </button>
      ) : null}
    </TaskBoardCellShell>
  )
}

interface TaskBoardMultiSelectCellProps {
  label: string
  isEditable: boolean
  display: ReactNode
  resource: string
  value: number[]
  onCommit: (ids: number[]) => void
}

function hasSameIds(left: number[], right: number[]): boolean {
  return left.length === right.length && left.every((id) => right.includes(id))
}

export function TaskBoardMultiSelectCell({ label, isEditable, display, resource, value, onCommit }: TaskBoardMultiSelectCellProps) {
  const [open, setOpen] = useState(false)
  const [draft, setDraft] = useState(value)

  const handleOpenChange = (next: boolean) => {
    if (next) {
      setDraft(value)
    }
    setOpen(next)
  }

  const toggle = (item: ForSelectItem) =>
    setDraft((current) => (current.includes(item.id) ? current.filter((id) => id !== item.id) : [...current, item.id]))

  return (
    <TaskBoardCellShell
      label={label}
      isEditable={isEditable}
      display={display}
      open={open}
      onOpenChange={handleOpenChange}
      onDismiss={(keepEdit) => {
        if (keepEdit && !hasSameIds(draft, value)) {
          onCommit(draft)
        }
      }}
    >
      <TaskBoardOptionList resource={resource} label={label} selectedIds={draft} onSelect={toggle} isMultiple showAvatar />
    </TaskBoardCellShell>
  )
}
