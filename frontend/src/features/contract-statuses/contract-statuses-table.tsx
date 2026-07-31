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
import type { ApiErrorResponse } from '@/api/types'
import { contractStatusColumnRenderers } from '@/features/contract-statuses/column-renderers'
import { deleteContractStatus } from '@/features/contract-statuses/api'
import { StatusReorderToggle } from '@/features/status-reorder/status-reorder-toggle'

/** Domain key used to mount the generic table for contract statuses. */
const CONTRACT_STATUSES_DOMAIN = 'contract-statuses'

/**
 * Thin Contract Statuses adapter over the generic table. It mounts
 * `<TableView>` with the `contract-statuses` domain, its custom cell
 * renderers and a row-action handler, and delegates the open mode (modal
 * Sheet vs dedicated page) of view/edit/create to `useModuleOpener`,
 * resolved from the user's preference (spec 0042). It still owns the delete
 * flow (confirming + running the delete mutation, surfacing the backend's
 * exact 409 message when the status is still referenced by a Contract) and
 * refreshing the SSRM grid after every mutation via the table's imperative
 * handle. Permission gating is an affordance only; the backend re-authorizes
 * each call.
 */
export function ContractStatusesTable() {
  const { t } = useTranslation()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const { openCreate, openView, openEdit, sheet } = useModuleOpener(CONTRACT_STATUSES_DOMAIN, {
    onSaved: refreshGrid,
  })

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(row.id)
      try {
        await deleteContractStatus(row.id)
        toast.success(t('contractStatuses.form.deleted'))
        refreshGrid()
      } catch (error) {
        if (!axios.isAxiosError<ApiErrorResponse>(error)) {
          toast.error(t('contractStatuses.form.deleteError'))
          return
        }
        const status = error.response?.status
        if (status === 403) {
          toast.error(t('contractStatuses.form.deleteForbidden'))
        } else if (status === 409) {
          // The status is still referenced by a Contract. Surface the backend's
          // own message rather than a generic one.
          toast.error(
            error.response?.data?.message ?? t('contractStatuses.form.deleteInUseFallback'),
          )
        } else {
          toast.error(t('contractStatuses.form.deleteError'))
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
        case 'edit':
          openEdit(row)
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
    [openView, openEdit, runDelete],
  )

  const isBusy = useCallback((row: TableRow) => row.id === deletingId, [deletingId])

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <>
            <StatusReorderToggle
              resource={CONTRACT_STATUSES_DOMAIN}
              permission="contract-statuses.update"
              labels={{
                openButton: t('contractStatuses.reorder.openButton'),
                title: t('contractStatuses.reorder.title'),
                subtitle: t('contractStatuses.reorder.subtitle'),
                dragHandleLabel: t('contractStatuses.reorder.dragHandleLabel'),
                loadError: t('contractStatuses.reorder.loadError'),
                saved: t('contractStatuses.reorder.saved'),
                forbidden: t('contractStatuses.reorder.forbidden'),
                genericError: t('contractStatuses.reorder.genericError'),
              }}
              onReordered={refreshGrid}
            />
            <Can permission="contract-statuses.create">
              <Button onClick={openCreate}>
                <Plus aria-hidden="true" />
                {t('contractStatuses.form.newContractStatus')}
              </Button>
            </Can>
          </>
        }
      />

      <TableView
        ref={tableRef}
        domain={CONTRACT_STATUSES_DOMAIN}
        renderers={contractStatusColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      {sheet}

      <ResourceActivityDialog
        resource={CONTRACT_STATUSES_DOMAIN}
        row={activityRow}
        onOpenChange={(open) => {
          if (!open) {
            setActivityRow(null)
          }
        }}
      />
    </div>
  )
}
