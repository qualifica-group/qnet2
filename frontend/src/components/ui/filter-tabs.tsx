import { TABS_LIST_CLASS, TABS_TRIGGER_CLASS } from '@/components/ui/tabs'
import { cn } from '@/lib/utils'

export interface FilterTabOption<T extends string> {
  value: T
  label: string
  /** Shown as a small pill after the label when positive. */
  count?: number
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
 * selection. Same look as `TabsTrigger`: each button sets `data-state` the way
 * a Radix trigger would, so the shared classes apply unchanged.
 */
export function FilterTabs<T extends string>({
  value,
  options,
  onValueChange,
  'aria-label': ariaLabel,
  className,
}: FilterTabsProps<T>) {
  return (
    <div role="tablist" aria-label={ariaLabel} className={cn(TABS_LIST_CLASS, className)}>
      {options.map((option) => {
        const selected = value === option.value
        return (
          <button
            key={option.value}
            type="button"
            role="tab"
            aria-selected={selected}
            data-state={selected ? 'active' : 'inactive'}
            onClick={() => onValueChange(option.value)}
            className={TABS_TRIGGER_CLASS}
          >
            {option.label}
            {option.count !== undefined && option.count > 0 ? (
              <span className="ml-1.5 rounded-full bg-destructive px-1.5 text-[0.65rem] leading-4 font-semibold text-white">
                {option.count}
              </span>
            ) : null}
          </button>
        )
      })}
    </div>
  )
}
