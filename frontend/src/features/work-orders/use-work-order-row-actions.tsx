import { useCallback, useMemo, useState, type ReactNode } from 'react'
import { Contact, Info, ListChecks, MessagesSquare, Paperclip } from 'lucide-react'
import { moduleActionHref } from '@/features/modules/module-action-href'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import type { ModuleCreateParams, OpenMode } from '@/features/modules/types'
import { WORK_ORDERS_DOMAIN } from '@/features/work-orders/api'
import { buildWorkOrderColumnRenderers } from '@/features/work-orders/column-renderers'
import { moduleDefinition } from '@/features/work-orders/work-order-module'
import { WorkOrderProgressDialog } from '@/features/work-orders/work-order-progress-dialog'
import { WorkOrderTeamDialog } from '@/features/work-orders/work-order-team-dialog'
import type { ActionIconMap } from '@/features/table/action-icon-map'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import type { ActionHrefResolver, RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/** Row-action key of the "Responsabili e Partecipanti" dialog (spec 0405). */
const TEAM_ACTION_KEY = 'team'

/** The row actions that open the commessa on one of its detail tabs (spec 0405): action key = tab value. */
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
 * for the eye, the page on that tab for Info and the menu entries.
 */
const resolveActionHref: ActionHrefResolver = (action, row) => {
  if (TAB_ACTION_KEYS.has(action.key)) {
    return `${moduleDefinition.basePath}/${row.id}?${new URLSearchParams({ tab: action.key }).toString()}`
  }
  return VIEW_HREF?.(action, row)
}

export interface UseWorkOrderRowActionsOptions {
  /** Called after anything that changes the displayed rows: a save in the opened record or in a dialog. */
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
  /** The Commesse cell renderers, its progress cell wired to this hook's popup (spec 0321 R-6). */
  renderers: TableRendererMap
  /**
   * Opens the create form seeded with `params`, in the same Sheet as view:
   * only the anagrafica detail's Commesse tab offers it (spec 0199).
   */
  openCreateWith: (params: ModuleCreateParams) => void
  sheet: ReactNode
  /** The "Responsabili e Partecipanti" and progress dialogs: the host mounts them. */
  dialogs: ReactNode
}

/**
 * The Commesse row actions of the legacy `OrdersTable` (spec 0405), shared by
 * every surface that lists work orders (Commesse, Commesse di Progetto, the
 * Contract and Anagrafica tabs): the eye (in place of the legacy "Modifica")
 * opens the record, "Responsabili e Partecipanti" its team dialog, Info and
 * the menu entries (Storico Note / Task / Documenti) open the record on that
 * tab. The progress cell keeps its own
 * dialog (spec 0321).
 */
export function useWorkOrderRowActions({
  onMutated,
  forceMode,
}: UseWorkOrderRowActionsOptions): UseWorkOrderRowActionsResult {
  const [teamId, setTeamId] = useState<number | null>(null)
  const [progressId, setProgressId] = useState<number | null>(null)
  const renderers = useMemo(() => buildWorkOrderColumnRenderers(setProgressId), [])

  const { openCreateWith, openView, openViewTab, sheet } = useModuleOpener(WORK_ORDERS_DOMAIN, {
    onSaved: onMutated,
    forceMode,
  })

  const handleAction: RowActionHandler = useCallback(
    (action: TableActionDefinition, row: TableRow) => {
      if (action.key === 'view') {
        openView(row)
      } else if (action.key === TEAM_ACTION_KEY) {
        setTeamId(Number(row.id))
      } else if (TAB_ACTION_KEYS.has(action.key)) {
        openViewTab(row, action.key)
      }
    },
    [openView, openViewTab],
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
    </>
  )

  return {
    handleAction,
    resolveActionHref,
    iconMap: ROW_ACTION_ICONS,
    renderers,
    openCreateWith,
    sheet,
    dialogs,
  }
}
