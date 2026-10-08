import { cn } from '@/lib/utils'

const TAB_CLASS =
  'rounded-md px-2.5 py-1 text-xs font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring'
const TAB_SELECTED_CLASS = 'bg-primary text-primary-foreground'
const TAB_IDLE_CLASS = 'bg-card text-foreground hover:bg-muted'

export interface FilterTabOption<T extends string> {
  value: T
  label: string
}

interface FilterTabsProps<T extends string> {
  /** The active tab, or `null` when the grid filters match none of them. */
  value: T | null
  options: readonly FilterTabOption<T>[]
  onValueChange: (value: T) => void
  'aria-label': string
  className?: string
}

/**
 * Compact tab strip above a grid: each tab is a preset of the grid filter
 * model, so the caller owns the mapping and this component only renders the
 * selection.
 */
export function FilterTabs<T extends string>({
  value,
  options,
  onValueChange,
  'aria-label': ariaLabel,
  className,
}: FilterTabsProps<T>) {
  return (
    <div role="tablist" aria-label={ariaLabel} className={cn('flex flex-wrap items-center gap-1', className)}>
      {options.map((option) => {
        const selected = value === option.value
        return (
          <button
            key={option.value}
            type="button"
            role="tab"
            aria-selected={selected}
            onClick={() => onValueChange(option.value)}
            className={cn(TAB_CLASS, selected ? TAB_SELECTED_CLASS : TAB_IDLE_CLASS)}
          >
            {option.label}
          </button>
        )
      })}
    </div>
  )
}
