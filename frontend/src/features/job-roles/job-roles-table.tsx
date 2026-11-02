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
import { deleteJobRole } from '@/features/job-roles/api'
import { JOB_ROLES_DOMAIN } from '@/features/job-roles/types'
import { forbiddenMessage } from '@/lib/forbidden-message'

const CONFLICT_STATUS = 409

/**
 * Thin job-roles adapter over the generic table (domain `job-roles`). Open
 * mode of view/create is delegated to `useModuleOpener`; it owns the delete
 * flow (confirm is asked by the table; 409 = the job role is assigned to
 * people).
 */
export function JobRolesTable() {
  const { t, i18n } = useTranslation()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const { openCreate, openView, sheet } = useModuleOpener(JOB_ROLES_DOMAIN, { onSaved: refreshGrid })

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(Number(row.id))
      try {
        await deleteJobRole(Number(row.id))
        toast.success(t('jobRoles.form.deleted'))
        refreshGrid()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        const forbidden = forbiddenMessage(error, i18n)
        if (forbidden !== null) {
          toast.error(forbidden)
        } else if (status === CONFLICT_STATUS) {
          toast.error(t('jobRoles.form.deleteInUse'))
        } else {
          toast.error(t('jobRoles.form.deleteError'))
        }
      } finally {
        setDeletingId(null)
      }
    },
    [refreshGrid, t, i18n],
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
          <Can permission="job-roles.create">
            <Button onClick={openCreate}>
              <Plus aria-hidden="true" />
              {t('jobRoles.form.newJobRole')}
            </Button>
          </Can>
        }
      />

      <TableView ref={tableRef} domain={JOB_ROLES_DOMAIN} onAction={handleAction} isBusy={isBusy} />

      {sheet}

      <ResourceActivityDialog
        resource={JOB_ROLES_DOMAIN}
        row={activityRow}
        onOpenChange={(open) => (open ? undefined : setActivityRow(null))}
      />
    </div>
  )
}
