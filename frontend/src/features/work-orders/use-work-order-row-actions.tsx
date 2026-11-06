import { useCallback, useMemo, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { toast } from 'sonner'
import { MessageSquareMore, TrendingUp } from 'lucide-react'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import type { ModuleCreateParams, OpenMode } from '@/features/modules/types'
import { deleteWorkOrder, fetchWorkOrder, WORK_ORDERS_DOMAIN } from '@/features/work-orders/api'
import {
  COLLABORATION_ACTION_ICONS,
  useWorkOrderCollaborationDialogs,
} from '@/features/work-orders/use-work-order-collaboration-dialogs'
import type { ActionIconMap } from '@/features/table/action-icon-map'
import { REOPEN_PAYLOAD, useWorkOrderClosure, WORK_ORDER_ACTION_ICONS } from '@/features/work-orders/use-work-order-closure'
import { buildWorkOrderColumnRenderers } from '@/features/work-orders/column-renderers'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import { WorkOrderProgressDialog } from '@/features/work-orders/work-order-progress-dialog'
import { WorkOrderRequestUpdateDialog } from '@/features/work-orders/work-order-request-update-dialog'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'
import { WorkOrderForceCloseDialog } from '@/features/work-orders/work-order-force-close-dialog'
import type { RowActionHandler, RowActionState } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import {
  PROFORMA_ACTION_ICONS,
  PROFORMA_ACTION_KEY,
  proformaStatusOf,
  useProformaActionState,
} from '@/features/work-orders/proforma-row-action'
import { apiErrorMessage, forbiddenMessage } from '@/lib/forbidden-message'

/** Every icon the Commesse row actions add to the shared map: closure (lock), proforma (euro) and progress (trending-up). */
/** Row-action key and icon of the manual progress dialog (spec 0321). */
const PROGRESS_ACTION_KEY = 'progress'
const PROGRESS_ACTION_ICONS: ActionIconMap = { 'trending-up': TrendingUp }

/** Row-action key and icon of the "Richiedi aggiornamento" dialog (spec 0352). */
const REQUEST_UPDATE_ACTION_KEY = 'request_update'
const REQUEST_UPDATE_ACTION_ICONS: ActionIconMap = { 'message-square-more': MessageSquareMore }

const ROW_ACTION_ICONS: ActionIconMap = {
  ...COLLABORATION_ACTION_ICONS,
  ...WORK_ORDER_ACTION_ICONS,
  ...PROFORMA_ACTION_ICONS,
  ...PROGRESS_ACTION_ICONS,
  ...REQUEST_UPDATE_ACTION_ICONS,
}

export interface UseWorkOrderRowActionsOptions {
  /** Called after anything that changes the displayed rows: a save, a delete, a forced closure, a reopen. */
  onMutated: () => void
  /**
   * Forces the open mode of view instead of honoring the user's
   * preference (spec 0067 D-3 pattern): the Contract detail's Commesse tab
   * (spec 0095 D-9) uses this so opening a Commessa never abandons the
   * Contract. Omitted, the actor's own preference wins.
   */
  forceMode?: OpenMode
}

export interface UseWorkOrderRowActionsResult {
  handleAction: RowActionHandler
  isBusy: (row: TableRow) => boolean
  activityRow: TableRow | null
  closeActivity: (open: boolean) => void
  /** The row whose "€" proforma request modal is open (spec 0193), or null. */
  proformaRow: TableRow | null
  closeProforma: () => void
  /** Tint/label/disabled of the "€" action per row, for `<TableView resolveActionState>`. */
  resolveActionState: (action: TableActionDefinition, row: TableRow) => RowActionState | undefined
  iconMap: ActionIconMap
  /** The Commesse cell renderers, its progress cell wired to this hook's popup (spec 0321 R-6). */
  renderers: TableRendererMap
  /**
   * Opens the create form seeded with `params`, in the same Sheet as view:
   * only the anagrafica detail's Commesse tab offers it (spec 0199,
   * `registry_id` narrows the Offerta picker) — the list page still does not
   * (spec 0093 D-13).
   */
  openCreateWith: (params: ModuleCreateParams) => void
  sheet: ReactNode
  /** The "Chiusura forzata" reason dialog of the `force_close` row action: the host mounts it. */
  forceCloseDialog: ReactNode
  /** The "Avanzamento" dialog of the `progress` row action (spec 0321): the host mounts it. */
  progressDialog: ReactNode
  /** The "Richiedi aggiornamento" dialog of the `request_update` row action (spec 0352): the host mounts it. */
  requestUpdateDialog: ReactNode
  /** The documents and notes dialogs of the `documents`/`notes` row actions (spec 0363): the host mounts them. */
  collaborationDialogs: ReactNode
}

/**
 * The Commesse action catalog's BEHAVIOR (view/delete/activity, the "€"
 * proforma request of spec 0193, and the
 * forced closure as an action with its inverse — user directive 2026-10-06 —
 * `force_close` opening the reason dialog, `reopen` already confirmed by the
 * grid's own `confirm: true`), owned
 * once and shared by every surface that renders those actions: the
 * standalone Commesse grid (`WorkOrdersTable`) and the Contract detail's
 * Commesse tab (`ContractWorkOrdersSection`, spec 0095 D-9). Extracted so a
 * duplicated switch can never drift the way `useQuoteRowActions` documents
 * (`use-opportunity-quotes-panel.ts`).
 */
export function useWorkOrderRowActions({
  onMutated,
  forceMode,
}: UseWorkOrderRowActionsOptions): UseWorkOrderRowActionsResult {
  const { t, i18n } = useTranslation()

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)
  const [forceCloseId, setForceCloseId] = useState<number | null>(null)
  const [reopeningId, setReopeningId] = useState<number | null>(null)
  const [proformaRow, setProformaRow] = useState<TableRow | null>(null)
  const [progressId, setProgressId] = useState<number | null>(null)
  const { openDocuments, openNotes, dialogs: collaborationDialogs } = useWorkOrderCollaborationDialogs(onMutated)
  const [requestUpdateWorkOrder, setRequestUpdateWorkOrder] = useState<WorkOrderDetailWithPermissions | null>(null)
  const { mutateAsync: changeClosure } = useWorkOrderClosure()
  const resolveActionState = useProformaActionState()
  const renderers = useMemo(() => buildWorkOrderColumnRenderers(setProgressId), [])

  const { openCreateWith, openView, sheet } = useModuleOpener(WORK_ORDERS_DOMAIN, {
    onSaved: onMutated,
    forceMode,
  })

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(Number(row.id))
      try {
        await deleteWorkOrder(Number(row.id))
        toast.success(t('workOrders.form.deleted'))
        onMutated()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        const forbidden = forbiddenMessage(error, i18n)
        if (forbidden !== null) {
          toast.error(forbidden)
        } else if (status === 409) {
          toast.error(t('workOrders.form.deleteConflict'))
        } else {
          toast.error(t('workOrders.form.deleteError'))
        }
      } finally {
        setDeletingId(null)
      }
    },
    [onMutated, t, i18n],
  )

  const runReopen = useCallback(
    async (row: TableRow) => {
      setReopeningId(Number(row.id))
      try {
        await changeClosure({ workOrderId: Number(row.id), payload: REOPEN_PAYLOAD })
        toast.success(t('workOrders.actions.reopen.success'))
        onMutated()
      } catch {
        toast.error(t('workOrders.form.genericError'))
      } finally {
        setReopeningId(null)
      }
    },
    [changeClosure, onMutated, t],
  )

  // The row's grid projection carries no recipients: load the fresh record the dialog counts from.
  const openRequestUpdate = useCallback(
    (row: TableRow) => {
      fetchWorkOrder(Number(row.id))
        .then(setRequestUpdateWorkOrder)
        .catch((error: unknown) => toast.error(apiErrorMessage(error, t('workOrders.form.genericError'), i18n)))
    },
    [t, i18n],
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
        case 'documents':
          openDocuments(Number(row.id))
          break
        case 'notes':
          openNotes(Number(row.id))
          break
        case 'force_close':
          setForceCloseId(Number(row.id))
          break
        case 'reopen':
          void runReopen(row)
          break
        case PROGRESS_ACTION_KEY:
          setProgressId(Number(row.id))
          break
        case REQUEST_UPDATE_ACTION_KEY:
          openRequestUpdate(row)
          break
        case PROFORMA_ACTION_KEY:
          // Issued requests are inert (the button is disabled too): a guard
          // against a stale row firing the action anyway.
          if (proformaStatusOf(row) !== 'issued') {
            setProformaRow(row)
          }
          break
        default:
          break
      }
    },
    [openView, runDelete, runReopen, openRequestUpdate, openDocuments, openNotes],
  )

  const isBusy = useCallback(
    (row: TableRow) => row.id === deletingId || row.id === reopeningId,
    [deletingId, reopeningId],
  )

  const closeActivity = useCallback((open: boolean) => {
    if (!open) {
      setActivityRow(null)
    }
  }, [])

  const closeProforma = useCallback(() => setProformaRow(null), [])

  const forceCloseDialog = (
    <WorkOrderForceCloseDialog
      workOrderId={forceCloseId}
      onOpenChange={(open) => {
        if (!open) {
          setForceCloseId(null)
        }
      }}
      onClosed={onMutated}
    />
  )

  const progressDialog = (
    <WorkOrderProgressDialog
      workOrderId={progressId}
      onOpenChange={(open) => {
        if (!open) {
          setProgressId(null)
        }
      }}
      onMutated={onMutated}
    />
  )

  const requestUpdateDialog = requestUpdateWorkOrder ? (
    <WorkOrderRequestUpdateDialog
      open
      onOpenChange={(open) => {
        if (!open) {
          setRequestUpdateWorkOrder(null)
        }
      }}
      workOrder={requestUpdateWorkOrder}
    />
  ) : null

  return {
    handleAction,
    isBusy,
    activityRow,
    closeActivity,
    proformaRow,
    closeProforma,
    resolveActionState,
    iconMap: ROW_ACTION_ICONS,
    renderers,
    openCreateWith,
    sheet,
    forceCloseDialog,
    progressDialog,
    requestUpdateDialog,
    collaborationDialogs,
  }
}
