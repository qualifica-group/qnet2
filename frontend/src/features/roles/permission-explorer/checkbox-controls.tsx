import { cn } from '@/lib/utils'
import { Checkbox } from '@/components/ui/checkbox'
import type { TriState } from '@/features/roles/permission-explorer/permission-selection'

interface LabeledCheckboxProps {
  checked: TriState
  disabled: boolean
  label: string
  /**
   * Overrides the checkbox's accessible name (spec 0076 AC-023: an ability
   * pill's visible text is just the action, e.g. "Create" — the module is
   * already the panel's own heading — but its accessible name must still
   * identify module + action, e.g. "Leads — Create"). Defaults to `label`.
   */
  ariaLabel?: string
  className?: string
  onChange: (checked: boolean) => void
}

/**
 * A `Checkbox` plus its VISIBLE text label, sharing one clickable target
 * (e.g. an ability pill, the global select-all). Moved verbatim from
 * `role-form-body.tsx`.
 */
export function LabeledCheckbox({ checked, disabled, label, ariaLabel, className, onChange }: LabeledCheckboxProps) {
  return (
    <label className={cn('flex items-center gap-1.5', className)}>
      <Checkbox
        checked={checked}
        disabled={disabled}
        aria-label={ariaLabel ?? label}
        onCheckedChange={(next) => onChange(next === true)}
      />
      {label}
    </label>
  )
}

interface AriaCheckboxProps {
  checked: TriState
  disabled: boolean
  /** Accessible name only — no visible text sibling (the row already shows its own label). */
  label: string
  className?: string
  onChange: (checked: boolean) => void
}

/**
 * A bare `Checkbox` carrying only an `aria-label` (spec 0076 AC-023): used
 * where the row already renders its own visible name next to the control
 * (tree module/area rows, the field-permission matrix's visible/editable/
 * required trio) — a second, redundant visible label would just be noise.
 */
export function AriaCheckbox({ checked, disabled, label, className, onChange }: AriaCheckboxProps) {
  return (
    <Checkbox
      checked={checked}
      disabled={disabled}
      aria-label={label}
      className={className}
      onCheckedChange={(next) => onChange(next === true)}
    />
  )
}
