import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { defaultAccountFlowsFilters } from '@/features/management-control/api'
import { AccountsFiltersBar } from '@/features/management-control/accounts-filters-bar'
import { AccountsPanel } from '@/features/management-control/accounts-panel'
import type { AccountFlowsFilters } from '@/features/management-control/accounts-types'

/**
 * Management control, accounts (spec 0271): filters kept in client state, then
 * the panel that reads the server. Gated by `management-control.view`.
 */
export default function ManagementControlAccountsPage() {
  const { t } = useTranslation()
  const [filters, setFilters] = useState<AccountFlowsFilters>(defaultAccountFlowsFilters)

  return (
    <Can
      permission="management-control.view"
      fallback={<p className="text-sm text-muted-foreground">{t('managementControl.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-4">
        <PageHeader title={t('managementControl.accounts.title')} />
        <AccountsFiltersBar filters={filters} onChange={setFilters} />
        <AccountsPanel filters={filters} />
      </div>
    </Can>
  )
}
