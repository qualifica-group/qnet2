import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { AgentFiscalProfilesTable } from '@/features/agent-fiscal-profiles/agent-fiscal-profiles-table'

/**
 * Agent fiscal profiles page (Accounting > Agent commissions). Light
 * composition only: gates access with `agent-fiscal-profiles.viewAny` (what
 * the table endpoint enforces) and mounts the thin adapter over the generic
 * table (`domain="agent-fiscal-profiles"`).
 */
export default function AgentFiscalProfilesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="agent-fiscal-profiles.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('agentFiscalProfiles.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <AgentFiscalProfilesTable />
      </div>
    </Can>
  )
}
