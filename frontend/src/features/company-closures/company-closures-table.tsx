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
import { deleteCompanyClosure } from '@/features/company-closures/api'
import { COMPANY_CLOSURES_DOMAIN } from '@/features/company-closures/types'
import { forbiddenMessage } from '@/lib/forbidden-message'

const CONFLICT_STATUS = 409

/**
 * Thin company-closures adapter over the generic table (domain `company-closures`). Open mode of
 * view/create is delegated to `useModuleOpener`; it owns the delete flow (confirm is asked by the
 * table; 409 = the closure already started).
 */
export function CompanyClosuresTable() {
  const { t, i18n } = useTranslation()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const { openCreate, openView, sheet } = useModuleOpener(COMPANY_CLOSURES_DOMAIN, { onSaved: refreshGrid })

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(Number(row.id))
      try {
        await deleteCompanyClosure(Number(row.id))
        toast.success(t('companyClosures.form.deleted'))
        refreshGrid()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        const forbidden = forbiddenMessage(error, i18n)
        if (forbidden !== null) {
          toast.error(forbidden)
        } else if (status === CONFLICT_STATUS) {
          toast.error(t('companyClosures.form.deleteStarted'))
        } else {
          toast.error(t('companyClosures.form.deleteError'))
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
          <Can permission="company-closures.create">
            <Button onClick={openCreate}>
              <Plus aria-hidden="true" />
              {t('companyClosures.form.newCompanyClosure')}
            </Button>
          </Can>
        }
      />

      <TableView ref={tableRef} domain={COMPANY_CLOSURES_DOMAIN} onAction={handleAction} isBusy={isBusy} />

      {sheet}

      <ResourceActivityDialog
        resource={COMPANY_CLOSURES_DOMAIN}
        row={activityRow}
        onOpenChange={(open) => (open ? undefined : setActivityRow(null))}
      />
    </div>
  )
}
