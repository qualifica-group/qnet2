import { useTranslation } from 'react-i18next'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { RequestDashboard } from '@/features/request-management/request-dashboard'
import { REQUEST_STATISTICS_PERMISSION } from '@/features/request-management/types'

/**
 * Request Management Statistics page (spec 0185). Light composition only:
 * gates access with `request-statistics.view` (the backend re-authorizes every
 * report endpoint) and mounts the always-visible dashboard, which owns filters,
 * data fetching and export.
 */
export default function RequestStatisticsPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission={REQUEST_STATISTICS_PERMISSION}
      fallback={<p className="text-sm text-muted-foreground">{t('requestManagement.dashboard.pageForbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-4">
        <PageHeader />
        <RequestDashboard />
      </div>
    </Can>
  )
}
