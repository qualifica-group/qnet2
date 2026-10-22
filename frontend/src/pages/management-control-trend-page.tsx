import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { defaultTrendFilters } from '@/features/management-control/api'
import { TrendFiltersBar } from '@/features/management-control/trend-filters-bar'
import { TrendPanel } from '@/features/management-control/trend-panel'
import type { TrendFilters } from '@/features/management-control/trend-types'

/**
 * Management control, monthly trend (spec 0270): filters kept in client state, then
 * the panel that reads the server. Gated by `management-control.view`.
 */
export default function ManagementControlTrendPage() {
  const { t } = useTranslation()
  const [filters, setFilters] = useState<TrendFilters>(defaultTrendFilters)

  return (
    <Can
      permission="management-control.view"
      fallback={<p className="text-sm text-muted-foreground">{t('managementControl.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-4">
        <PageHeader />
        <TrendFiltersBar filters={filters} onChange={setFilters} />
        <TrendPanel filters={filters} />
      </div>
    </Can>
  )
}
