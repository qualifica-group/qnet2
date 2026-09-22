/**
 * A labelled segmented control (track + raised active segment), the same
 * look as Gestione Richieste's row-mode picker. Semantically a radio group:
 * one value out of a short, closed list.
 */

import { useId } from 'react'
import { cn } from '@/lib/utils'

const TRACK_CLASS = 'flex flex-wrap gap-1 rounded-lg border border-field-border bg-muted/40 p-1'
const OPTION_CLASS =
  'min-w-0 flex-1 truncate rounded-md px-2 py-1.5 text-center text-xs font-medium outline-none transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50'
const OPTION_SELECTED_CLASS = 'bg-card text-foreground shadow-xs ring-1 ring-border'
const OPTION_IDLE_CLASS = 'text-muted-foreground hover:text-foreground'

export interface TaskBoardSegmentedFieldProps<T extends string> {
  label: string
  value: T
  options: { value: T; label: string }[]
  onChange: (value: T) => void
}

export function TaskBoardSegmentedField<T extends string>({ label, value, options, onChange }: TaskBoardSegmentedFieldProps<T>) {
  const labelId = useId()

  return (
    <div className="flex flex-col gap-1.5">
      <span id={labelId} className="text-xs font-medium">
        {label}
      </span>
      <div role="radiogroup" aria-labelledby={labelId} className={TRACK_CLASS}>
        {options.map((option) => {
          const isSelected = option.value === value

          return (
            <button
              key={option.value}
              type="button"
              role="radio"
              aria-checked={isSelected}
              className={cn(OPTION_CLASS, isSelected ? OPTION_SELECTED_CLASS : OPTION_IDLE_CLASS)}
              onClick={() => onChange(option.value)}
            >
              {option.label}
            </button>
          )
        })}
      </div>
    </div>
  )
}
