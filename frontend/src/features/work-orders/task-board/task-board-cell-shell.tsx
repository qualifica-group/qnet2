/**
 * One cell of a task row (spec 0401 D-6): read-only, it only shows its value;
 * editable, the value is a button that opens the editor in a popover, like a
 * single click on a Task grid cell. Enter (or a pick) confirms, a click
 * outside keeps the edit, Escape drops it.
 */

import { useRef, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Input } from '@/components/ui/input'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { cn } from '@/lib/utils'

const CELL_CLASS = 'flex min-h-7 w-full min-w-0 items-center gap-1.5 rounded-md px-1.5 py-0.5 text-left text-xs'

const EDITABLE_CELL_CLASS =
  'cursor-pointer outline-none transition-colors hover:bg-muted/40 hover:ring-1 hover:ring-border focus-visible:ring-[3px] focus-visible:ring-ring/50 data-[state=open]:ring-1 data-[state=open]:ring-primary/50'

interface TaskBoardCellShellProps {
  /** The column label: names the edit button for assistive tech. */
  label: string
  isEditable: boolean
  display: ReactNode
  open: boolean
  onOpenChange: (open: boolean) => void
  /** The user closed the editor on their own: `true` on a click outside (keep the edit), `false` on Escape. */
  onDismiss?: (keepEdit: boolean) => void
  children: ReactNode
  contentClassName?: string
  /** Replaces the full-cell trigger look (e.g. the title's pencil button). */
  triggerClassName?: string
}

export function TaskBoardCellShell({
  label,
  isEditable,
  display,
  open,
  onOpenChange,
  onDismiss,
  children,
  contentClassName,
  triggerClassName,
}: TaskBoardCellShellProps) {
  const { t } = useTranslation()
  const isEscapingRef = useRef(false)

  if (!isEditable) {
    return <div className={CELL_CLASS}>{display}</div>
  }

  const handleOpenChange = (next: boolean) => {
    if (!next) {
      onDismiss?.(!isEscapingRef.current)
    }
    isEscapingRef.current = false
    onOpenChange(next)
  }

  return (
    <Popover open={open} onOpenChange={handleOpenChange}>
      <PopoverTrigger asChild>
        <button type="button" className={cn(CELL_CLASS, EDITABLE_CELL_CLASS, triggerClassName)}>
          <span className="sr-only">{t('workOrders.taskBoard.cell.edit', { field: label })}</span>
          {display}
        </button>
      </PopoverTrigger>
      <PopoverContent
        align="start"
        sideOffset={4}
        className={cn('w-auto p-1.5', contentClassName)}
        onEscapeKeyDown={() => {
          isEscapingRef.current = true
        }}
      >
        {children}
      </PopoverContent>
    </Popover>
  )
}

interface TaskBoardInputCellProps {
  label: string
  isEditable: boolean
  display: ReactNode
  type: 'text' | 'date' | 'number'
  /** The persisted value as the input holds it (`''` when empty). */
  value: string
  /** Called only when the confirmed draft differs from `value`. */
  onCommit: (draft: string) => void
  contentClassName?: string
  triggerClassName?: string
}

/** A text/date/number cell: the popover holds one input on a local draft. */
export function TaskBoardInputCell({
  label,
  isEditable,
  display,
  type,
  value,
  onCommit,
  contentClassName,
  triggerClassName,
}: TaskBoardInputCellProps) {
  const [open, setOpen] = useState(false)
  const [draft, setDraft] = useState(value)

  const handleOpenChange = (next: boolean) => {
    if (next) {
      setDraft(value)
    }
    setOpen(next)
  }

  const commit = () => {
    if (draft !== value) {
      onCommit(draft)
    }
  }

  return (
    <TaskBoardCellShell
      label={label}
      isEditable={isEditable}
      display={display}
      open={open}
      onOpenChange={handleOpenChange}
      onDismiss={(keepEdit) => {
        if (keepEdit) {
          commit()
        }
      }}
      contentClassName={contentClassName}
      triggerClassName={triggerClassName}
    >
      <Input
        autoFocus
        type={type}
        min={type === 'number' ? 0 : undefined}
        step={type === 'number' ? 1 : undefined}
        inputMode={type === 'number' ? 'numeric' : undefined}
        value={draft}
        aria-label={label}
        className="h-7 text-xs"
        onChange={(event: React.ChangeEvent<HTMLInputElement>) => setDraft(event.target.value)}
        onKeyDown={(event: React.KeyboardEvent<HTMLInputElement>) => {
          if (event.key === 'Enter') {
            event.preventDefault()
            commit()
            setOpen(false)
          }
        }}
      />
    </TaskBoardCellShell>
  )
}
