import { FORM_TAB_LIST_CLASS, FORM_TAB_TRIGGER_CLASS } from '@/components/form-tab-strip'
import { cn } from '@/lib/utils'

/**
 * The look of the Gestione Richieste category strip: a surface trough with the
 * active tab raised as a card chip. The trigger classes key off `data-state`,
 * which each button sets the way a Radix `TabsTrigger` would.
 */
const TAB_LIST_CLASS = cn('flex flex-wrap items-center border border-border/60 bg-surface', FORM_TAB_LIST_CLASS)
const TAB_CLASS = cn(
  'inline-flex shrink-0 items-center font-medium whitespace-nowrap outline-none',
  'focus-visible:ring-[3px] focus-visible:ring-ring/50',
  FORM_TAB_TRIGGER_CLASS,
)

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
    <div role="tablist" aria-label={ariaLabel} className={cn(TAB_LIST_CLASS, className)}>
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
            className={TAB_CLASS}
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
