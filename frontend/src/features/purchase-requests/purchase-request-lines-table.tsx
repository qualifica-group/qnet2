import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import { FileText, ListChecks, Truck } from 'lucide-react'
import { PageHeader } from '@/components/page-header'
import { useAbilities } from '@/features/auth/use-abilities'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import type { BulkAction, TableSelection } from '@/features/table/use-bulk-actions-slot'
import { purchaseRequestLineColumnRenderers } from '@/features/purchase-requests/column-renderers'
import { GenerateOrdersDialog } from '@/features/purchase-requests/generate-orders-dialog'
import { GENERATE_ORDERS_PERMISSION, generateOrdersBlocker } from '@/features/purchase-requests/generate-orders'
import { LineSupplierDialog } from '@/features/purchase-requests/line-supplier-dialog'
import { LineStatusDialog } from '@/features/purchase-requests/line-status-dialog'
import { LineStatusHistoryDialog } from '@/features/purchase-requests/line-status-history-dialog'
import { LineStatusTabs } from '@/features/purchase-requests/line-status-tabs'
import { toLineTarget, type LineTarget } from '@/features/purchase-requests/line-status-transitions'
import { useStatusTabFilter } from '@/features/purchase-requests/use-status-tab-filter'
import { PURCHASE_REQUEST_LINES_DOMAIN, PURCHASE_REQUESTS_DOMAIN } from '@/features/purchase-requests/types'

/** Grid filter key of the status tabs on the lines grid (spec 0208 contract). */
const STATUS_FILTER_KEY = 'status'
const NO_TARGETS: readonly LineTarget[] = []

/**
 * Thin "RDA line management" adapter over the generic table: status tabs, a
 * bulk "Change status" action over the selection (one line or many, one
 * dialog, all or nothing), the bulk "Assign supplier" action (spec 0209 D-4),
 * the bulk "Generate ODA" action (spec 0210 D-17) and the open-RDA and history row actions. The
 * dialog offers only what the server advertised in `abilities.transitions`.
 */
export function PurchaseRequestLinesTable() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])
  const tabs = useStatusTabFilter(tableRef, STATUS_FILTER_KEY)
  const { can } = useAbilities()
  const canGenerateOrders = can(GENERATE_ORDERS_PERMISSION)

  const [targets, setTargets] = useState<readonly LineTarget[]>(NO_TARGETS)
  const [supplierTargets, setSupplierTargets] = useState<readonly LineTarget[]>(NO_TARGETS)
  const [orderTargets, setOrderTargets] = useState<readonly LineTarget[]>(NO_TARGETS)
  const [historyLineId, setHistoryLineId] = useState<number | null>(null)

  const handleAction: RowActionHandler = useCallback(
    (action: TableActionDefinition, row: TableRow) => {
      switch (action.key) {
        case 'view':
          void navigate(`/${PURCHASE_REQUESTS_DOMAIN}/${row.purchase_request_id}`)
          break
        case 'history':
          setHistoryLineId(Number(row.id))
          break
        default:
          break
      }
    },
    [navigate],
  )

  const getBulkActions = useCallback(
    (selection: TableSelection): BulkAction[] => {
      const blocker = generateOrdersBlocker(selection.rows.map(toLineTarget), canGenerateOrders)
      return [
        {
          key: 'change-status',
          label: t('purchaseRequests.statusDialog.bulkAction'),
          icon: ListChecks,
          onSelect: () => setTargets(selection.rows.map(toLineTarget)),
        },
        {
          key: 'assign-supplier',
          label: t('purchaseRequests.supplierDialog.bulkAction'),
          icon: Truck,
          onSelect: () => setSupplierTargets(selection.rows.map(toLineTarget)),
        },
        {
          key: 'generate-orders',
          label: t('purchaseRequests.generateOrders.bulkAction'),
          icon: FileText,
          disabled: blocker !== null,
          disabledReason: blocker === null ? undefined : t(`purchaseRequests.generateOrders.blocked.${blocker}`),
          onSelect: () => setOrderTargets(selection.rows.map(toLineTarget)),
        },
      ]
    },
    [t, canGenerateOrders],
  )

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader />
      <LineStatusTabs active={tabs.active} onSelect={tabs.select} label={t('purchaseRequests.tabs.label')} />

      <TableView
        ref={tableRef}
        domain={PURCHASE_REQUEST_LINES_DOMAIN}
        renderers={purchaseRequestLineColumnRenderers}
        onAction={handleAction}
        onFilterModelChange={tabs.onFilterModelChange}
        getBulkActions={getBulkActions}
        disableBuiltinDelete
      />

      <LineStatusDialog
        key={targets.map((target) => target.id).join(',')}
        targets={targets}
        onOpenChange={(open) => (open ? undefined : setTargets(NO_TARGETS))}
        onChanged={() => {
          setTargets(NO_TARGETS)
          refreshGrid()
        }}
      />
      <LineSupplierDialog
        key={`supplier-${supplierTargets.map((target) => target.id).join(',')}`}
        targets={supplierTargets}
        onOpenChange={(open) => (open ? undefined : setSupplierTargets(NO_TARGETS))}
        onAssigned={() => {
          setSupplierTargets(NO_TARGETS)
          refreshGrid()
        }}
      />
      <GenerateOrdersDialog
        key={`orders-${orderTargets.map((target) => target.id).join(',')}`}
        targets={orderTargets}
        onOpenChange={(open) => (open ? undefined : setOrderTargets(NO_TARGETS))}
        onGenerated={refreshGrid}
      />
      <LineStatusHistoryDialog
        lineId={historyLineId}
        onOpenChange={(open) => (open ? undefined : setHistoryLineId(null))}
      />
    </div>
  )
}
