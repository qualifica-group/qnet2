/**
 * Generic multi/single toggle-chip group (spec 0122 D-13): shared by the
 * "Tipo" (task types, icon+color), "Stato target" (`DAILY_STATUS_META`) and
 * "Attività" (boolean) drawer filters — the one real repetition (3 call
 * sites) that justifies extracting it (engineering.md §3 DRY).
 */

import type { LucideIcon } from 'lucide-react'
import { cn } from '@/lib/utils'

export interface TimeEntryToggleOption {
  value: string
  label: string
  icon?: LucideIcon
  /** Badge-style classes applied when the option is selected (`badgeColorClass`). */
  colorClassName?: string
}

interface TimeEntryToggleFilterProps {
  options: TimeEntryToggleOption[]
  value: string[]
  onChange: (next: string[]) => void
  /** `true` (default): toggles membership. `false`: single choice, re-clicking clears it. */
  multiple?: boolean
  disabled?: boolean
  'aria-label'?: string
}

export function TimeEntryToggleFilter({
  options,
  value,
  onChange,
  multiple = true,
  disabled = false,
  'aria-label': ariaLabel,
}: TimeEntryToggleFilterProps) {
  const selected = new Set(value)

  const toggle = (optionValue: string) => {
    if (disabled) {
      return
    }
    if (multiple) {
      onChange(
        selected.has(optionValue) ? value.filter((entry) => entry !== optionValue) : [...value, optionValue],
      )
      return
    }
    onChange(selected.has(optionValue) ? [] : [optionValue])
  }

  return (
    <div role="group" aria-label={ariaLabel} className="flex flex-wrap gap-1.5">
      {options.map((option) => {
        const isSelected = selected.has(option.value)
        const Icon = option.icon
        return (
          <button
            key={option.value}
            type="button"
            aria-pressed={isSelected}
            disabled={disabled}
            onClick={() => toggle(option.value)}
            className={cn(
              'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-50',
              isSelected
                ? cn('border-transparent', option.colorClassName ?? 'bg-primary text-primary-foreground')
                : 'border-border bg-card text-muted-foreground hover:bg-muted hover:text-foreground',
            )}
          >
            {Icon ? <Icon className="size-3.5" aria-hidden="true" /> : null}
            {option.label}
          </button>
        )
      })}
    </div>
  )
}
