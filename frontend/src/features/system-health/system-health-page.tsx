import { useTranslation } from 'react-i18next'
import { RefreshCw } from 'lucide-react'
import { PageHeader } from '@/components/page-header'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { HealthCheckCard } from '@/features/system-health/health-check-card'
import { HealthStatusIndicator } from '@/features/system-health/health-status-indicator'
import { OnlineUsersCard } from '@/features/system-health/online-users-card'
import { useSystemHealth } from '@/features/system-health/use-system-health'

const CHECK_CARD_COUNT = 4
const SKELETON_KEYS = Array.from({ length: CHECK_CARD_COUNT }, (_, index) => index)

/**
 * "System health" page (spec 0187, super-admin only: the route is gated by
 * the role guard, the backend enforces the same rule). Auto-refreshes every
 * 30 s; "Recheck" forces an immediate refetch.
 */
export default function SystemHealthPage() {
  const { t } = useTranslation('systemHealth')
  const { data, isLoading, isError, isFetching, refetch } = useSystemHealth()

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader title={t('title')} subtitle={t('subtitle')} />

      <Card className="gap-1 p-3">
        <div className="flex flex-wrap items-center justify-between gap-3">
          {data ? (
            <div className="flex flex-col gap-0.5">
              <p className="text-base font-semibold">{t(`overall.${data.overall}`)}</p>
              <div className="flex flex-wrap items-center gap-2">
                <HealthStatusIndicator status={data.overall} statusLabel={t(`status.${data.overall}`)} />
                <span className="text-xs text-muted-foreground">
                  {t('checkedAt', { time: new Date(data.checked_at).toLocaleTimeString() })}
                </span>
              </div>
            </div>
          ) : (
            <Skeleton className="h-9 w-48" />
          )}
          <Button size="sm" variant="secondary" disabled={isFetching} onClick={() => void refetch()}>
            <RefreshCw className="size-3.5" aria-hidden />
            {t('refresh')}
          </Button>
        </div>
      </Card>

      {isLoading ? (
        <div className="grid gap-3 sm:grid-cols-2" aria-busy="true">
          {SKELETON_KEYS.map((key) => (
            <Skeleton key={key} className="h-40 w-full" />
          ))}
        </div>
      ) : isError || !data ? (
        <p role="alert" className="text-sm text-destructive">
          {t('loadError')}
        </p>
      ) : (
        <>
          <OnlineUsersCard online={data.online} />
          <div className="grid gap-3 sm:grid-cols-2">
            {data.checks.map((check) => (
              <HealthCheckCard key={check.key} check={check} />
            ))}
          </div>
        </>
      )}
    </div>
  )
}
