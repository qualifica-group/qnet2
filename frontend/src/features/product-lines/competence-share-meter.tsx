import { useTranslation } from 'react-i18next'
import { CircleAlert, CircleCheck } from 'lucide-react'
import { toPercentage } from '@/components/ui/stat-chart-tokens'
import { cn } from '@/lib/utils'
import { COMPETENCE_SHARE_TOTAL, competenceShareColor } from '@/features/product-lines/types'

interface CompetenceShareMeterProps {
  /** Each row's share, in row order; a blank share is null and draws no segment. */
  shares: readonly (number | null)[]
  total: number
}

/**
 * Spec 0346: the person's shares as one bar of row-colored segments, with the
 * total and what is missing or exceeding 100% beside an icon (never color
 * alone). Past 100 the bar scales to the total, so every segment stays visible.
 * The bar is decorative: the status line carries the data as text.
 */
export function CompetenceShareMeter({ shares, total }: CompetenceShareMeterProps) {
  const { t } = useTranslation()
  const scale = Math.max(total, COMPETENCE_SHARE_TOTAL)
  const difference = total - COMPETENCE_SHARE_TOTAL
  const complete = difference === 0

  return (
    <div className="flex flex-col gap-1.5">
      <div aria-hidden="true" className="flex h-2 w-full gap-0.5 overflow-hidden rounded-full bg-muted">
        {shares.map((share, index) =>
          share !== null && share > 0 ? (
            <div
              // The segment's identity IS the row position, as in the editor.
              key={index}
              className="h-full transition-[width]"
              style={{ width: `${toPercentage(share, scale)}%`, backgroundColor: competenceShareColor(index) }}
            />
          ) : null,
        )}
      </div>
      <p
        aria-live="polite"
        className={cn(
          'flex items-center gap-1 text-xs font-medium',
          complete ? 'text-success' : difference < 0 ? 'text-warning' : 'text-destructive',
        )}
      >
        {complete ? (
          <CircleCheck aria-hidden="true" className="size-3.5" />
        ) : (
          <CircleAlert aria-hidden="true" className="size-3.5" />
        )}
        <span className="tabular-nums">{t('productLines.shareTotal', { total })}</span>
        {complete ? null : (
          <span className="font-normal text-muted-foreground tabular-nums">
            ·{' '}
            {difference < 0
              ? t('productLines.shareMissing', { n: -difference })
              : t('productLines.shareExceeding', { n: difference })}
          </span>
        )}
      </p>
    </div>
  )
}

interface CompetenceShareValueProps {
  share: number
  index: number
}

/** Spec 0346: a read-only row's share — a mini bar in the row's color and the figure. */
export function CompetenceShareValue({ share, index }: CompetenceShareValueProps) {
  const { t } = useTranslation()

  return (
    <span className="flex shrink-0 items-center gap-1.5">
      <span aria-hidden="true" className="h-1.5 w-10 overflow-hidden rounded-full bg-muted">
        <span
          className="block h-full rounded-full"
          style={{
            width: `${toPercentage(share, COMPETENCE_SHARE_TOTAL)}%`,
            backgroundColor: competenceShareColor(index),
          }}
        />
      </span>
      <span className="w-9 text-right text-xs font-medium tabular-nums">{t('productLines.shareReadOnly', { n: share })}</span>
    </span>
  )
}
