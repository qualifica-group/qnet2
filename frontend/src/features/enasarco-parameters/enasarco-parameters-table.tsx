import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { Plus } from 'lucide-react'
import { toast } from 'sonner'
import type { ApiErrorResponse } from '@/api/types'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { deleteEnasarcoParameter } from '@/features/enasarco-parameters/api'
import { enasarcoParameterColumnRenderers } from '@/features/enasarco-parameters/column-renderers'
import { ENASARCO_PARAMETERS_DOMAIN } from '@/features/enasarco-parameters/types'

const FORBIDDEN_STATUS = 403
const CONFLICT_STATUS = 409

/** Maps a failed DELETE to a toast: 409 (year used by an issued settlement) shows the server message. */
function deleteErrorMessage(error: unknown, t: (key: string) => string): string {
  if (!axios.isAxiosError<ApiErrorResponse>(error)) {
    return t('enasarcoParameters.form.deleteError')
  }
  switch (error.response?.status) {
    case FORBIDDEN_STATUS:
      return t('enasarcoParameters.form.deleteForbidden')
    case CONFLICT_STATUS:
      return error.response.data?.message ?? t('enasarcoParameters.form.deleteInUse')
    default:
      return t('enasarcoParameters.form.deleteError')
  }
}

/**
 * Thin ENASARCO parameters adapter over the generic table (domain
 * `enasarco-parameters`). Open mode of view/edit/create is delegated to
 * `useModuleOpener`; it owns the delete flow (confirm is asked by the table).
 */
export function EnasarcoParametersTable() {
  const { t } = useTranslation()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const { openCreate, openView, sheet } = useModuleOpener(ENASARCO_PARAMETERS_DOMAIN, { onSaved: refreshGrid })

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(Number(row.id))
      try {
        await deleteEnasarcoParameter(Number(row.id))
        toast.success(t('enasarcoParameters.form.deleted'))
        refreshGrid()
      } catch (error) {
        toast.error(deleteErrorMessage(error, t))
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
          <Can permission="enasarco-parameters.create">
            <Button onClick={openCreate}>
              <Plus aria-hidden="true" />
              {t('enasarcoParameters.form.newParameter')}
            </Button>
          </Can>
        }
      />

      <TableView
        ref={tableRef}
        domain={ENASARCO_PARAMETERS_DOMAIN}
        renderers={enasarcoParameterColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      {sheet}

      <ResourceActivityDialog
        resource={ENASARCO_PARAMETERS_DOMAIN}
        row={activityRow}
        onOpenChange={(open) => (open ? undefined : setActivityRow(null))}
      />
    </div>
  )
}
