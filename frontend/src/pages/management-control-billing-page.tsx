import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { defaultBillingFilters } from '@/features/management-control/api'
import { BillingFiltersBar } from '@/features/management-control/billing-filters-bar'
import { BillingPanel } from '@/features/management-control/billing-panel'
import type { BillingFilters } from '@/features/management-control/billing-types'

/**
 * Management control, billing (spec 0271): filters kept in client state, then
 * the panel that reads the server. Gated by `management-control.view`.
 */
export default function ManagementControlBillingPage() {
  const { t } = useTranslation()
  const [filters, setFilters] = useState<BillingFilters>(defaultBillingFilters)

  return (
    <Can
      permission="management-control.view"
      fallback={<p className="text-sm text-muted-foreground">{t('managementControl.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-4">
        <PageHeader title={t('managementControl.billing.title')} />
        <BillingFiltersBar filters={filters} onChange={setFilters} />
        <BillingPanel filters={filters} />
      </div>
    </Can>
  )
}
