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
import { documentLayoutColumnRenderers } from '@/features/document-layouts/column-renderers'
import { deleteDocumentLayout } from '@/features/document-layouts/api'

/** Domain key used to mount the generic table for document layouts. */
const DOCUMENT_LAYOUTS_DOMAIN = 'document-layouts'

/**
 * Thin Document Layouts adapter over the generic table. It mounts
 * `<TableView>` with the `document-layouts` domain, its custom cell
 * renderers and a row-action handler, and delegates the open mode (a
 * dedicated page by default, D-9) of view/edit/create to `useModuleOpener`.
 * It still owns the delete flow (confirming + running the delete mutation)
 * and refreshing the SSRM grid after every mutation via the table's
 * imperative handle. A 422 surfaces the D-7 "predefined layout" guard
 * message straight from the backend (AC-132) instead of a generic string —
 * `default_cannot_be_deleted` is a business rule explained server-side, not
 * a shape the frontend should paraphrase. Permission gating is an affordance
 * only; the backend re-authorizes each call.
 */
export function DocumentLayoutsTable() {
  const { t } = useTranslation()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const { openCreate, openView, openEdit, sheet } = useModuleOpener(DOCUMENT_LAYOUTS_DOMAIN, {
    onSaved: refreshGrid,
  })

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(row.id)
      try {
        await deleteDocumentLayout(row.id)
        toast.success(t('documentLayouts.form.deleted'))
        refreshGrid()
      } catch (error) {
        if (!axios.isAxiosError<ApiErrorResponse>(error)) {
          toast.error(t('documentLayouts.form.deleteError'))
          return
        }
        const status = error.response?.status
        if (status === 403) {
          toast.error(t('documentLayouts.form.deleteForbidden'))
        } else if (status === 422) {
          toast.error(error.response?.data?.message ?? t('documentLayouts.form.deleteError'))
        } else {
          toast.error(t('documentLayouts.form.deleteError'))
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
          <Can permission="document-layouts.create">
            <Button onClick={openCreate}>
              <Plus aria-hidden="true" />
              {t('documentLayouts.form.newDocumentLayout')}
            </Button>
          </Can>
        }
      />

      <TableView
        ref={tableRef}
        domain={DOCUMENT_LAYOUTS_DOMAIN}
        renderers={documentLayoutColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      {sheet}

      <ResourceActivityDialog
        resource={DOCUMENT_LAYOUTS_DOMAIN}
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
