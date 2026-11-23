import { useTranslation } from 'react-i18next'
import { Card } from '@/components/ui/card'
import { HealthStatusIndicator } from '@/features/system-health/health-status-indicator'
import type { HealthCheck } from '@/features/system-health/types'

/**
 * Core checks are labelled in the `systemHealth` namespace; a check added by an
 * integration brings its own namespace (named like the check) with
 * `health.title` and `health.details.<detail>`, falling back to the raw key.
 */
export function HealthCheckCard({ check }: { check: HealthCheck }) {
  const { t, i18n } = useTranslation('systemHealth')
  const ownTitleKey = `${check.key}:health.title`
  const hasOwnNamespace = i18n.exists(ownTitleKey)
  const title = i18n.exists(`subsystems.${check.key}`, { ns: 'systemHealth' })
    ? t(`subsystems.${check.key}`)
    : hasOwnNamespace
      ? i18n.t(ownTitleKey)
      : check.key

  const detailLabel = (detailKey: string): string => {
    const ownKey = `${check.key}:health.details.${detailKey}`
    if (hasOwnNamespace && i18n.exists(ownKey)) {
      return i18n.t(ownKey)
    }
    const coreKey = `details.${detailKey}`
    return i18n.exists(coreKey, { ns: 'systemHealth' }) ? t(coreKey) : detailKey
  }

  return (
    <Card className="gap-3 p-4">
      <div className="flex items-center justify-between gap-2">
        <h2 className="text-base font-semibold">{title}</h2>
        <HealthStatusIndicator status={check.status} statusLabel={t(`status.${check.status}`)} />
      </div>
      {check.latency_ms !== null ? (
        <p className="text-xs text-muted-foreground">{t('latency', { ms: check.latency_ms })}</p>
      ) : null}
      <ul className="flex flex-col gap-1.5">
        {check.details.map((detail) => {
          return (
            <li key={detail.key} className="flex items-center justify-between gap-2 text-sm">
              <span className="min-w-0 truncate">
                {detailLabel(detail.key)}
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
