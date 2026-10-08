import { useCallback, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { toast } from 'sonner'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import type { ModuleCreateParams, OpenMode } from '@/features/modules/types'
import { deleteWorkOrder, WORK_ORDERS_DOMAIN } from '@/features/work-orders/api'
import type { ActionIconMap } from '@/features/table/action-icon-map'
import { REOPEN_PAYLOAD, useWorkOrderClosure, WORK_ORDER_ACTION_ICONS } from '@/features/work-orders/use-work-order-closure'
import { WorkOrderForceCloseDialog } from '@/features/work-orders/work-order-force-close-dialog'
import type { RowActionHandler, RowActionState } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import {
  PROFORMA_ACTION_ICONS,
  PROFORMA_ACTION_KEY,
  proformaStatusOf,
  useProformaActionState,
} from '@/features/work-orders/proforma-row-action'

/** Every icon the Commesse row actions add to the shared map: closure (lock) and proforma (euro). */
const ROW_ACTION_ICONS: ActionIconMap = { ...WORK_ORDER_ACTION_ICONS, ...PROFORMA_ACTION_ICONS }

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
  const { t } = useTranslation()

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)
  const [forceCloseId, setForceCloseId] = useState<number | null>(null)
  const [reopeningId, setReopeningId] = useState<number | null>(null)
  const [proformaRow, setProformaRow] = useState<TableRow | null>(null)
  const { mutateAsync: changeClosure } = useWorkOrderClosure()
  const resolveActionState = useProformaActionState()

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
        if (status === 403) {
          toast.error(t('workOrders.form.deleteForbidden'))
        } else if (status === 409) {
          toast.error(t('workOrders.form.deleteConflict'))
        } else {
          toast.error(t('workOrders.form.deleteError'))
        }
      } finally {
        setDeletingId(null)
      }
    },
    [onMutated, t],
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
        case 'force_close':
          setForceCloseId(Number(row.id))
          break
        case 'reopen':
          void runReopen(row)
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
    [openView, runDelete, runReopen],
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

  return {
    handleAction,
    isBusy,
    activityRow,
    closeActivity,
    proformaRow,
    closeProforma,
    resolveActionState,
    iconMap: ROW_ACTION_ICONS,
    openCreateWith,
    sheet,
    forceCloseDialog,
  }
}
