import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { defaultExposureFilters } from '@/features/management-control/api'
import { ExposureFiltersBar } from '@/features/management-control/exposure-filters-bar'
import { ExposurePanel } from '@/features/management-control/exposure-panel'
import type { ExposureFilters } from '@/features/management-control/exposure-types'

/**
 * Management control, schedule and exposure (spec 0269): filters kept in client state, then
 * the panel that reads the server. Gated by `management-control.view`.
 */
export default function ManagementControlExposurePage() {
  const { t } = useTranslation()
  const [filters, setFilters] = useState<ExposureFilters>(defaultExposureFilters)

  return (
    <Can
      permission="management-control.view"
      fallback={<p className="text-sm text-muted-foreground">{t('managementControl.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-4">
        <PageHeader title={t('managementControl.exposure.title')} />
        <ExposureFiltersBar filters={filters} onChange={setFilters} />
        <ExposurePanel filters={filters} />
      </div>
    </Can>
  )
}
