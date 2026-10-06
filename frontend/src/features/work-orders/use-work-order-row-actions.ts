import { useCallback, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { toast } from 'sonner'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import type { OpenMode } from '@/features/modules/types'
import { deleteWorkOrder, WORK_ORDERS_DOMAIN } from '@/features/work-orders/api'
import type { ActionIconMap } from '@/features/table/action-icon-map'
import type { RowActionHandler, RowActionState } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import {
  PROFORMA_ACTION_KEY,
  WORK_ORDER_ACTION_ICONS,
  proformaStatusOf,
  useProformaActionState,
} from '@/features/work-orders/proforma-row-action'

export interface UseWorkOrderRowActionsOptions {
  /** Called after anything that changes the displayed rows: a delete. */
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
  sheet: ReactNode
}

/**
 * The Commesse action catalog's BEHAVIOR (view/delete/activity), owned
 * once and shared by every surface that renders those actions: the
 * standalone Commesse grid (`WorkOrdersTable`) and the Contract detail's
 * Commesse tab (`ContractWorkOrdersSection`, spec 0095 D-9). Extracted so a
 * duplicated switch can never drift the way `useQuoteRowActions` documents
 * (`use-opportunity-quotes-panel.ts`). No `create` here (spec 0093 D-13):
 * a work order is only ever generated from a Contract's "Programma" action.
 */
export function useWorkOrderRowActions({
  onMutated,
  forceMode,
}: UseWorkOrderRowActionsOptions): UseWorkOrderRowActionsResult {
  const { t } = useTranslation()

  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)
  const [proformaRow, setProformaRow] = useState<TableRow | null>(null)
  const resolveActionState = useProformaActionState()

  const { openView, sheet } = useModuleOpener(WORK_ORDERS_DOMAIN, {
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
    [openView, runDelete],
  )

  const isBusy = useCallback((row: TableRow) => row.id === deletingId, [deletingId])

  const closeActivity = useCallback((open: boolean) => {
    if (!open) {
      setActivityRow(null)
    }
  }, [])

  const closeProforma = useCallback(() => setProformaRow(null), [])

  return {
    handleAction,
    isBusy,
    activityRow,
    closeActivity,
    proformaRow,
    closeProforma,
    resolveActionState,
    iconMap: WORK_ORDER_ACTION_ICONS,
    sheet,
  }
}
