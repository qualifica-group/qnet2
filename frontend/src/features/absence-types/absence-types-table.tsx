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
import { deleteAbsenceType } from '@/features/absence-types/api'
import { ABSENCE_TYPES_DOMAIN } from '@/features/absence-types/types'

const FORBIDDEN_STATUS = 403
const CONFLICT_STATUS = 409

/**
 * Thin absence-types adapter over the generic table (domain `absence-types`). Open mode of
 * view/create is delegated to `useModuleOpener`; it owns the delete flow (confirm is asked by
 * the table; 409 = a request already uses the type).
 */
export function AbsenceTypesTable() {
  const { t } = useTranslation()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const { openCreate, openView, sheet } = useModuleOpener(ABSENCE_TYPES_DOMAIN, { onSaved: refreshGrid })

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(Number(row.id))
      try {
        await deleteAbsenceType(Number(row.id))
        toast.success(t('absenceTypes.form.deleted'))
        refreshGrid()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        if (status === FORBIDDEN_STATUS) {
          toast.error(t('absenceTypes.form.deleteForbidden'))
        } else if (status === CONFLICT_STATUS) {
          toast.error(t('absenceTypes.form.deleteInUse'))
        } else {
          toast.error(t('absenceTypes.form.deleteError'))
        }
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
          <Can permission="absence-types.create">
            <Button onClick={openCreate}>
              <Plus aria-hidden="true" />
              {t('absenceTypes.form.newAbsenceType')}
            </Button>
          </Can>
        }
      />

      <TableView ref={tableRef} domain={ABSENCE_TYPES_DOMAIN} onAction={handleAction} isBusy={isBusy} />

      {sheet}

      <ResourceActivityDialog
        resource={ABSENCE_TYPES_DOMAIN}
        row={activityRow}
        onOpenChange={(open) => (open ? undefined : setActivityRow(null))}
      />
    </div>
  )
}
