import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { defaultAgentsFilters } from '@/features/management-control/api'
import { AgentsFiltersBar } from '@/features/management-control/agents-filters-bar'
import { AgentsPanel } from '@/features/management-control/agents-panel'
import type { AgentsFilters } from '@/features/management-control/agents-types'

/**
 * Management control, agents (spec 0272): filters kept in client state, then
 * the panel that reads the server. Gated by `management-control.view`.
 */
export default function ManagementControlAgentsPage() {
  const { t } = useTranslation()
  const [filters, setFilters] = useState<AgentsFilters>(defaultAgentsFilters)

  return (
    <Can
      permission="management-control.view"
      fallback={<p className="text-sm text-muted-foreground">{t('managementControl.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-4">
        <PageHeader />
        <AgentsFiltersBar filters={filters} onChange={setFilters} />
        <AgentsPanel filters={filters} />
      </div>
    </Can>
  )
}
