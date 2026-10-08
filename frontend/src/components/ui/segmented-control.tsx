import type { ComponentProps, KeyboardEvent } from 'react'
import { cn } from '@/lib/utils'

const TRACK_CLASS = 'flex flex-wrap gap-1 rounded-lg border border-field-border bg-muted/40 p-1'
const OPTION_CLASS =
  'min-w-0 flex-1 truncate rounded-md px-2 py-1.5 text-center text-xs font-medium outline-none transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:opacity-50'
const OPTION_SELECTED_CLASS = 'bg-card text-foreground shadow-xs ring-1 ring-border'
const OPTION_IDLE_CLASS = 'text-muted-foreground hover:text-foreground'

/** Arrow keys walk the options the way a native radio group does. */
const NEXT_KEYS = new Set(['ArrowRight', 'ArrowDown'])
const PREVIOUS_KEYS = new Set(['ArrowLeft', 'ArrowUp'])

export interface SegmentedControlOption<T extends string> {
  value: T
  label: string
}

interface SegmentedControlProps<T extends string>
  extends Omit<ComponentProps<'div'>, 'onChange' | 'defaultValue' | 'role'> {
  value: T | null
  options: readonly SegmentedControlOption<T>[]
  onValueChange: (value: T) => void
  disabled?: boolean
}

/**
 * A segmented control (track + raised active segment): one value out of a
 * short, closed list. Semantically a radio group with a roving tab stop, so
 * Tab enters once and the arrows move the selection. Extra props (id,
 * aria-*) land on the group itself, which lets a form's `<FormControl>` wire
 * it like any other input.
 */
export function SegmentedControl<T extends string>({
  value,
  options,
  onValueChange,
  disabled = false,
  className,
  ...groupProps
}: SegmentedControlProps<T>) {
  const tabStop = options.some((option) => option.value === value) ? value : options[0]?.value

  const handleKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    const step = NEXT_KEYS.has(event.key) ? 1 : PREVIOUS_KEYS.has(event.key) ? -1 : 0
    if (step === 0 || disabled) {
      return
    }
    event.preventDefault()
    const current = options.findIndex((option) => option.value === tabStop)
    const next = (current + step + options.length) % options.length
    onValueChange(options[next].value)
    event.currentTarget.querySelectorAll<HTMLButtonElement>('[role="radio"]')[next]?.focus()
  }

  return (
    <div
      role="radiogroup"
      aria-disabled={disabled || undefined}
      className={cn(TRACK_CLASS, className)}
      onKeyDown={handleKeyDown}
      {...groupProps}
    >
      {options.map((option) => {
        const isSelected = option.value === value

        return (
          <button
            key={option.value}
            type="button"
            role="radio"
            aria-checked={isSelected}
            tabIndex={option.value === tabStop ? 0 : -1}
            disabled={disabled}
            className={cn(OPTION_CLASS, isSelected ? OPTION_SELECTED_CLASS : OPTION_IDLE_CLASS)}
            onClick={() => onValueChange(option.value)}
          >
            {option.label}
          </button>
        )
      })}
    </div>
  )
}
