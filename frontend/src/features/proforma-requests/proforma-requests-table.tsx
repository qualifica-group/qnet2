import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { FilePlus2, MessagesSquare } from 'lucide-react'
import { toast } from 'sonner'
import { PageHeader } from '@/components/page-header'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { NotesDialog } from '@/features/notes/notes-dialog'
import type { ActionIconMap } from '@/features/table/action-icon-map'
import type { RowActionHandler } from '@/features/table/row-actions'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { deleteProformaRequest, PROFORMA_REQUESTS_DOMAIN } from '@/features/proforma-requests/api'
import { proformaRequestColumnRenderers } from '@/features/proforma-requests/column-renderers'
import { InvoiceEditorDialog } from '@/features/invoices/invoice-editor-dialog'
import { INVOICE_ACTION_KEY, isRequestIssued, useInvoiceActionState } from '@/features/proforma-requests/invoice-row-action'
import { ProformaRequestEditScreen } from '@/features/proforma-requests/proforma-request-edit-screen'

/**
 * Initial filter of the grid: only requests not yet issued (spec 0193 D-12).
 * Module-level on purpose: `TableView` memoizes on its identity. The user
 * removes it from the status column filter / the "clear filters" chip to see
 * the issued ones too.
 */
const PENDING_ONLY_FILTER: Record<string, unknown> = {
  status: { filterType: 'set', values: ['pending'] },
}

/** The backend fixes the action icon keys: notes = 'messages-square', invoice = 'file-plus-2'. */
const PROFORMA_ACTION_ICONS: ActionIconMap = { 'messages-square': MessagesSquare, 'file-plus-2': FilePlus2 }

/**
 * Thin Proforma requests adapter over the generic table. No create action:
 * requests are only generated from a work order. Owns the delete flow, the
 * note-edit dialog (`update` row action), the CRM notes dialog and the
 * activity log; view opens through `useModuleOpener`. The backend
 * re-authorizes every call.
 */
export function ProformaRequestsTable() {
  const { t } = useTranslation()
  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [editId, setEditId] = useState<number | null>(null)
  const [notesId, setNotesId] = useState<number | null>(null)
  const [invoiceRequestId, setInvoiceRequestId] = useState<number | null>(null)
  const resolveActionState = useInvoiceActionState()
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const { openView, sheet } = useModuleOpener(PROFORMA_REQUESTS_DOMAIN, { onSaved: refreshGrid })

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(Number(row.id))
      try {
        await deleteProformaRequest(Number(row.id))
        toast.success(t('proformaRequests.form.deleted'))
        refreshGrid()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        toast.error(t(status === 403 ? 'proformaRequests.form.deleteForbidden' : 'proformaRequests.form.deleteError'))
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
        case 'update':
          setEditId(Number(row.id))
          break
        case 'delete':
          void runDelete(row)
          break
        case 'notes':
          setNotesId(Number(row.id))
          break
        case INVOICE_ACTION_KEY:
          // Issued requests are inert (the button is disabled too): guards a stale row.
          if (!isRequestIssued(row)) {
            setInvoiceRequestId(Number(row.id))
          }
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

  const closeEdit = useCallback(() => setEditId(null), [])
  const closeInvoice = useCallback(() => setInvoiceRequestId(null), [])
  const handleInvoiced = useCallback(() => {
    setInvoiceRequestId(null)
    refreshGrid()
  }, [refreshGrid])
  const handleEdited = useCallback(() => {
    setEditId(null)
    refreshGrid()
  }, [refreshGrid])

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader />

      <TableView
        ref={tableRef}
        domain={PROFORMA_REQUESTS_DOMAIN}
        renderers={proformaRequestColumnRenderers}
        defaultFilterModel={PENDING_ONLY_FILTER}
        iconMap={PROFORMA_ACTION_ICONS}
        resolveActionState={resolveActionState}
        onAction={handleAction}
        isBusy={isBusy}
      />

      {sheet}

      <Dialog open={editId !== null} onOpenChange={(open) => (open ? undefined : closeEdit())}>
        <DialogContent size="sm" className="gap-0 p-0">
          <DialogHeader className="border-b px-4 py-3 text-left">
            <DialogTitle className="text-base">{t('proformaRequests.form.editTitle')}</DialogTitle>
            <DialogDescription className="text-xs">{t('proformaRequests.form.editSubtitle')}</DialogDescription>
          </DialogHeader>
          {editId !== null ? (
            <ProformaRequestEditScreen id={editId} onSuccess={handleEdited} onCancel={closeEdit} />
          ) : null}
        </DialogContent>
      </Dialog>

      {invoiceRequestId !== null ? (
        <InvoiceEditorDialog
          mode="create"
          proformaRequestId={invoiceRequestId}
          open
          onClose={closeInvoice}
          onSaved={handleInvoiced}
        />
      ) : null}

      <NotesDialog
        entityType={PROFORMA_REQUESTS_DOMAIN}
        entityId={notesId}
        onOpenChange={(open) => (open ? undefined : setNotesId(null))}
      />

      <ResourceActivityDialog
        resource={PROFORMA_REQUESTS_DOMAIN}
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
