import type { ReactNode } from 'react'
import { Clock3 } from 'lucide-react'
import { cn } from '@/lib/utils'

/**
 * Chrome for the INSIDE of a `FormSection`, where a long list of controls needs
 * shape of its own.
 *
 * A section that stacks six full-width pickers in one column reads as one
 * undifferentiated run: nothing tells the operator that the first two answer
 * "where does this record come from" and the next three "who are its people".
 * `FieldGroup` gives each half a micro-heading — the SAME one `RecordSection`
 * uses on the read-only side, so a group is recognizable across the two
 * surfaces — and nothing else: no border, no card, no second nesting level.
 */

interface FieldGroupProps {
  label: string
  children: ReactNode
  className?: string
}

export function FieldGroup({ label, children, className }: FieldGroupProps) {
  return (
    <div className={cn('flex min-w-0 flex-col gap-3', className)}>
      <span className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
        {label}
      </span>
      {children}
    </div>
  )
}

interface PlannedFieldProps {
  label: string
  /** Short "not yet" wording, e.g. the module's own `…ComingSoon` string. */
  note: string
  className?: string
}

/**
 * A field a future spec will bring, shown as a RESERVED slot — never as a
 * control.
 *
 * Both the anagrafica ("Codici ATECO") and the referente ("Settori attività")
 * used to render it as a real `<Select disabled>`: a trigger the operator can
 * tab to, click, and get nothing from. That is worse than absent — it reads as
 * a broken field rather than a planned one. A dashed outline and a plain
 * "coming soon" line say the same thing without pretending to be interactive,
 * and nothing focusable is left in the tab order.
 */
export function PlannedField({ label, note, className }: PlannedFieldProps) {
  return (
    <div
      className={cn(
        'flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 rounded-lg border border-dashed px-3 py-2',
        className,
      )}
    >
      <span className="truncate text-sm text-muted-foreground">{label}</span>
      <span className="ml-auto flex shrink-0 items-center gap-1.5 text-xs text-muted-foreground">
        <Clock3 aria-hidden="true" className="size-3.5" />
        {note}
      </span>
    </div>
  )
}
