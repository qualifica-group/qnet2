import { useTranslation } from 'react-i18next'
import { formatQuoteAmount } from '@/features/quotes/quote-summary'
import type { LineTarget, PickedSuppliers } from '@/features/purchase-requests/line-status-transitions'
import { LineStatusBadge } from '@/features/purchase-requests/purchase-request-status-badge'
import { SupplierPicker } from '@/features/purchase-requests/purchase-request-supplier'
import type { NamedRef } from '@/features/purchase-requests/types'

const HEAD = 'px-2 py-1 text-left text-xs font-medium text-muted-foreground'
const NUM_HEAD = 'px-2 py-1 text-right text-xs font-medium text-muted-foreground'
const CELL = 'px-2 py-1 text-xs'
const NUM_CELL = 'px-2 py-1 text-right text-xs tabular-nums whitespace-nowrap'

/** Supplier column of the recap, shown when the chosen status needs a supplier on every line (spec 0209 D-2). */
export interface SupplierColumn {
  picked: PickedSuppliers
  onPick: (lineId: number, supplier: NamedRef | null) => void
}

interface LineStatusSummaryTableProps {
  targets: readonly LineTarget[]
  supplierColumn?: SupplierColumn
}

/** Compact recap of the lines about to change (scrolls when many; wide content scrolls sideways on phones). */
export function LineStatusSummaryTable({ targets, supplierColumn }: LineStatusSummaryTableProps) {
  const { t } = useTranslation()
  return (
    <div className="max-h-44 overflow-auto rounded-lg border border-border bg-card">
      <table className="w-full min-w-max border-collapse">
        <caption className="sr-only">{t('purchaseRequests.statusDialog.selectedLines')}</caption>
        <thead className="sticky top-0 z-10 border-b border-border bg-surface">
          <tr>
            <th scope="col" className={NUM_HEAD}>#</th>
            <th scope="col" className={HEAD}>{t('purchaseRequests.lines.description')}</th>
            {supplierColumn ? (
              <th scope="col" className={HEAD}>
                {t('purchaseRequests.lines.supplier')}
              </th>
            ) : null}
            <th scope="col" className={NUM_HEAD}>{t('purchaseRequests.lines.quantity')}</th>
            <th scope="col" className={NUM_HEAD}>{t('purchaseRequests.lines.total')}</th>
            <th scope="col" className={HEAD}>{t('purchaseRequests.statusDialog.currentStatus')}</th>
          </tr>
        </thead>
        <tbody>
          {targets.map((target) => (
            <tr key={target.id} className="border-b border-border last:border-b-0">
              <td className={NUM_CELL}>{target.id}</td>
              <td className={`${CELL} max-w-56`}>
                <div className="truncate" title={target.description}>{target.description}</div>
              </td>
              {supplierColumn ? (
                <td className={`${CELL} min-w-44`}>
                  {target.supplier ? (
                    <span>{target.supplier.name}</span>
                  ) : (
                    <SupplierPicker
                      selected={supplierColumn.picked[target.id] ?? null}
                      onSelect={(supplier) => supplierColumn.onPick(target.id, supplier)}
                      label={`${t('purchaseRequests.lines.supplier')} ${target.id}`}
                      className="h-7 min-h-7 px-2 text-xs"
                    />
                  )}
                </td>
              ) : null}
              <td className={NUM_CELL}>
                {target.quantity}
                {target.unitSymbol ? ` ${target.unitSymbol}` : ''}
              </td>
              <td className={NUM_CELL}>{formatQuoteAmount(target.totalAmount)}</td>
              <td className={CELL}>{target.status ? <LineStatusBadge status={target.status} /> : null}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
