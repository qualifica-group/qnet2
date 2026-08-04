import { useCallback } from 'react'
import { PageHeader } from '@/components/page-header'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { TableView } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { fieldChangeRequestColumnRenderers } from '@/features/field-change-requests/column-renderers'
import { FIELD_CHANGE_REQUESTS_DOMAIN } from '@/features/field-change-requests/types'

/**
 * Thin field-change-requests adapter over the generic table (spec 0078,
 * AC-036): a read-only browse of every proposed change across every
 * resource/field, mirroring `ContractsTable`'s minimal shape. No "New"
 * affordance — a request is proposed from its own write-interception dialog
 * (E1's `useRequestFieldChange()`), never created here.
 *
 * The single row action the backend advertises is `view`
 * (`FieldChangeRequestsTableDefinition::actionsFor`): it opens the request's
 * detail, the only surface carrying the Approve/Reject buttons (AC-047) —
 * the grid itself never mutates a request. The module registry's
 * `defaultMode: OPEN_MODE_PAGE` makes that a navigation to
 * `/field-change-requests/:id`, the same target as the notification bell's
 * `action_url` (F-8/AC-025).
 */
export function FieldChangeRequestsTable() {
  const { openView, sheet } = useModuleOpener(FIELD_CHANGE_REQUESTS_DOMAIN)

  const handleAction: RowActionHandler = useCallback(
    (action: TableActionDefinition, row: TableRow) => {
      if (action.key === 'view') {
        openView(row)
      }
    },
    [openView],
  )

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader />

      <TableView
        domain={FIELD_CHANGE_REQUESTS_DOMAIN}
        renderers={fieldChangeRequestColumnRenderers}
        onAction={handleAction}
      />

      {sheet}
    </div>
  )
}
