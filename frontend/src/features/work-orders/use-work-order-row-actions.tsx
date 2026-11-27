import { useCallback, useMemo, useState, type ReactNode } from 'react'
import { Contact, Info, ListChecks, MessagesSquare, Paperclip } from 'lucide-react'
import { moduleActionHref } from '@/features/modules/module-action-href'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { OPEN_MODE_MODAL, type ModuleCreateParams, type OpenMode } from '@/features/modules/types'
import { WORK_ORDERS_DOMAIN } from '@/features/work-orders/api'
import { buildWorkOrderColumnRenderers } from '@/features/work-orders/column-renderers'
import { moduleDefinition } from '@/features/work-orders/work-order-module'
import { ProformaRequestDialog } from '@/features/work-orders/proforma-request-dialog'
import type { ProformaTarget } from '@/features/work-orders/work-order-proforma-cell'
import { WorkOrderProgressDialog } from '@/features/work-orders/work-order-progress-dialog'
import { WorkOrderTeamDialog } from '@/features/work-orders/work-order-team-dialog'
import { useDeleteWorkOrder } from '@/features/work-orders/use-delete-work-order'
import type { ActionIconMap } from '@/features/table/action-icon-map'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import type { ActionHrefResolver, RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/** Row-action key of the "Responsabili e Partecipanti" dialog (spec 0405). */
const TEAM_ACTION_KEY = 'team'

/** Row-action key of Elimina, offered only where the policy allows it (user request 2026-11-19). */
const DELETE_ACTION_KEY = 'delete'

/**
 * The "..." menu entries that open the commessa on one of its detail tabs (spec 0405): action key = tab value.
 * They always open the modal, never the page (user request 2026-11-27).
 */
const TAB_ACTION_KEYS: ReadonlySet<string> = new Set(['info', 'notes', 'tasks', 'documents'])

const ROW_ACTION_ICONS: ActionIconMap = {
  contact: Contact,
  info: Info,
  'messages-square': MessagesSquare,
  'list-checks': ListChecks,
  paperclip: Paperclip,
}

const VIEW_HREF = moduleActionHref(WORK_ORDERS_DOMAIN)

/**
 * The page each row action leads to, for Ctrl/Cmd+click: the commessa's page
 * for the eye, the page on that tab for the tab entries of the menu.
 */
const resolveActionHref: ActionHrefResolver = (action, row) => {
  if (TAB_ACTION_KEYS.has(action.key)) {
    return `${moduleDefinition.basePath}/${row.id}?${new URLSearchParams({ tab: action.key }).toString()}`
  }
  return VIEW_HREF?.(action, row)
}

export interface UseWorkOrderRowActionsOptions {
  /** Called after anything that changes the displayed rows: a save in the opened record or in a dialog, a delete. */
  onMutated: () => void
  /**
   * Forces the open mode of the record instead of honoring the user's
   * preference (spec 0067 D-3 pattern): the Contract and Anagrafica Commesse
   * tabs use this so opening a Commessa never abandons the host record.
   * Omitted, the actor's own preference wins.
   */
  forceMode?: OpenMode
}

export interface UseWorkOrderRowActionsResult {
  handleAction: RowActionHandler
  /** Ctrl/Cmd+click pages of the row actions, for `<TableView resolveActionHref>`. */
  resolveActionHref: ActionHrefResolver
  iconMap: ActionIconMap
  /** The Commesse cell renderers, the progress and "€" cells wired to this hook's dialogs (spec 0321 R-6, spec 0407). */
  renderers: TableRendererMap
  /**
   * Opens the create form seeded with `params`, in the same Sheet as view:
   * only the anagrafica detail's Commesse tab offers it (spec 0199).
   */
  openCreateWith: (params: ModuleCreateParams) => void
  sheet: ReactNode
  /** The "Responsabili e Partecipanti", progress and proforma request dialogs: the host mounts them. */
  dialogs: ReactNode
}

/**
 * The Commesse row actions of the legacy `OrdersTable` (spec 0405), shared by
 * every surface that lists work orders (Commesse, Commesse di Progetto, the
 * Contract and Anagrafica tabs): the eye (in place of the legacy "Modifica")
 * opens the record, "Responsabili e Partecipanti" its team dialog, the menu
 * entries Info / Storico Note / Task / Documenti open the record on that tab
 * in the modal whatever the open mode (user request 2026-11-27: the grid is
 * never left), Elimina (last in the menu, already confirmed by the row)
 * deletes it. The progress cell keeps its own dialog (spec 0321), the "€"
 * cell the proforma request one (spec 0407).
 */
export function useWorkOrderRowActions({
  onMutated,
  forceMode,
}: UseWorkOrderRowActionsOptions): UseWorkOrderRowActionsResult {
  const [teamId, setTeamId] = useState<number | null>(null)
  const [progressId, setProgressId] = useState<number | null>(null)
  const [proformaTarget, setProformaTarget] = useState<ProformaTarget | null>(null)
  const renderers = useMemo(() => buildWorkOrderColumnRenderers(setProgressId, setProformaTarget), [])

  const { openCreateWith, openView, sheet } = useModuleOpener(WORK_ORDERS_DOMAIN, {
    onSaved: onMutated,
    forceMode,
  })
  const { openViewTab, sheet: tabSheet } = useModuleOpener(WORK_ORDERS_DOMAIN, {
    onSaved: onMutated,
    forceMode: OPEN_MODE_MODAL,
  })
  const { remove } = useDeleteWorkOrder(onMutated)

  const handleAction: RowActionHandler = useCallback(
    (action: TableActionDefinition, row: TableRow) => {
      if (action.key === 'view') {
        openView(row)
      } else if (action.key === TEAM_ACTION_KEY) {
        setTeamId(Number(row.id))
      } else if (TAB_ACTION_KEYS.has(action.key)) {
        openViewTab(row, action.key)
      } else if (action.key === DELETE_ACTION_KEY) {
        void remove(Number(row.id))
      }
    },
    [openView, openViewTab, remove],
  )

  const closeOnFalse = (close: () => void) => (open: boolean) => {
    if (!open) {
      close()
    }
  }

  const dialogs = (
    <>
      <WorkOrderTeamDialog
        workOrderId={teamId}
        onOpenChange={closeOnFalse(() => setTeamId(null))}
        onMutated={onMutated}
      />
      <WorkOrderProgressDialog
        workOrderId={progressId}
        onOpenChange={closeOnFalse(() => setProgressId(null))}
        onMutated={onMutated}
      />
      {proformaTarget !== null ? (
        <ProformaRequestDialog
          workOrderId={proformaTarget.id}
          workOrderCode={proformaTarget.code}
          open
          onOpenChange={closeOnFalse(() => setProformaTarget(null))}
          onSent={() => {
            setProformaTarget(null)
            onMutated()
          }}
        />
      ) : null}
    </>
  )

  return {
    handleAction,
    resolveActionHref,
    iconMap: ROW_ACTION_ICONS,
    renderers,
    openCreateWith,
    sheet: (
      <>
        {sheet}
        {tabSheet}
      </>
    ),
    dialogs,
  }
}
