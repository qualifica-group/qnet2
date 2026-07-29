import type { ReactNode } from 'react'
import type { LucideIcon } from 'lucide-react'
import { AlertTriangle, Loader2, OctagonX } from 'lucide-react'
import { cn } from '@/lib/utils'

/**
 * Shared presentational primitives for the import wizard steps (spec 0033
 * visual refactor): section headers, inline alerts, KPI tiles and busy states.
 * Purely visual — no form state, no data fetching, no authorization logic.
 */

interface StepSectionHeaderProps {
  /** Contextual glyph rendered in the header chip (mirrors `FormSection`'s header). */
  icon: LucideIcon
  title: ReactNode
  description?: ReactNode
  /** Right-aligned slot (e.g. an attention chip). */
  aside?: ReactNode
}

/** Icon chip + title + optional description, without the card wrapper (the wizard body already lives in a card). */
export function StepSectionHeader({ icon: Icon, title, description, aside }: StepSectionHeaderProps) {
  return (
    <div className="flex items-center gap-3">
      <span className="flex size-9 shrink-0 items-center justify-center rounded-lg border border-primary/15 bg-primary/10 text-primary">
        <Icon className="size-[18px]" aria-hidden="true" />
      </span>
      <div className="min-w-0">
        <h3 className="text-sm font-semibold tracking-tight text-foreground">{title}</h3>
        {description ? <p className="mt-0.5 text-xs text-muted-foreground">{description}</p> : null}
      </div>
      {aside ? <div className="ml-auto flex shrink-0 items-center gap-2">{aside}</div> : null}
    </div>
  )
}

type StepAlertTone = 'destructive' | 'warning'

const ALERT_TONE_CLASSES: Record<StepAlertTone, string> = {
  destructive: 'border-destructive/30 bg-destructive/5 text-destructive',
  warning: 'border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-400',
}

interface StepAlertProps {
  tone?: StepAlertTone
  /** ARIA live role — `alert` for errors (default), `status` for informational notes. */
  role?: 'alert' | 'status'
  children: ReactNode
}

/** Inline feedback panel replacing bare red paragraphs: tinted background, icon, same live role. */
export function StepAlert({ tone = 'destructive', role = 'alert', children }: StepAlertProps) {
  const Icon = tone === 'destructive' ? OctagonX : AlertTriangle
  return (
    <div
      role={role}
      className={cn(
        'flex items-start gap-2 rounded-lg border px-3 py-2.5 text-sm motion-safe:animate-in motion-safe:fade-in-0 motion-safe:slide-in-from-bottom-1 motion-safe:duration-300',
        ALERT_TONE_CLASSES[tone],
      )}
    >
      <Icon className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
      <div className="min-w-0">{children}</div>
    </div>
  )
}

type StatTileTone = 'default' | 'success' | 'warning' | 'destructive' | 'info'

const STAT_TONE_CLASSES: Record<StatTileTone, string> = {
  default: 'text-foreground',
  success: 'text-emerald-600 dark:text-emerald-500',
  warning: 'text-amber-700 dark:text-amber-400',
  destructive: 'text-destructive',
  info: 'text-sky-700 dark:text-sky-400',
}

interface StatTileProps {
  label: string
  value: ReactNode
  tone?: StatTileTone
}

/** Compact KPI tile for step counters (no nested card — the wizard already lives in one). */
export function StatTile({ label, value, tone = 'default' }: StatTileProps) {
  return (
    <div className="flex min-w-0 flex-col gap-0.5 rounded-lg border bg-muted/30 px-3 py-2">
      <span className="truncate text-xs font-medium text-muted-foreground">{label}</span>
      <span className={cn('text-lg font-semibold tabular-nums', STAT_TONE_CLASSES[tone])}>{value}</span>
    </div>
  )
}

/** Centered busy indicator for server-side phases (analyzing, staging, loading). */
export function BusyState({ label, children }: { label: string; children?: ReactNode }) {
  return (
    <div className="flex flex-col items-center gap-3 py-10 text-center" role="status">
      <span className="flex size-12 items-center justify-center rounded-full bg-primary/10">
        <Loader2 className="size-6 animate-spin text-primary" aria-hidden="true" />
      </span>
      <p className="text-sm font-medium text-muted-foreground">{label}</p>
      {children}
    </div>
  )
}
