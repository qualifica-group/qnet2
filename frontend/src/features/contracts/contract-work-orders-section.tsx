import { forwardRef, useCallback, useImperativeHandle, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { RecordCard, RecordCardHeader } from '@/components/detail/record-panel'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { useAbilities } from '@/features/auth/use-abilities'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import { workOrderColumnRenderers } from '@/features/work-orders/column-renderers'
import { WORK_ORDERS_DOMAIN } from '@/features/work-orders/api'
import { useWorkOrderRowActions } from '@/features/work-orders/use-work-order-row-actions'

export interface ContractWorkOrdersSectionHandle {
  /** Purges and reloads the tab's grid (AC-062: called after "Programma" generates a new Commessa). */
  refresh: () => void
}

interface ContractWorkOrdersSectionProps {
  /** Contratto/Offerta e' 1:1 (D-8): lo scope e' l'offerta, non il contratto. */
  quoteId: number
}

/**
 * Permission gate: absent entirely without `work-orders.viewAny`. Kept as its
 * own component, not an early-return inside the panel, so the panel — which
 * mounts `useModuleOpener` (and therefore `useNavigate`) unconditionally, per
 * rules-of-hooks — is never even instantiated when the permission is missing
 * (mirrors `OpportunityQuotesSection`, spec 0067).
 */
export const ContractWorkOrdersSection = forwardRef<ContractWorkOrdersSectionHandle, ContractWorkOrdersSectionProps>(
  function ContractWorkOrdersSection({ quoteId }, ref) {
    const { can } = useAbilities()

    if (!can('work-orders.viewAny')) {
      return null
    }

    return <ContractWorkOrdersPanel ref={ref} quoteId={quoteId} />
  },
)

/**
 * The Contract detail's Commesse tab (spec 0095 D-8/D-10): the SAME
 * `TableView domain="work-orders"` and `workOrderColumnRenderers` the
 * standalone Commesse page uses, scoped to this contract's offer via
 * `rowScope={{quoteId}}`, in a full-width `RecordCard` below the two-column
 * grid (too narrow a column for a toolbar + grid + filters). Row actions
 * reuse `useWorkOrderRowActions` (D-9) forced into a modal so opening a
 * Commessa never abandons the Contract. `refresh` is exposed via `ref` so
 * `ContractActionsBar`'s "Programma" dialog — a SIBLING, not a parent — can
 * reload this grid after generating a new Commessa (AC-062).
 */
const ContractWorkOrdersPanel = forwardRef<ContractWorkOrdersSectionHandle, ContractWorkOrdersSectionProps>(
  function ContractWorkOrdersPanel({ quoteId }, ref) {
    const { t } = useTranslation()

    const tableRef = useRef<TableViewHandle>(null)
    const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])
    useImperativeHandle(ref, () => ({ refresh: refreshGrid }), [refreshGrid])

    const [rowCount, setRowCount] = useState<number | null>(null)
    const handleRowCountChanged = useCallback((next: number | null) => setRowCount(next), [])

    const { handleAction, isBusy, activityRow, closeActivity, sheet } = useWorkOrderRowActions({
      onMutated: refreshGrid,
      forceMode: OPEN_MODE_MODAL,
    })

    const count = rowCount ?? 0

    return (
      <RecordCard>
        <RecordCardHeader
          title={
            <span className="flex items-center gap-2">
              <span className="truncate">{t('contracts.detail.workOrders.title')}</span>
              <Badge variant="secondary" aria-label={t('contracts.detail.workOrders.countLabel', { count })}>
                {count}
              </Badge>
            </span>
          }
        />

        <div className="p-4">
          <TableView
            ref={tableRef}
            domain={WORK_ORDERS_DOMAIN}
            rowScope={{ quoteId }}
            renderers={workOrderColumnRenderers}
            onAction={handleAction}
            isBusy={isBusy}
            onRowCountChanged={handleRowCountChanged}
          />
        </div>

        {sheet}

        <ResourceActivityDialog resource={WORK_ORDERS_DOMAIN} row={activityRow} onOpenChange={closeActivity} />
      </RecordCard>
    )
  },
)
