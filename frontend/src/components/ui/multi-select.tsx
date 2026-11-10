import { ChevronsUpDown } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { SELECT_POPUP_FIT_CONTENT_CLASS } from '@/components/ui/select-popup-width'
import { cn } from '@/lib/utils'

export interface MultiSelectOption {
  value: string
  label: string
}

interface MultiSelectProps {
  /** Selectable options. */
  options: MultiSelectOption[]
  /** Currently selected values (controlled). */
  value: string[]
  /** Called with the next selection whenever an option is toggled. */
  onChange: (value: string[]) => void
  /** Shown in the trigger when nothing is selected. */
  placeholder?: string
  /** Disables the whole control. */
  disabled?: boolean
  /** Accessible label for the trigger button. */
  'aria-label'?: string
  className?: string
}

/**
 * Generic, domain-agnostic multi-select built on the existing Radix dropdown
 * menu (no new dependency). The trigger shows the selected options as badges (or
 * a placeholder), and the menu lists every option as a checkbox item; selecting
 * keeps the menu open so several values can be toggled at once. Reusable wherever
 * a “pick many from a list” control is needed.
 */
export function MultiSelect({
  options,
  value,
  onChange,
  placeholder,
  disabled,
  'aria-label': ariaLabel,
  className,
}: MultiSelectProps) {
  const selected = new Set(value)

  const toggle = (option: string) => {
    const next = new Set(selected)
    if (next.has(option)) {
      next.delete(option)
    } else {
      next.add(option)
    }
    // Preserve the options' declared order in the emitted value.
    onChange(options.map((o) => o.value).filter((v) => next.has(v)))
  }

  const selectedOptions = options.filter((option) => selected.has(option.value))

  return (
    <DropdownMenu>
      <DropdownMenuTrigger
        type="button"
        disabled={disabled}
        aria-label={ariaLabel}
        className={cn(
          'flex min-h-9 w-full items-center justify-between gap-2 rounded-md border border-field-border bg-field px-3 py-1.5 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50',
          className,
        )}
      >
        {selectedOptions.length > 0 ? (
          <span className="flex flex-1 flex-wrap items-center gap-1">
            {selectedOptions.map((option) => (
              <Badge key={option.value} variant="secondary">
                {option.label}
              </Badge>
            ))}
          </span>
        ) : (
          <span className="flex-1 text-left text-muted-foreground">
            {placeholder}
          </span>
        )}
        <ChevronsUpDown className="size-4 shrink-0 opacity-50" aria-hidden="true" />
      </DropdownMenuTrigger>
      <DropdownMenuContent
        align="start"
        className={cn(
          'max-h-64 min-w-(--radix-dropdown-menu-trigger-width) overflow-y-auto',
          SELECT_POPUP_FIT_CONTENT_CLASS,
        )}
      >
        {options.map((option) => (
          <DropdownMenuCheckboxItem
            key={option.value}
            checked={selected.has(option.value)}
            // Keep the menu open so multiple options can be toggled in one go.
            onSelect={(event) => event.preventDefault()}
            onCheckedChange={() => toggle(option.value)}
          >
            {option.label}
          </DropdownMenuCheckboxItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
