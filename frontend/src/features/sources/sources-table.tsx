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
import { sourceColumnRenderers } from '@/features/sources/column-renderers'
import { deleteSource } from '@/features/sources/api'

/** Domain key used to mount the generic table for sources. */
const SOURCES_DOMAIN = 'sources'

/**
 * Thin Sources adapter over the generic table. It mounts `<TableView>` with
 * the `sources` domain, its custom cell renderers and a row-action handler,
 * and delegates the open mode (modal Sheet vs dedicated page) of view/edit/
 * create to `useModuleOpener`, resolved from the user's preference (spec
 * 0042). It still owns the delete flow (confirm + toast + grid refresh) and
 * refreshes the SSRM grid after every mutation. Permission gating is an
 * affordance only; the backend re-authorizes each call.
 */
export function SourcesTable() {
  const { t } = useTranslation()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  // After a modal create/edit succeeds the Sheet closes itself; the grid is
  // this adapter's to refresh. The detail query is invalidated inside
  // `SourceFormScreen`. Page mode never calls this.
  const onSaved = useCallback(() => refreshGrid(), [refreshGrid])

  const { openCreate, openView, openEdit, sheet } = useModuleOpener(SOURCES_DOMAIN, { onSaved })

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(Number(row.id))
      try {
        await deleteSource(Number(row.id))
        toast.success(t('sources.form.deleted'))
        refreshGrid()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        toast.error(
          status === 403 ? t('sources.form.deleteForbidden') : t('sources.form.deleteError'),
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
          <Can permission="sources.create">
            <Button onClick={openCreate}>
              <Plus aria-hidden="true" />
              {t('sources.form.newSource')}
            </Button>
          </Can>
        }
      />

      <TableView
        ref={tableRef}
        domain={SOURCES_DOMAIN}
        renderers={sourceColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      {sheet}

      <ResourceActivityDialog
        resource={SOURCES_DOMAIN}
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
