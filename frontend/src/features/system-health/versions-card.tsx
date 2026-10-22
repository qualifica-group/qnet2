import { useTranslation } from 'react-i18next'
import { Card } from '@/components/ui/card'
import { FRONTEND_VERSIONS } from '@/features/system-health/frontend-versions'
import type { RuntimeVersion } from '@/features/system-health/types'

function VersionGroup({ title, versions }: { title: string; versions: RuntimeVersion[] }) {
  const { t } = useTranslation('systemHealth')

  return (
    <div className="flex flex-col gap-1.5">
      <h3 className="text-xs font-medium text-muted-foreground">{title}</h3>
      <dl className="flex flex-col gap-1">
        {versions.map((version) => (
          <div key={version.key} className="flex items-center justify-between gap-2 text-sm">
            <dt className="min-w-0 truncate">{t(`versions.items.${version.key}`, { defaultValue: version.key })}</dt>
            <dd className="text-xs tabular-nums text-muted-foreground">
              {version.value ?? t('versions.unavailable')}
            </dd>
          </div>
        ))}
      </dl>
    </div>
  )
}

export function VersionsCard({ backend }: { backend: RuntimeVersion[] }) {
  const { t } = useTranslation('systemHealth')

  return (
    <Card className="gap-3 p-4">
      <h2 className="text-base font-semibold">{t('versions.title')}</h2>
      <div className="grid gap-3 sm:grid-cols-2">
        <VersionGroup title={t('versions.backend')} versions={backend} />
        <VersionGroup title={t('versions.frontend')} versions={FRONTEND_VERSIONS} />
      </div>
    </Card>
  )
}
