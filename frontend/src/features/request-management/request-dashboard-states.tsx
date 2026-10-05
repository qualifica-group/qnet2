import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { KPI_GRID_CLASS } from '@/features/request-management/dashboard-overview'

const SKELETON_TAB_COUNT = 4
const SKELETON_TILE_COUNT = 4

interface DashboardNoticeProps {
  message: string
  tone: 'error' | 'info'
  onRetry?: () => void
}

/** One notice box for every "nothing to chart" outcome, retryable when a refetch can help. */
export function DashboardNotice({ message, tone, onRetry }: DashboardNoticeProps) {
  const { t } = useTranslation()

  return (
    <div className="flex flex-col items-start gap-2 rounded-xl border bg-card p-3">
      <p
        className={tone === 'error' ? 'text-sm text-destructive' : 'text-sm text-muted-foreground'}
        role={tone === 'error' ? 'alert' : 'status'}
      >
        {message}
      </p>
      {onRetry ? (
        <Button variant="outline" size="sm" onClick={onRetry}>
          {t('common.retry')}
        </Button>
      ) : null}
    </div>
  )
}

/** Placeholder shaped like the loaded page — tab strip, KPI tiles, heatmap — while the aggregates load. */
export function DashboardSkeleton() {
  return (
    <div className="flex flex-col gap-4">
      <div className="flex gap-1">
        {Array.from({ length: SKELETON_TAB_COUNT }).map((_, index) => (
          <Skeleton key={index} className="h-7 w-20" />
        ))}
      </div>
      <div className={KPI_GRID_CLASS}>
        {Array.from({ length: SKELETON_TILE_COUNT }).map((_, index) => (
          <div key={index} className="flex flex-col gap-2 rounded-xl border bg-card p-3">
            <div className="flex items-center gap-2">
              <Skeleton className="size-7 rounded-lg" />
              <Skeleton className="h-3 w-20" />
            </div>
            <Skeleton className="h-7 w-16" />
            <Skeleton className="h-1.5 w-full" />
          </div>
        ))}
      </div>
      <Skeleton className="h-48 w-full" />
    </div>
  )
}
