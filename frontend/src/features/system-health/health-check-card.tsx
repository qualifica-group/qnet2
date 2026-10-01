import { useTranslation } from 'react-i18next'
import { Card } from '@/components/ui/card'
import { HealthStatusIndicator } from '@/features/system-health/health-status-indicator'
import type { HealthCheck } from '@/features/system-health/types'

export function HealthCheckCard({ check }: { check: HealthCheck }) {
  const { t, i18n } = useTranslation('systemHealth')

  return (
    <Card className="gap-3 p-4">
      <div className="flex items-center justify-between gap-2">
        <h2 className="text-base font-semibold">{t(`subsystems.${check.key}`)}</h2>
        <HealthStatusIndicator status={check.status} statusLabel={t(`status.${check.status}`)} />
      </div>
      {check.latency_ms !== null ? (
        <p className="text-xs text-muted-foreground">{t('latency', { ms: check.latency_ms })}</p>
      ) : null}
      <ul className="flex flex-col gap-1.5">
        {check.details.map((detail) => {
          const detailKey = `details.${detail.key}`
          return (
            <li key={detail.key} className="flex items-center justify-between gap-2 text-sm">
              <span className="min-w-0 truncate">
                {i18n.exists(detailKey, { ns: 'systemHealth' }) ? t(detailKey) : detail.key}
                {detail.value !== null ? (
                  <span className="ml-1.5 text-xs text-muted-foreground">{detail.value}</span>
                ) : null}
              </span>
              <HealthStatusIndicator status={detail.status} statusLabel={t(`status.${detail.status}`)} />
            </li>
          )
        })}
      </ul>
    </Card>
  )
}
