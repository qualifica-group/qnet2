import type { ReactNode } from 'react'
import { UserAvatar } from '@/components/user-avatar'
import { avatarInitials } from '@/components/avatar-initials'
import { avatarColor } from '@/components/avatar-color'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { cn } from '@/lib/utils'

/**
 * Shared presentational kit for the record "view" sheets (the eye-icon detail
 * panels). Single source of truth for how a detail reads across every module —
 * users, companies, roles, business functions, operational sites — so the look
 * is changed HERE, once, and propagates. Compose these pieces; do not re-invent
 * a `<dl>` layout per feature.
 */

/** Scroll container for a detail sheet; fades its content in on open. */
export function DetailPanel({
  children,
  className,
}: {
  children: ReactNode
  className?: string
}) {
  return (
    <div
      className={cn(
        'flex min-h-0 flex-1 flex-col overflow-y-auto',
        'motion-safe:animate-in motion-safe:fade-in-0 motion-safe:slide-in-from-bottom-2 motion-safe:duration-500',
        className,
      )}
    >
      {children}
    </div>
  )
}

interface DetailHeroProps {
  /** Leading visual: a `DetailMonogram` or an avatar. */
  media: ReactNode
  /** Primary identifier of the record. */
  title: string
  /** Secondary line under the title (location, email, code…). */
  subtitle?: ReactNode
  /** Status/category chips shown under the subtitle. */
  badges?: ReactNode
  className?: string
}

/** Header band of a detail sheet: media + title + subtitle + badges. */
export function DetailHero({ media, title, subtitle, badges, className }: DetailHeroProps) {
  return (
    <header
      className={cn(
        'relative isolate shrink-0 overflow-hidden border-b px-6 pt-9 pb-5',
        'bg-gradient-to-br from-primary/[0.08] via-primary/[0.03] to-transparent',
        className,
      )}
    >
      <span
        aria-hidden
        className="pointer-events-none absolute -top-16 -right-14 -z-10 size-48 rounded-full bg-primary/10 blur-3xl"
      />
      <div className="flex items-start gap-4 pr-8">
        {media}
        <div className="min-w-0 flex-1 pt-0.5">
          <h2 className="truncate text-lg leading-tight font-semibold tracking-tight text-foreground">
            {title}
          </h2>
          {subtitle ? (
            <p className="mt-1 truncate text-sm text-muted-foreground">{subtitle}</p>
          ) : null}
          {badges ? <div className="mt-3 flex flex-wrap items-center gap-1.5">{badges}</div> : null}
        </div>
      </div>
    </header>
  )
}

interface DetailMonogramProps {
  /** Name driving the deterministic tint and the fallback initials. */
  name: string
  /** Optional icon shown instead of initials (for non-person entities). */
  icon?: ReactNode
  className?: string
}

/**
 * Softly-tinted disc identifying a non-person record. Same circle, same
 * palette, same initials as `UserAvatar` (it just swaps the initials for a
 * kind icon), so a record's hero reads like every other avatar in the app.
 */
export function DetailMonogram({ name, icon, className }: DetailMonogramProps) {
  const color = avatarColor(name)
  return (
    <div
      aria-hidden
      style={{ backgroundColor: color.bg, color: color.fg }}
      className={cn(
        'flex size-14 shrink-0 items-center justify-center rounded-full text-lg font-medium [&>svg]:size-6',
        className,
      )}
    >
      {icon ?? avatarInitials(name)}
    </div>
  )
}

interface DetailSectionProps {
  /** Small uppercase group label; omit for a title-less group. */
  title?: string
  icon?: ReactNode
  /** Optional trailing element (e.g. a count badge). */
  action?: ReactNode
  children: ReactNode
  className?: string
}

/** A titled group of fields, separated from siblings by a hairline. */
export function DetailSection({ title, icon, action, children, className }: DetailSectionProps) {
  return (
    <section className={cn('border-b px-6 py-5 last:border-b-0', className)}>
      {title ? (
        <div className="mb-4 flex items-center justify-between gap-2">
          <h3 className="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase [&>svg]:size-3.5">
            {icon}
            {title}
          </h3>
          {action}
        </div>
      ) : null}
      {children}
    </section>
  )
}

/** Responsive two-column key/value grid. */
export function DetailGrid({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <dl className={cn('grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2', className)}>{children}</dl>
  )
}

interface DetailFieldProps {
  label: string
  icon?: ReactNode
  children: ReactNode
  /** Span both columns (for addresses, long lists…). */
  full?: boolean
  className?: string
}

/** A single label + value pair inside a `DetailGrid`. */
export function DetailField({ label, icon, children, full, className }: DetailFieldProps) {
  return (
    <div className={cn('flex flex-col gap-1', full && 'sm:col-span-2', className)}>
      <dt className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground [&>svg]:size-3.5 [&>svg]:text-muted-foreground/70">
        {icon}
        {label}
      </dt>
      <dd className="text-sm break-words text-foreground">{children}</dd>
    </div>
  )
}

/** Placeholder for an empty value (kept as the app-wide em dash). */
export function DetailEmpty() {
  return <span className="text-muted-foreground/60">—</span>
}

interface DetailPersonProps {
  name: string
  avatarUrl?: string | null
  className?: string
}

/** A person row: avatar + name, used wherever a member is shown. */
export function DetailPerson({ name, avatarUrl, className }: DetailPersonProps) {
  return (
    <div className={cn('flex items-center gap-2', className)}>
      <UserAvatar name={name} src={avatarUrl} />
      <span className="truncate text-sm text-foreground">{name}</span>
    </div>
  )
}

/** Muted footer strip for record metadata (created date, id…). */
export function DetailMeta({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="flex items-center gap-2 px-6 py-4 text-xs text-muted-foreground">
      <span className="font-medium">{label}</span>
      <span aria-hidden>·</span>
      <span>{children}</span>
    </div>
  )
}

/** Shared loading placeholder mirroring the hero + grid shape. */
export function DetailLoading() {
  return (
    <div className="flex flex-col gap-6 p-6">
      <div className="flex items-center gap-4">
        <Skeleton className="size-14 rounded-2xl" />
        <div className="flex flex-1 flex-col gap-2">
          <Skeleton className="h-5 w-1/2" />
          <Skeleton className="h-4 w-1/3" />
        </div>
      </div>
      <div className="grid grid-cols-2 gap-4">
        <Skeleton className="h-11" />
        <Skeleton className="h-11" />
        <Skeleton className="h-11" />
        <Skeleton className="h-11" />
      </div>
    </div>
  )
}

interface DetailErrorProps {
  message: string
  retryLabel: string
  onRetry: () => void
}

/** Shared error state with a retry affordance. */
export function DetailError({ message, retryLabel, onRetry }: DetailErrorProps) {
  return (
    <div className="flex flex-col items-start gap-3 p-6">
      <p className="text-sm text-destructive">{message}</p>
      <Button variant="outline" size="sm" onClick={onRetry}>
        {retryLabel}
      </Button>
    </div>
  )
}
