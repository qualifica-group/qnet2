import type { ReactNode } from 'react'
import { cn } from '@/lib/utils'

/**
 * Shared presentational kit for an enterprise-CRM "record" surface (as
 * opposed to `detail-panel.tsx`'s read-only view sheets): identity header,
 * KPI strip, a two-column body of titled sections, and a metadata footer.
 * Every piece is `@container`-driven so the same tree renders correctly
 * inside a resizable Sheet (min 380px) and on a full-bleed page — never
 * gate layout on viewport breakpoints here.
 */

/** Root scroll container; replaces `DetailPanel` at page/sheet level. */
export function RecordCanvas({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <div className={cn('@container flex flex-1 flex-col overflow-y-auto bg-surface', className)}>
      <div className="flex flex-col gap-4 p-4">{children}</div>
    </div>
  )
}

/** Bare `bg-card` surface: no padding of its own, so bands run edge to edge with their own hairlines. */
export function RecordCard({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <div className={cn('overflow-hidden rounded-xl border bg-card shadow-sm', className)}>{children}</div>
  )
}

interface RecordCardHeaderProps {
  media?: ReactNode
  title: ReactNode
  subtitle?: ReactNode
  badges?: ReactNode
  actions?: ReactNode
  className?: string
}

/** Identity band of a `RecordCard`: media + title (`<h2>`) + subtitle + badges, actions pinned top-right. */
export function RecordCardHeader({ media, title, subtitle, badges, actions, className }: RecordCardHeaderProps) {
  return (
    <div className={cn('flex items-start gap-3 border-b p-4', className)}>
      {media}
      <div className="min-w-0 flex-1">
        <h2 className="truncate text-base font-semibold text-foreground">{title}</h2>
        {subtitle ? <p className="mt-0.5 truncate text-sm text-muted-foreground">{subtitle}</p> : null}
        {badges ? <div className="mt-2 flex flex-wrap items-center gap-1.5">{badges}</div> : null}
      </div>
      {actions ? <div className="flex shrink-0 items-center gap-1.5">{actions}</div> : null}
    </div>
  )
}

/** Horizontal band of `RecordStat` tiles. The tint (`bg-muted/40`) covers the whole band, not each tile. */
export function RecordStatStrip({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <div
      className={cn(
        'grid grid-cols-1 gap-3 border-y bg-muted/40 p-4 @sm:grid-cols-2 @2xl:grid-cols-4',
        className,
      )}
    >
      {children}
    </div>
  )
}

interface RecordStatProps {
  label: string
  value: ReactNode
  hint?: ReactNode
  icon?: ReactNode
  className?: string
}

/**
 * Compact KPI cell. Deliberately not `components/ui/stat-card.tsx`: `StatCard`
 * wraps a `<Card>` (`bg-card`) and would be card-on-card inside a strip that
 * already carries the tint — this is a bare tile instead.
 */
export function RecordStat({ label, value, hint, icon, className }: RecordStatProps) {
  return (
    <div className={cn('flex min-w-0 flex-col gap-0.5', className)}>
      <span className="flex items-center gap-1.5 text-xs text-muted-foreground [&>svg]:size-3.5">
        {icon}
        {label}
      </span>
      <span className="truncate text-sm font-semibold tabular-nums text-foreground">{value}</span>
      {hint ? <span className="truncate text-xs text-muted-foreground">{hint}</span> : null}
    </div>
  )
}

/** Body grid holding `RecordSection` blocks: single column, two on wide containers. */
export function RecordSectionsGrid({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <div className={cn('grid grid-cols-1 items-start gap-4 p-4 @2xl:grid-cols-2', className)}>{children}</div>
  )
}

interface RecordSectionProps {
  title: string
  icon?: ReactNode
  action?: ReactNode
  full?: boolean
  children: ReactNode
  className?: string
}

/** A titled group inside `RecordSectionsGrid`; micro-heading mirrors `DetailSection`'s so the two kits read as one family. */
export function RecordSection({ title, icon, action, full, children, className }: RecordSectionProps) {
  return (
    <section className={cn('flex min-w-0 flex-col gap-3', full && '@2xl:col-span-2', className)}>
      <div className="flex items-center justify-between gap-2">
        <h3 className="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase [&>svg]:size-3.5">
          {icon}
          {title}
        </h3>
        {action}
      </div>
      {children}
    </section>
  )
}

/** Dense, hairline-separated field rows. */
export function RecordFieldList({ children, className }: { children: ReactNode; className?: string }) {
  return <dl className={cn('divide-y divide-border/60', className)}>{children}</dl>
}

interface RecordFieldProps {
  label: string
  icon?: ReactNode
  children: ReactNode
  className?: string
}

/** One row inside a `RecordFieldList`: stacked when narrow, a spec-sheet row (~40% label column) at `@md`. */
export function RecordField({ label, icon, children, className }: RecordFieldProps) {
  return (
    <div className={cn('flex flex-col gap-1 py-2 @md:flex-row @md:items-baseline @md:gap-3', className)}>
      <dt className="flex min-w-0 shrink-0 items-center gap-1.5 text-xs text-muted-foreground [&>svg]:size-3.5 @md:w-2/5">
        {icon}
        {label}
      </dt>
      <dd className="min-w-0 text-sm break-words text-foreground @md:flex-1">{children}</dd>
    </div>
  )
}

/** Muted footer strip for record metadata (created date, id, owner…). */
export function RecordMeta({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <div
      className={cn(
        'flex flex-wrap items-center gap-x-3 gap-y-1 border-t px-4 py-3 text-xs text-muted-foreground',
        className,
      )}
    >
      {children}
    </div>
  )
}
