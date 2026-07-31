import { useCallback, useRef, useState } from 'react'
import { PageHeader } from '@/components/page-header'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { contractColumnRenderers } from '@/features/contracts/column-renderers'
import { CONTRACTS_DOMAIN } from '@/features/contracts/api'

/**
 * Row-action keys whose real UI (a dialog with inputs, gated on its own
 * permission) lives ONLY inside `ContractActionsBar`/`ContractEditDialog`
 * on the detail view — never duplicated as a standalone grid dialog. The
 * backend's row-action catalog (spec 0072, MT-04) advertises them per-row
 * (`edit`, `change_status`, `validate`, `schedule`, `terminate`,
 * `reactivate`) so the grid still needs to react to a click: it opens the
 * detail, where the actual gated action button/dialog is.
 */
const ACTIONS_OPENING_DETAIL = new Set(['edit', 'change_status', 'validate', 'schedule', 'terminate', 'reactivate'])

/**
 * Thin Contracts adapter over the generic table (spec 0072, mirrors
 * `QuotesTable`). No "New contract" button: a contract is never created nor
 * deleted by hand (D-6) — `ContractPolicy::abilities()` exposes no
 * `create`/`delete`, so the row-action catalog never advertises them either.
 * `view` and every domain-action key in `ACTIONS_OPENING_DETAIL` all open the
 * detail (see its doc); `activity` opens the shared activity dialog inline.
 * Permission gating is an affordance only; the backend re-authorizes each
 * call.
 */
export function ContractsTable() {
  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const { openView, sheet } = useModuleOpener(CONTRACTS_DOMAIN, {
    onSaved: refreshGrid,
  })

  const handleAction: RowActionHandler = useCallback(
    (action: TableActionDefinition, row: TableRow) => {
      if (action.key === 'view' || ACTIONS_OPENING_DETAIL.has(action.key)) {
        openView(row)
        return
      }
      if (action.key === 'activity') {
        setActivityRow(row)
      }
    },
    [openView],
  )

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader />

      <TableView
        ref={tableRef}
        domain={CONTRACTS_DOMAIN}
        renderers={contractColumnRenderers}
        onAction={handleAction}
      />

      {sheet}

      <ResourceActivityDialog
        resource={CONTRACTS_DOMAIN}
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
