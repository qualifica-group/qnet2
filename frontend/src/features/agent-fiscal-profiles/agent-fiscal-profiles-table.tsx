import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { Plus } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { deleteAgentFiscalProfile } from '@/features/agent-fiscal-profiles/api'
import { agentFiscalProfileColumnRenderers } from '@/features/agent-fiscal-profiles/column-renderers'
import { AGENT_FISCAL_PROFILES_DOMAIN } from '@/features/agent-fiscal-profiles/types'

const FORBIDDEN_STATUS = 403

/**
 * Thin agent fiscal profiles adapter over the generic table (domain
 * `agent-fiscal-profiles`). Open mode of view/edit/create is delegated to
 * `useModuleOpener`; it owns the delete flow (confirm is asked by the table).
 */
export function AgentFiscalProfilesTable() {
  const { t } = useTranslation()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const { openCreate, openView, sheet } = useModuleOpener(AGENT_FISCAL_PROFILES_DOMAIN, { onSaved: refreshGrid })

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(Number(row.id))
      try {
        await deleteAgentFiscalProfile(Number(row.id))
        toast.success(t('agentFiscalProfiles.form.deleted'))
        refreshGrid()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        toast.error(
          status === FORBIDDEN_STATUS
            ? t('agentFiscalProfiles.form.deleteForbidden')
            : t('agentFiscalProfiles.form.deleteError'),
        )
      } finally {
        setDeletingId(null)
      }
    },
    [refreshGrid, t],
  )

  const handleAction: RowActionHandler = useCallback(
    (action: TableActionDefinition, row: TableRow) => {
      switch (action.key) {
        case 'view':
          openView(row)
          break
        case 'delete':
          void runDelete(row)
          break
        case 'activity':
          setActivityRow(row)
          break
        default:
          break
      }
    },
    [openView, runDelete],
  )

  const isBusy = useCallback((row: TableRow) => row.id === deletingId, [deletingId])

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Can permission="agent-fiscal-profiles.create">
            <Button onClick={openCreate}>
              <Plus aria-hidden="true" />
              {t('agentFiscalProfiles.form.newProfile')}
            </Button>
          </Can>
        }
      />

      <TableView
        ref={tableRef}
        domain={AGENT_FISCAL_PROFILES_DOMAIN}
        renderers={agentFiscalProfileColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      {sheet}

      <ResourceActivityDialog
        resource={AGENT_FISCAL_PROFILES_DOMAIN}
        row={activityRow}
        onOpenChange={(open) => (open ? undefined : setActivityRow(null))}
      />
    </div>
  )
}
