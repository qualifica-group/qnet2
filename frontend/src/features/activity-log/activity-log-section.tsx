import { useTranslation } from 'react-i18next'
import { ArrowRight, Loader2, User as UserIcon } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { formatDateTime } from '@/lib/formatting/date-display'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useActivityLog } from '@/features/activity-log/use-activity-log'
import type { ActivityLogChange, ActivityLogEntry, ActivityLogEvent } from '@/features/activity-log/types'

export interface ActivityLogSectionProps {
  /** Registry key of the aggregating resource, e.g. "users". */
  resource: string
  /** Id of the root record whose aggregated activity log is shown. */
  id: number
}

const SKELETON_ROWS = 3

/** Badge color per event — theme tokens only, text always carries the meaning too. */
const EVENT_BADGE_VARIANT: Record<ActivityLogEvent, 'default' | 'secondary' | 'destructive' | 'outline'> = {
  created: 'default',
  updated: 'secondary',
  deleted: 'destructive',
  restored: 'outline',
}

/**
 * Reusable timeline of a resource record's aggregated activity log
 * (spec 0034). Mounted both as a `DetailSection` (user detail) and inside a
 * row-action Dialog (users table) — this component owns no gating, callers
 * decide when it is authorized to mount.
 */
export function ActivityLogSection({ resource, id }: ActivityLogSectionProps) {
  const { t } = useTranslation()
  const {
    data,
    isLoading,
    isError,
    refetch,
    hasNextPage,
    isFetchingNextPage,
    fetchNextPage,
  } = useActivityLog(resource, id)

  if (isLoading) {
    return (
      <div className="flex flex-col gap-2">
        {Array.from({ length: SKELETON_ROWS }).map((_, index) => (
          <Skeleton key={index} className="h-14 w-full" />
        ))}
      </div>
    )
  }

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-2">
        <p className="text-xs text-destructive">{t('activityLog.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  const entries = data?.pages.flatMap((page) => page.items) ?? []

  if (entries.length === 0) {
    return <p className="text-xs text-muted-foreground">{t('activityLog.empty')}</p>
  }

  return (
    <div className="flex flex-col gap-3">
      <ol className="flex flex-col gap-2 border-l border-border pl-3">
        {entries.map((entry) => (
          <ActivityLogItem key={entry.id} entry={entry} />
        ))}
      </ol>
      {hasNextPage ? (
        <Button
          variant="outline"
          size="sm"
          onClick={() => fetchNextPage()}
          disabled={isFetchingNextPage}
        >
          {isFetchingNextPage ? <Loader2 className="size-3.5 animate-spin" aria-hidden="true" /> : null}
          {t('activityLog.loadMore')}
        </Button>
      ) : null}
    </div>
  )
}

interface ActivityLogItemProps {
  entry: ActivityLogEntry
}

/** A single timeline row: when/who/what happened, plus its field-level diff. */
function ActivityLogItem({ entry }: ActivityLogItemProps) {
  const { t } = useTranslation()
  const causerName = entry.causer.name ?? t('activityLog.systemCauser')

  return (
    <li className="flex flex-col gap-1 rounded-lg border bg-card p-2.5 text-xs text-card-foreground shadow-sm">
      <div className="flex flex-wrap items-center justify-between gap-x-2 gap-y-0.5">
        <div className="flex items-center gap-1.5">
          <Badge variant={EVENT_BADGE_VARIANT[entry.event]}>{t(`activityLog.events.${entry.event}`)}</Badge>
          <span className="font-medium text-foreground">
            {t(`activityLog.modules.${entry.module}`, { defaultValue: entry.module })}
          </span>
        </div>
        <span className="text-muted-foreground">{formatDateTime(entry.logged_at)}</span>
      </div>
      <p className="flex items-center gap-1 text-muted-foreground">
        <UserIcon className="size-3" aria-hidden="true" />
        {causerName}
      </p>
      {entry.changes.length > 0 ? (
        <ul className="flex flex-col gap-0.5 border-t pt-1.5">
          {entry.changes.map((change) => (
            <ActivityLogChangeRow key={change.field} change={change} event={entry.event} />
          ))}
        </ul>
      ) : null}
    </li>
  )
}

interface ActivityLogChangeRowProps {
  change: ActivityLogChange
  event: ActivityLogEvent
}

/**
 * One field-level diff row: the field label, then the old value (struck
 * through, shown only when it exists) pointing to the new value. `created`
 * shows only the new value, `deleted` only the old one — there is nothing to
 * diff against on either side.
 */
function ActivityLogChangeRow({ change, event }: ActivityLogChangeRowProps) {
  const { t } = useTranslation()
  const fieldLabel = t(`activityLog.fields.${change.field}`, {
    defaultValue: humanizeFieldKey(change.field),
  })
  const showOld = event !== 'created' && change.old_value !== null
  const showNew = event !== 'deleted'

  return (
    <li className="flex flex-wrap items-baseline gap-x-1 text-foreground">
      <span className="font-medium">{fieldLabel}</span>
      <span>{':'}</span>
      {showOld ? (
        <span className="max-w-40 truncate text-muted-foreground line-through">
          {renderChangeValue(change.old_value, change.old_display)}
        </span>
      ) : null}
      {showOld && showNew ? <ArrowRight className="size-3 shrink-0 text-muted-foreground" aria-hidden="true" /> : null}
      {showNew ? (
        <span className="max-w-40 truncate">{renderChangeValue(change.new_value, change.new_display)}</span>
      ) : null}
    </li>
  )
}

/** Renders a before/after value: the server-resolved FK label when present, else the raw value. */
function renderChangeValue(rawValue: unknown, display: string | null): string {
  return display ?? formatChangeValue(rawValue)
}

/** Renders a raw before/after value as a compact string for the diff line. */
function formatChangeValue(value: unknown): string {
  if (value === null || value === undefined) {
    return '—'
  }
  if (typeof value === 'object') {
    return JSON.stringify(value)
  }
  return String(value)
}

/**
 * Last-resort label for a field with no `activityLog.fields.*` entry: strips
 * a trailing `_id` (foreign keys) and turns underscores into spaces, e.g.
 * `registry_id` -> `Registry`, `agreement_status` -> `Agreement status`.
 */
function humanizeFieldKey(field: string): string {
  const base = field.endsWith('_id') ? field.slice(0, -3) : field
  const spaced = base.replace(/_/g, ' ')
  return spaced.charAt(0).toUpperCase() + spaced.slice(1)
}
