import { useTranslation } from 'react-i18next'
import { ArrowDown, ArrowUp, Minus, Sparkles } from 'lucide-react'
import { Card } from '@/components/ui/card'
import type { RequestDashboardSummaryItem } from '@/features/request-management/dashboard-api'
import {
  type DashboardTrend,
  type DashboardTrendSentiment,
  isInverseIndicator,
} from '@/features/request-management/dashboard-comparison'
import { FALLBACK_INDICATOR_ICON, INDICATOR_ICONS } from '@/features/request-management/dashboard-indicator-meta'
import type { CategoryShare } from '@/features/request-management/dashboard-overview'
import { useCountUp } from '@/hooks/use-count-up'
import { cn } from '@/lib/utils'

const SENTIMENT_CLASS: Record<DashboardTrendSentiment, string> = {
  positive: 'bg-success/10 text-success',
  negative: 'bg-destructive/10 text-destructive',
  neutral: 'bg-muted text-muted-foreground',
}

const TREND_ICON = { up: ArrowUp, down: ArrowDown, flat: Minus } as const

function formatCount(value: number): string {
  return value.toLocaleString()
}

/** Arrow + signed percent, never color alone; the previous value is spelled out for assistive tech. */
function TrendBadge({ trend }: { trend: DashboardTrend }) {
  const { t } = useTranslation()
  const Icon = trend.percent === null ? Sparkles : TREND_ICON[trend.direction]
  const text =
    trend.percent === null
      ? t('requestManagement.dashboard.trend.new')
      : `${trend.percent > 0 ? '+' : ''}${trend.percent}%`
  const previous = t('requestManagement.dashboard.trend.vsPrevious', { value: formatCount(trend.previous) })

  return (
    <span
      className={cn(
        'inline-flex shrink-0 items-center gap-0.5 rounded-full px-1.5 py-0.5 text-xs font-medium tabular-nums',
        SENTIMENT_CLASS[trend.sentiment],
      )}
      title={previous}
    >
      <Icon aria-hidden="true" className="size-3" />
      {text}
      <span className="sr-only">{` (${previous})`}</span>
    </span>
  )
}

/** One thin 100% bar split by category (spec 0192 D-6), largest first; the text twin is for screen readers and hover. */
function ShareBar({ shares }: { shares: CategoryShare[] }) {
  const { t } = useTranslation()
  const [lead] = shares
  const otherCategories = shares.reduce((sum, share) => sum + share.categoryCount, 0) - 1
  const describe = (share: CategoryShare) =>
    t('requestManagement.dashboard.shares.item', {
      label: share.label,
      value: formatCount(share.value),
      percent: Math.round(share.percent),
    })

  return (
    <div className="flex flex-col gap-1">
      <div aria-hidden="true" className="flex h-1.5 w-full gap-px overflow-hidden rounded-full bg-muted">
        {shares.map((share) => (
          <span
            key={share.key}
            className="h-full first:rounded-l-full last:rounded-r-full"
            style={{ width: `${share.percent}%`, backgroundColor: share.color }}
            title={describe(share)}
          />
        ))}
      </div>
      {/* Plain-text lead so the bar never relies on color alone; the full split is in the list below. */}
      <p aria-hidden="true" className="truncate text-xs text-muted-foreground">
        {t('requestManagement.dashboard.shares.lead', { label: lead.label, percent: Math.round(lead.percent) })}
        {otherCategories > 0 ? ` · ${t('requestManagement.dashboard.shares.more', { count: otherCategories })}` : null}
      </p>
      <ul className="sr-only" aria-label={t('requestManagement.dashboard.shares.title')}>
        {shares.map((share) => (
          <li key={share.key}>{describe(share)}</li>
        ))}
      </ul>
    </div>
  )
}

export interface DashboardKpiCardProps {
  item: RequestDashboardSummaryItem
  /** Change against the previous period; omitted while unavailable (D-4). */
  trend?: DashboardTrend | null
  /** Split by category, overview only; omitted (or empty) inside a category tab. */
  shares?: CategoryShare[]
}

/**
 * A statistics KPI (spec 0192 D-4/D-6/D-7): indicator icon, the number
 * counting up to its value, the change against the previous period and, in
 * the overview, which categories it comes from. Unhandled-work indicators
 * carry the amber accent: they are the numbers to bring down.
 */
export function DashboardKpiCard({ item, trend, shares }: DashboardKpiCardProps) {
  const displayed = useCountUp(item.value)
  const Icon = INDICATOR_ICONS[item.key] ?? FALLBACK_INDICATOR_ICON
  const attention = isInverseIndicator(item.key)

  return (
    <Card className="group relative gap-2 overflow-hidden px-3 py-3 transition-shadow hover:shadow-md">
      <span
        aria-hidden="true"
        className={cn(
          'pointer-events-none absolute -top-8 -right-8 size-24 rounded-full blur-2xl transition-opacity group-hover:opacity-100',
          attention ? 'bg-warning/15 opacity-80' : 'bg-primary/10 opacity-60',
        )}
      />
      <div className="relative flex min-w-0 items-center gap-2">
        <span
          className={cn(
            'flex size-7 shrink-0 items-center justify-center rounded-lg',
            attention ? 'bg-warning/15 text-warning' : 'bg-primary/10 text-primary',
          )}
        >
          <Icon aria-hidden="true" className="size-3.5" />
        </span>
        <span className="min-w-0 truncate text-xs font-medium text-muted-foreground" title={item.label}>
          {item.label}
        </span>
      </div>
      <div className="relative flex flex-wrap items-baseline justify-between gap-x-2 gap-y-1">
        <span className="text-2xl font-semibold tracking-tight tabular-nums">
          <span aria-hidden="true">{formatCount(displayed)}</span>
          <span className="sr-only">{formatCount(item.value)}</span>
        </span>
        {trend ? <TrendBadge trend={trend} /> : null}
      </div>
      {shares && shares.length > 0 ? <ShareBar shares={shares} /> : null}
    </Card>
  )
}
