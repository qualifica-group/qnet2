import { useCallback, useMemo, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation } from '@tanstack/react-query'
import axios from 'axios'
import { ArrowRightLeft, MessageSquare, Paperclip, Plus, UserCog } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { DocumentsDialog } from '@/features/attachments/documents-dialog'
import { Can } from '@/features/auth/can'
import { useAbilities } from '@/features/auth/use-abilities'
import {
  AssignOperatorsDialog,
  type AssignOperatorsDialogInput,
  type AssignOperatorsDialogSite,
} from '@/features/leads/assign-operators-dialog'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { NotesDialog } from '@/features/notes/notes-dialog'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { ActionIconMap } from '@/features/table/action-icon-map'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { BulkAction, TableSelection } from '@/features/table/use-bulk-actions-slot'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { OPPORTUNITY_ATTACHABLE_ALIAS } from '@/features/opportunities/api'
import { assignRequestOperators, deleteRequest, transferRequests } from '@/features/request-management/api'
import { requestManagementColumnRenderers } from '@/features/request-management/column-renderers'
import { RequestManagementCategoryTabs } from '@/features/request-management/request-management-category-tabs'
import { useRequestManagementCategoryTab } from '@/features/request-management/use-request-management-category-tab'
import { REQUEST_MANAGEMENT_DOMAIN, type TransferRequestsPayload } from '@/features/request-management/types'

/**
 * Domain icon overrides for the `documents`/`notes`/`transfer-contact` row
 * actions: the backend action catalog fixes their icon keys as
 * 'paperclip'/'message-square'/'arrow-right-left', absent from the shared
 * defaults in `action-icon-map.ts` (spec 0079). Hoisted at module level (not
 * inline in JSX) so its identity stays stable across renders.
 */
const REQUEST_MANAGEMENT_ACTION_ICONS: ActionIconMap = {
  paperclip: Paperclip,
  'message-square': MessageSquare,
  'arrow-right-left': ArrowRightLeft,
}

/**
 * The Sede to precompile in the "Assegna operatori" popup: present only when
 * every selected row's `operational_site` (the `{id, label}` shape the
 * definition projects onto the grid row, or null) shares one non-null id —
 * the same rule the Lead table applies.
 */
function resolveSharedOperationalSite(rows: TableRow[]): AssignOperatorsDialogSite | null {
  const [first, ...rest] = rows.map(
    (row) => row.operational_site as AssignOperatorsDialogSite | null,
  )
  if (!first) {
    return null
  }
  return rest.every((site) => site?.id === first.id) ? first : null
}

/**
 * Thin Request Management adapter over the generic table (spec 0049): an
 * OPERATIVE view over the same Opportunity rows (D-1, no new entity, no
 * duplication). Mounts `<TableView>` with the `request-management` domain
 * and its status-badge renderers, and delegates the "Lavora" row action
 * (`view`) to `useModuleOpener`, resolved from the user's open-mode
 * preference (spec 0042): modal mounts the work panel in a Sheet, page mode
 * navigates to `/request-management/:id`. The `documents` row action opens the
 * shared `DocumentsDialog` on the same polymorphic owner the opportunities
 * module uses (the row IS the Opportunity), gated server-side by this module's
 * OWN `request-management.viewDocuments` (D-2). The `notes` row action opens
 * the agnostic `NotesDialog` on the same row (spec 0052), gated server-side by
 * the notes feature's own hybrid authorization (D-6) — this module only wires
 * the `entityType`/`entityId` pair. The `activity` row action — declared last
 * in the catalog, so the shared inline limit pushes it into the three-dots
 * overflow — opens `ResourceActivityDialog` on this module's OWN activity
 * resource key (`request-management`, gated server-side by
 * `request-management.viewActivity` + the GA2 scope).
 *
 * Selection (user directive 2026-07-23): this module owns bulk flows, as the
 * Lead table does — the generic "elimina selezionati" (switched on by the
 * `delete` action being in the catalog), the shared "Assegna operatori"
 * popup, and — spec 0079 — "Trasferisci contatto", which reuses the SAME
 * `AssignOperatorsDialog` component with `lockedMode="single"` (no mode
 * step, Operatore always shown) behind its own mutation
 * (`POST /request-management/transfer`). All three are gated by this
 * module's OWN permissions; the checkbox column exists BECAUSE of them, the
 * generic table never shows it without a reachable bulk action. The
 * `transfer-contact` row action opens the identical dialog on a
 * one-element selection. Create (spec 0057) is its own affordance, gated by
 * this module's OWN `request-management.create` — the rest of the CRUD
 * (update) still stays on the work panel, never `opportunities.*`.
 */
export function RequestManagementTable() {
  const { t } = useTranslation()
  const { can } = useAbilities()

  const { categories, selectedCategoryId, setCategoryId } = useRequestManagementCategoryTab()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])
  const { openCreate, openView, sheet } = useModuleOpener(REQUEST_MANAGEMENT_DOMAIN, {
    onSaved: refreshGrid,
  })

  const [documentsRowId, setDocumentsRowId] = useState<number | null>(null)
  const [notesRowId, setNotesRowId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)
  const [deletingId, setDeletingId] = useState<number | null>(null)

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(row.id)
      try {
        await deleteRequest(row.id)
        toast.success(t('requestManagement.delete.success'))
        refreshGrid()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        toast.error(
          status === 403
            ? t('requestManagement.delete.forbidden')
            : t('requestManagement.delete.error'),
        )
      } finally {
        setDeletingId(null)
      }
    },
    [refreshGrid, t],
  )

  // Transfer to another Sede + Operatore (spec 0079): row and bulk share ONE
  // dialog/mutation — a row transfer is just a one-element selection, so
  // `handleAction` below reuses the same `openTransferDialog`.
  const [transferOpen, setTransferOpen] = useState(false)
  const [transferIds, setTransferIds] = useState<number[]>([])
  const [transferDefaultSite, setTransferDefaultSite] = useState<AssignOperatorsDialogSite | null>(null)
  // Same double gate as `assignOperators` (:166 above): the popup writes the
  // Sede AND the Operatore.
  const canTransferContact = can('request-management.update') && can('request-management.transferContact')

  const transferMutation = useMutation({
    mutationFn: (payload: TransferRequestsPayload) => transferRequests(payload),
    onSuccess: (result) => {
      toast.success(t('requestManagement.transfer.success', { count: result.transferred }))
      refreshGrid()
      tableRef.current?.clearSelection()
    },
  })

  const handleTransfer = useCallback(
    async (input: AssignOperatorsDialogInput) => {
      try {
        // `lockedMode="single"` guarantees `operator_id` is always picked.
        await transferMutation.mutateAsync({
          request_ids: transferIds,
          operational_site_id: input.operational_site_id,
          operator_id: input.operator_id as number,
        })
      } catch (error) {
        toast.error(t('requestManagement.transfer.errors.generic'))
        throw error
      }
    },
    [transferMutation, transferIds, t],
  )

  const openTransferDialog = useCallback((selection: TableSelection) => {
    setTransferIds(selection.ids)
    setTransferDefaultSite(resolveSharedOperationalSite(selection.rows))
    setTransferOpen(true)
  }, [])

  const handleAction: RowActionHandler = useCallback(
    (action: TableActionDefinition, row: TableRow) => {
      switch (action.key) {
        case 'view':
          openView(row)
          break
        case 'documents':
          setDocumentsRowId(row.id)
          break
        case 'notes':
          setNotesRowId(row.id)
          break
        case 'delete':
          void runDelete(row)
          break
        case 'activity':
          setActivityRow(row)
          break
        case 'transfer-contact':
          openTransferDialog({ ids: [row.id], rows: [row] })
          break
        default:
          break
      }
    },
    [openView, runDelete, openTransferDialog],
  )

  const isBusy = useCallback((row: TableRow) => row.id === deletingId, [deletingId])

  // Bulk operator assignment: the shared popup collects Sede + mode +
  // operator; this adapter owns the selection, the mutation and its feedback.
  const [assignOpen, setAssignOpen] = useState(false)
  const [assignIds, setAssignIds] = useState<number[]>([])
  const [assignDefaultSite, setAssignDefaultSite] = useState<AssignOperatorsDialogSite | null>(null)
  // `assignOperator` on top of `update` (user directive 2026-08-03): the popup
  // writes the Sede AND the Operatore, the two attribution dimensions a role
  // may be restricted on — the same pair the store endpoint and the bulk
  // endpoint now both gate on this ability.
  const canAssignOperators = can('request-management.update') && can('request-management.assignOperator')

  const assignMutation = useMutation({
    mutationFn: assignRequestOperators,
    onSuccess: (result) => {
      toast.success(t('requestManagement.assign.success', { count: result.assigned }))
      refreshGrid()
      tableRef.current?.clearSelection()
    },
  })

  const handleAssign = useCallback(
    async (input: AssignOperatorsDialogInput) => {
      try {
        await assignMutation.mutateAsync({ request_ids: assignIds, ...input })
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        toast.error(
          status === 422 && input.mode === 'balanced'
            ? t('requestManagement.assign.errors.noOperators')
            : t('requestManagement.assign.errors.generic'),
        )
        throw error
      }
    },
    [assignMutation, assignIds, t],
  )

  const openAssignDialog = useCallback((selection: TableSelection) => {
    setAssignIds(selection.ids)
    setAssignDefaultSite(resolveSharedOperationalSite(selection.rows))
    setAssignOpen(true)
  }, [])

  // Surfaced inside the generic table's single "Actions" dropdown, alongside
  // the built-in "elimina selezionati". Each entry gated on its own ability;
  // `undefined` (not a function returning an empty array) when neither is
  // reachable, so the checkbox column stays off entirely.
  const getBulkActions =
    canAssignOperators || canTransferContact
      ? (selection: TableSelection): BulkAction[] => [
          ...(canAssignOperators
            ? [
                {
                  key: 'assign-operators',
                  label: t('requestManagement.assign.tableButton'),
                  icon: UserCog,
                  onSelect: () => openAssignDialog(selection),
                },
              ]
            : []),
          ...(canTransferContact
            ? [
                {
                  key: 'transfer-contact',
                  label: t('actions.transferContact'),
                  icon: ArrowRightLeft,
                  onSelect: () => openTransferDialog(selection),
                },
              ]
            : []),
        ]
      : undefined

  // The two entity-specific sentences of the shared popup, which otherwise
  // names leads.
  const assignCopy = useMemo(
    () => ({
      description: t('requestManagement.assign.description', { count: assignIds.length }),
      modeHints: {
        balanced: t('requestManagement.assign.actions.balancedHint'),
        single: t('requestManagement.assign.actions.singleHint'),
      },
    }),
    [t, assignIds.length],
  )

  // The locked-mode popup reads `title`/`description` (no mode hints — Step 1
  // never renders).
  const transferCopy = useMemo(
    () => ({
      title: t('requestManagement.transfer.title'),
      description: t('requestManagement.transfer.description', { count: transferIds.length }),
      modeHints: { balanced: '', single: '' },
    }),
    [t, transferIds.length],
  )

  // Documents are edited from inside the dialog (upload/delete); refresh the
  // grid on close so the row's `documents_count` badge reflects the change.
  const handleDocumentsOpenChange = useCallback(
    (open: boolean) => {
      if (!open) {
        setDocumentsRowId(null)
        refreshGrid()
      }
    },
    [refreshGrid],
  )

  // Notes are added/deleted from inside the dialog; refresh the grid on close
  // so the row's `notes_count` badge reflects the change (mirrors documents).
  const handleNotesOpenChange = useCallback(
    (open: boolean) => {
      if (!open) {
        setNotesRowId(null)
        refreshGrid()
      }
    },
    [refreshGrid],
  )

  // The activity log is read-only: no refresh needed on close, only the row
  // whose timeline is shown is cleared.
  const handleActivityOpenChange = useCallback((open: boolean) => {
    if (!open) {
      setActivityRow(null)
    }
  }, [])

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Can permission="request-management.create">
            <Button onClick={openCreate}>
              <Plus aria-hidden="true" />
              {t('requestManagement.form.newRequest')}
            </Button>
          </Can>
        }
      />

      <RequestManagementCategoryTabs
        categories={categories}
        selectedCategoryId={selectedCategoryId}
        onSelect={setCategoryId}
      />

      <TableView
        // Keyed by the selection (D-4): switching tabs remounts the whole
        // table so every client-side state (search, filters, layout) restarts
        // from the freshly-scoped config's defaults instead of carrying over.
        key={selectedCategoryId ?? 'all'}
        ref={tableRef}
        domain={REQUEST_MANAGEMENT_DOMAIN}
        scope={selectedCategoryId !== null ? { productCategoryId: selectedCategoryId } : undefined}
        renderers={requestManagementColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
        iconMap={REQUEST_MANAGEMENT_ACTION_ICONS}
        getBulkActions={getBulkActions}
      />

      {sheet}

      <AssignOperatorsDialog
        open={assignOpen}
        onOpenChange={setAssignOpen}
        selectionCount={assignIds.length}
        defaultSite={assignDefaultSite}
        copy={assignCopy}
        onAssign={handleAssign}
      />

      <AssignOperatorsDialog
        open={transferOpen}
        onOpenChange={setTransferOpen}
        selectionCount={transferIds.length}
        defaultSite={transferDefaultSite}
        copy={transferCopy}
        lockedMode="single"
        onAssign={handleTransfer}
      />

      <DocumentsDialog
        resource={OPPORTUNITY_ATTACHABLE_ALIAS}
        id={documentsRowId}
        onOpenChange={handleDocumentsOpenChange}
      />

      <NotesDialog
        entityType={REQUEST_MANAGEMENT_DOMAIN}
        entityId={notesRowId}
        onOpenChange={handleNotesOpenChange}
      />

      <ResourceActivityDialog
        resource={REQUEST_MANAGEMENT_DOMAIN}
        row={activityRow}
        onOpenChange={handleActivityOpenChange}
      />
    </div>
  )
}
