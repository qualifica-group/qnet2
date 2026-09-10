import { useTranslation } from 'react-i18next'
import { Ban, CheckCircle2, Scale, UserCheck } from 'lucide-react'
import type { LucideIcon } from 'lucide-react'
import { Label } from '@/components/ui/label'
import { cn } from '@/lib/utils'

export type AssignmentMode = 'single' | 'balanced'

/**
 * Presentation tokens per assignment mode. Each mode owns a distinct semantic
 * accent (emerald = workload split, sky = single operator) so the two cards
 * read as different actions at a glance. Full class strings are inlined (not
 * built dynamically) so Tailwind's JIT keeps them.
 */
const ASSIGNMENT_MODES: ReadonlyArray<{
  mode: AssignmentMode
  icon: LucideIcon
  /** Icon chip styling while selected. */
  chip: string
  /** Card border/tint/ring while selected. */
  card: string
  /** Accent color for the selected check mark. */
  accent: string
}> = [
  {
    mode: 'balanced',
    icon: Scale,
    chip: 'bg-emerald-500/15 text-emerald-600 ring-1 ring-emerald-500/25 dark:text-emerald-400',
    card: 'border-emerald-500/60 bg-emerald-500/[0.07] ring-2 ring-emerald-500/25',
    accent: 'text-emerald-600 dark:text-emerald-400',
  },
  {
    mode: 'single',
    icon: UserCheck,
    chip: 'bg-sky-500/15 text-sky-600 ring-1 ring-sky-500/25 dark:text-sky-400',
    card: 'border-sky-500/60 bg-sky-500/[0.07] ring-2 ring-sky-500/25',
    accent: 'text-sky-600 dark:text-sky-400',
  },
]

/** Stable empty default: an inline `[]` would be a new reference every render. */
const NO_DISABLED_MODES: readonly AssignmentMode[] = []

export interface AssignOperatorsModePickerProps {
  value: AssignmentMode | null
  onChange: (mode: AssignmentMode) => void
  /** Entity-specific sentence under each mode, resolved by the consumer. */
  hints?: Record<AssignmentMode, string>
  /**
   * Modes the current selection cannot express (spec 0113 AC-029): the card
   * stays visible, focusable and readable, but is not selectable.
   */
  disabledModes?: readonly AssignmentMode[]
  /** Reason shown in place of the hint on a disabled card. */
  disabledModeHints?: Partial<Record<AssignmentMode, string>>
  isSubmitting: boolean
}

/**
 * Step 1 of the assign popup: the mode radiogroup. Split out of
 * `assign-operators-dialog.tsx` to keep both files well under the size
 * thresholds; it owns no state, the dialog does.
 */
export function AssignOperatorsModePicker({
  value,
  onChange,
  hints,
  disabledModes = NO_DISABLED_MODES,
  disabledModeHints,
  isSubmitting,
}: AssignOperatorsModePickerProps) {
  const { t } = useTranslation()

  return (
    <div className="space-y-2">
      <Label className="text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">
        {t('leads.assign.mode.label')}
      </Label>
      <div
        role="radiogroup"
        aria-label={t('leads.assign.mode.label')}
        className="grid gap-2 sm:grid-cols-2"
      >
        {ASSIGNMENT_MODES.map((entry) => {
          const { mode, icon: Icon } = entry
          const selected = value === mode
          const unavailable = disabledModes.includes(mode)
          const reason = unavailable ? disabledModeHints?.[mode] : undefined
          const reasonId = `assign-operators-mode-${mode}-reason`

          return (
            <button
              key={mode}
              type="button"
              role="radio"
              aria-checked={selected}
              aria-label={t(`leads.assign.actions.${mode}`)}
              // Kept focusable on purpose: an `aria-disabled` card can still be
              // reached by keyboard, so the reason below is announced instead
              // of the card silently disappearing from the tab order.
              aria-disabled={unavailable || undefined}
              aria-describedby={reason ? reasonId : undefined}
              disabled={isSubmitting}
              onClick={() => {
                if (!unavailable) {
                  onChange(mode)
                }
              }}
              className={cn(
                'group relative flex items-start gap-3 rounded-xl border bg-card p-3 text-left transition-all',
                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1 focus-visible:ring-offset-background',
                'disabled:pointer-events-none disabled:opacity-50',
                unavailable
                  ? 'cursor-not-allowed border-dashed border-border bg-muted/40'
                  : selected
                    ? cn(entry.card, 'shadow-sm')
                    : 'border-border hover:border-foreground/20 hover:bg-muted/40 hover:shadow-sm motion-safe:hover:-translate-y-0.5',
              )}
            >
              <span
                aria-hidden="true"
                className={cn(
                  'flex size-8 shrink-0 items-center justify-center rounded-lg transition-colors',
                  unavailable
                    ? 'bg-muted text-muted-foreground'
                    : selected
                      ? entry.chip
                      : 'bg-muted text-muted-foreground group-hover:bg-muted/70',
                )}
              >
                <Icon className="size-4" />
              </span>
              <span className="min-w-0 flex-1 space-y-0.5 pr-4">
                <span
                  className={cn(
                    'block text-xs font-semibold',
                    unavailable ? 'text-muted-foreground' : 'text-foreground',
                  )}
                >
                  {t(`leads.assign.actions.${mode}`)}
                </span>
                {reason ? (
                  <span
                    id={reasonId}
                    className="flex items-start gap-1 text-[11px] leading-snug font-medium text-muted-foreground"
                  >
                    <Ban className="mt-0.5 size-3 shrink-0" aria-hidden="true" />
                    {reason}
                  </span>
                ) : (
                  <span className="block text-[11px] leading-snug text-muted-foreground">
                    {hints?.[mode] ?? t(`leads.assign.actions.${mode}Hint`)}
                  </span>
                )}
              </span>
              {selected && (
                <CheckCircle2
                  aria-hidden="true"
                  className={cn(
                    'absolute top-2.5 right-2.5 size-4 shrink-0',
                    entry.accent,
                    'motion-safe:animate-in motion-safe:fade-in motion-safe:zoom-in-75',
                  )}
                />
              )}
            </button>
          )
        })}
      </div>
    </div>
  )
}
