import type { ComponentPropsWithRef } from 'react'
import { Checkbox } from '@/components/ui/checkbox'
import { cn } from '@/lib/utils'

/** Raised card (rung 3) so the list reads as one unit on either host surface. */
const GROUP_CLASS = 'rounded-lg border bg-card py-1.5 shadow-xs'
const LEGEND_CLASS = 'ml-2.5 px-1 text-xs font-medium'
const HEADER_CLASS = 'flex items-center justify-between gap-2 border-b border-border/60 px-2.5 pb-2'
const LIST_CLASS = 'grid max-h-52 gap-0.5 overflow-y-auto p-1.5'
const ROW_CLASS = 'flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-xs transition-colors'
const ROW_SELECTED_CLASS = 'bg-primary/10 text-foreground'
const ROW_IDLE_CLASS = 'text-muted-foreground hover:bg-muted/50 hover:text-foreground'

/** One selectable entry: an opaque key plus the domain label to render as-is. */
export interface RequestReportKeyOption {
  key: string
  label: string
}

/** Adds/removes one key from the current selection. */
function toggleKey(current: string[], key: string, checked: boolean): string[] {
  return checked ? [...current, key] : current.filter((value) => value !== key)
}

/** Tri-state of the "select all" control: checked / indeterminate / unchecked. */
function selectAllState(selected: string[], all: string[]): boolean | 'indeterminate' {
  if (all.length === 0 || selected.length === 0) return false
  return selected.length === all.length ? true : 'indeterminate'
}

/**
 * Extends the native fieldset props so the ARIA wiring `FormControl` injects
 * (`id`, `aria-invalid`, `aria-describedby`) reaches the `<fieldset>` itself:
 * `FormControl` renders a Radix `Slot`, which hands those to its child, and a
 * component that swallowed them would silently drop the error announcement
 * the group markup used to carry inline.
 */
export interface RequestReportKeyGroupProps extends Omit<ComponentPropsWithRef<'fieldset'>, 'onChange'> {
  /** Fieldset legend (already translated). */
  label: string
  selectAllLabel: string
  options: RequestReportKeyOption[]
  value: string[]
  onChange: (next: string[]) => void
  disabled: boolean
}

/**
 * The report's checkbox group (spec 0108, D-10): card, tri-state "select
 * all", `selected/total` counter, scrollable list. Extracted from
 * `RequestReportFilters` at unchanged behaviour once a SECOND group — the GA2
 * operators — needed the identical affordances; the two are the same control
 * over two different `{ key, label }` lists, so they are one component.
 *
 * Presentational and domain-agnostic: it knows nothing of branches or
 * operators, and it renders labels verbatim (they are domain values — a
 * category name, a person's name, the report's own "Non assegnato" — and
 * never go through i18next).
 *
 * Stays a feature component rather than a `components/ui/` atom: it is
 * specific to this report and no other screen composes it (ui-design.md §6.2
 * asks for extraction at 2+ SCREENS; here both call sites are the same one).
 */
export function RequestReportKeyGroup({
  label,
  selectAllLabel,
  options,
  value,
  onChange,
  disabled,
  ...fieldsetProps
}: RequestReportKeyGroupProps) {
  const allKeys = options.map((option) => option.key)
  const allState = selectAllState(value, allKeys)

  return (
    <fieldset disabled={disabled} className={GROUP_CLASS} {...fieldsetProps}>
      <legend className={LEGEND_CLASS}>
        {label}
        <span aria-hidden="true" className="ml-1 text-destructive">
          *
        </span>
      </legend>
      <div className={HEADER_CLASS}>
        <label className="flex cursor-pointer items-center gap-2 text-xs font-medium">
          <Checkbox checked={allState} onCheckedChange={() => onChange(allState === true ? [] : allKeys)} />
          {selectAllLabel}
        </label>
        <span className="text-[11px] tabular-nums text-muted-foreground">
          {value.length}/{allKeys.length}
        </span>
      </div>
      <div className={LIST_CLASS}>
        {options.map((option) => {
          const isSelected = value.includes(option.key)

          return (
            <label
              key={option.key}
              className={cn(ROW_CLASS, isSelected ? ROW_SELECTED_CLASS : ROW_IDLE_CLASS)}
            >
              <Checkbox
                checked={isSelected}
                onCheckedChange={(checked) => onChange(toggleKey(value, option.key, checked === true))}
              />
              <span className="truncate">{option.label}</span>
            </label>
          )
        })}
      </div>
    </fieldset>
  )
}
