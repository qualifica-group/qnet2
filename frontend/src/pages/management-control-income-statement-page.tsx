import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { defaultIncomeStatementFilters } from '@/features/management-control/api'
import { IncomeStatementFiltersBar } from '@/features/management-control/income-statement-filters-bar'
import { IncomeStatementPanel } from '@/features/management-control/income-statement-panel'
import type { IncomeStatementFilters } from '@/features/management-control/types'

/**
 * Management control, income statement (spec 0268): common filters kept in client
 * state, then the panel that reads the server. Gated by `management-control.view`.
 */
export default function ManagementControlIncomeStatementPage() {
  const { t } = useTranslation()
  const [filters, setFilters] = useState<IncomeStatementFilters>(defaultIncomeStatementFilters)

  return (
    <Can
      permission="management-control.view"
      fallback={<p className="text-sm text-muted-foreground">{t('managementControl.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-4">
        <PageHeader title={t('managementControl.title')} />
        <IncomeStatementFiltersBar filters={filters} onChange={setFilters} />
        <IncomeStatementPanel filters={filters} />
      </div>
    </Can>
  )
}
