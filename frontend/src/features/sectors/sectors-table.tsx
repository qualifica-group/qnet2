import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
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
import { sectorColumnRenderers } from '@/features/sectors/column-renderers'
import { deleteSector } from '@/features/sectors/api'
import { sectorKeys } from '@/features/sectors/query-keys'

/** Domain key used to mount the generic table for sectors. */
const SECTORS_DOMAIN = 'sectors'

/**
 * Thin Sectors adapter over the generic table. It mounts `<TableView>` with
 * the `sectors` domain, its custom cell renderers and a row-action handler,
 * and delegates the open mode (modal Sheet vs dedicated page) of view/edit/
 * create to `useModuleOpener`, resolved from the user's preference (spec
 * 0042). It still owns the delete flow (confirm + toast + running the
 * restrictive-delete 409 when a sector still has children) and refreshes
 * both the SSRM grid and the parent-picker tree after every mutation.
 */
export function SectorsTable() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  // After a modal create/edit succeeds the Sheet closes itself; the grid is
  // this adapter's to refresh. The detail/tree queries are invalidated
  // inside `SectorFormScreen`. Page mode never calls this.
  const onSaved = useCallback(() => refreshGrid(), [refreshGrid])

  const { openCreate, openView, openEdit, sheet } = useModuleOpener(SECTORS_DOMAIN, { onSaved })

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(Number(row.id))
      try {
        await deleteSector(Number(row.id))
        toast.success(t('sectors.form.deleted'))
        refreshGrid()
        void queryClient.invalidateQueries({ queryKey: sectorKeys.tree })
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        if (status === 403) {
          toast.error(t('sectors.form.deleteForbidden'))
        } else if (status === 409) {
          toast.error(t('sectors.form.deleteInUse'))
        } else {
          toast.error(t('sectors.form.deleteError'))
        }
      } finally {
        setDeletingId(null)
      }
    },
    [refreshGrid, queryClient, t],
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
          <Can permission="sectors.create">
            <Button onClick={openCreate}>
              <Plus aria-hidden="true" />
              {t('sectors.form.newSector')}
            </Button>
          </Can>
        }
      />

      <TableView
        ref={tableRef}
        domain={SECTORS_DOMAIN}
        renderers={sectorColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      {sheet}

      <ResourceActivityDialog
        resource={SECTORS_DOMAIN}
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
