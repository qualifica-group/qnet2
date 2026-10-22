import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { defaultCashFlowFilters } from '@/features/management-control/api'
import { CashFlowFiltersBar } from '@/features/management-control/cash-flow-filters-bar'
import { CashFlowPanel } from '@/features/management-control/cash-flow-panel'
import type { CashFlowFilters } from '@/features/management-control/cash-flow-types'

/**
 * Management control, cash flow forecast (spec 0270): filters kept in client state, then
 * the panel that reads the server. Gated by `management-control.view`.
 */
export default function ManagementControlCashFlowPage() {
  const { t } = useTranslation()
  const [filters, setFilters] = useState<CashFlowFilters>(defaultCashFlowFilters)

  return (
    <Can
      permission="management-control.view"
      fallback={<p className="text-sm text-muted-foreground">{t('managementControl.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-4">
        <PageHeader />
        <CashFlowFiltersBar filters={filters} onChange={setFilters} />
        <CashFlowPanel filters={filters} />
      </div>
    </Can>
  )
}
