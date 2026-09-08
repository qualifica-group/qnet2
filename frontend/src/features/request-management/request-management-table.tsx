import { useCallback, useMemo, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation } from '@tanstack/react-query'
import axios from 'axios'
import { ArrowRightLeft, GraduationCap, MessagesSquare, Paperclip, Plus, UserCog } from 'lucide-react'
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
import { AssignManagerGa3Dialog } from '@/features/request-management/assign-manager-ga3-dialog'
import { requestManagementColumnRenderers } from '@/features/request-management/column-renderers'
import { OfferLinesDialogProvider } from '@/features/request-management/offer-lines-dialog'
import { RequestDashboardPanel } from '@/features/request-management/request-dashboard-panel'
import { RequestDashboardToggle } from '@/features/request-management/request-dashboard-toggle'
import { RequestManagementCategoryTabs } from '@/features/request-management/request-management-category-tabs'
import { useRequestManagementCategoryTab } from '@/features/request-management/use-request-management-category-tab'
import { useRequestManagerGa3Assignment } from '@/features/request-management/use-request-manager-ga3-assignment'
import type { TransferRequestsPayload } from '@/features/request-management/request-write-types'
import { REQUEST_MANAGEMENT_DOMAIN } from '@/features/request-management/types'
import { useStatsPanel } from '@/features/stats/use-stats-panel'

/**
 * Domain icon overrides for the `documents`/`notes`/`transfer-contact` row
 * actions: the backend action catalog fixes their icon keys as
 * 'paperclip'/'messages-square'/'arrow-right-left', absent from the shared
 * defaults in `action-icon-map.ts` (spec 0079). Hoisted at module level (not
 * inline in JSX) so its identity stays stable across renders.
 */
const REQUEST_MANAGEMENT_ACTION_ICONS: ActionIconMap = {
  paperclip: Paperclip,
  'messages-square': MessagesSquare,
  'arrow-right-left': ArrowRightLeft,
}

/**
 * Cio' che il dialog delle note deve sapere della riga aperta (direttiva
 * utente 2026-08-07): la nota vive sul thread dell'Opportunita'
 * (`opportunityId` = l'`entity_id` delle note, spec 0086 D-9) ma e' SCOPATA
 * a questa Offerta (`quoteId`, spec 0085 D-1) — la stessa coppia che le
 * Offerte risolvono in `useQuoteRowActions`.
 */
interface RequestNotesTarget {
  opportunityId: number
  quoteId: number
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
 * Thin Request Management adapter over the generic table: an OPERATIVE view
 * over `quotes` rows (spec 0086 D-1, one row = one Offerta). Mounts
 * `<TableView>` with the `request-management` domain and its status-badge
 * renderers, and delegates the "Lavora" row action (`view`) to
 * `useModuleOpener`, resolved from the user's open-mode preference (spec
 * 0042): modal mounts the work panel in a Sheet, page mode navigates to
 * `/request-management/:id` (the Offerta id). The `documents` row action opens
 * the shared `DocumentsDialog` on the same polymorphic owner the opportunities
 * module uses, keyed on the row's `opportunity_id` (spec 0086 D-9: documents
 * stay anchored to the Opportunity, never the Offerta), gated server-side by
 * this module's OWN `request-management.viewDocuments` (D-2). The `notes` row
 * action opens the agnostic `NotesDialog` on the same `opportunity_id` (spec
 * 0052/0086 D-9), gated server-side by the notes feature's own hybrid
 * authorization (D-6) — this module only wires the `entityType`/`entityId`
 * pair, plus the `lockedQuoteId` that scopes the thread to the row's OWN
 * Offerta (direttiva utente 2026-08-07: stesso componente e stesso filtro
 * delle Offerte, spec 0085). The `activity` row action — declared last in the catalog, so the
 * shared inline limit pushes it into the three-dots overflow — opens
 * `ResourceActivityDialog` on this module's OWN activity resource key
 * (`request-management`, likewise keyed on `opportunity_id`, gated
 * server-side by `request-management.viewActivity` + the supervisor scope).
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
  const dashboard = useStatsPanel(REQUEST_MANAGEMENT_DOMAIN)

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])
  // What every bulk write does once it lands: re-read the rows and drop the
  // now-stale checkbox selection.
  const clearSelectionAndRefresh = useCallback(() => {
    refreshGrid()
    tableRef.current?.clearSelection()
  }, [refreshGrid])
  const { openCreate, openView, sheet } = useModuleOpener(REQUEST_MANAGEMENT_DOMAIN, {
    onSaved: refreshGrid,
  })

  const [documentsRowId, setDocumentsRowId] = useState<number | null>(null)
  const [notesTarget, setNotesTarget] = useState<RequestNotesTarget | null>(null)
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
          // Spec 0086 D-9: documents stay anchored to the Opportunity, never
          // the row's own Quote id.
          setDocumentsRowId(row.opportunity_id as number)
          break
        case 'notes':
          // Il thread resta quello dell'Opportunita' (D-9), filtrato sulla
          // riga: `row.id` E' l'Offerta (spec 0086 D-1).
          setNotesTarget({ opportunityId: row.opportunity_id as number, quoteId: row.id })
          break
        case 'delete':
          void runDelete(row)
          break
        case 'activity':
          // Same D-9 reason: the shared dialog reads `row.id`, so the row is
          // projected onto the Opportunity id before it reaches it.
          setActivityRow({ ...row, id: row.opportunity_id as number })
          break
        case 'transfer-contact':
          // Transfer writes `quotes.supervisor_id`/`operational_site_id`
          // (spec 0086): request_ids are Offerta ids, i.e. the row's own id.
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

  // Bulk GA3 assignment (spec 0104): the Sede-less sibling of the flow above —
  // one chosen user onto every selected row, or `null` to clear the slot. Its
  // whole flow (gate, label, dialog state, mutation) lives in its own hook.
  const managerGa3 = useRequestManagerGa3Assignment({
    categoryId: selectedCategoryId,
    onAssigned: clearSelectionAndRefresh,
  })

  // Surfaced inside the generic table's single "Actions" dropdown, alongside
  // the built-in "elimina selezionati". Each entry gated on its own ability;
  // `undefined` (not a function returning an empty array) when neither is
  // reachable, so the checkbox column stays off entirely.
  const getBulkActions =
    canAssignOperators || managerGa3.canAssign || canTransferContact
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
          ...(managerGa3.canAssign
            ? [
                {
                  key: 'assign-manager-ga3',
                  label: t('requestManagement.assignManagerGa3.tableButton', { label: managerGa3.label }),
                  icon: GraduationCap,
                  onSelect: () => managerGa3.openDialog(selection.ids),
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
        setNotesTarget(null)
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
          <>
            <RequestDashboardToggle
              domain={REQUEST_MANAGEMENT_DOMAIN}
              isOpen={dashboard.isOpen}
              onToggle={dashboard.toggle}
            />
            <Can permission="request-management.create">
              <Button onClick={openCreate}>
                <Plus aria-hidden="true" />
                {t('requestManagement.form.newRequest')}
              </Button>
            </Can>
          </>
        }
      />

      {/* User directive 2026-09-08: statistics above, category strip below it. */}
      <RequestDashboardPanel isOpen={dashboard.isOpen} />

      <RequestManagementCategoryTabs
        categories={categories}
        selectedCategoryId={selectedCategoryId}
        onSelect={setCategoryId}
      />

      {/* User directive 2026-09-07: the "Linee di prodotto" cell is inline
          editable like the others, and its editor delegates to this dialog
          (see `OfferLinesCellEditor`). Mounted around the grid, not per cell,
          so one dialog serves every row. */}
      <OfferLinesDialogProvider>
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
      </OfferLinesDialogProvider>

      {sheet}

      <AssignOperatorsDialog
        open={assignOpen}
        onOpenChange={setAssignOpen}
        selectionCount={assignIds.length}
        defaultSite={assignDefaultSite}
        copy={assignCopy}
        onAssign={handleAssign}
      />

      <AssignManagerGa3Dialog
        open={managerGa3.open}
        onOpenChange={managerGa3.setOpen}
        selectionCount={managerGa3.selectionCount}
        label={managerGa3.label}
        onAssign={managerGa3.assign}
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
        entityId={notesTarget?.opportunityId ?? null}
        lockedQuoteId={notesTarget?.quoteId ?? null}
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
