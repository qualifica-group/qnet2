import { useTranslation } from 'react-i18next'
import { Pencil } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Amount, PaymentCell, ProductCell } from '@/features/work-order-contract-data/contract-data-cells'
import { money, plainNumber } from '@/features/work-order-contract-data/contract-data-format'
import {
  commissionHint,
  netAmountHint,
  netOfCommissionsHint,
  revenueHint,
} from '@/features/work-order-contract-data/contract-data-formulas'
import type { ContractDataLine } from '@/features/work-order-contract-data/types'

/**
 * In a container narrower than `@6xl` each row is a card (grid of label/value
 * pairs, the labels drawn from `data-label`); from `@6xl` it is a plain
 * single-height table row. The breakpoint is the table's own container (its
 * scroll wrapper), not the viewport: the table sits in a half-width column.
 */
const CELL = 'px-2 py-1.5 @6xl:table-cell @6xl:align-middle'
const NUMERIC_CELL = `${CELL} flex items-baseline justify-between gap-2 tabular-nums @6xl:w-px @6xl:text-right @6xl:whitespace-nowrap`
const LABEL = 'before:text-[11px] before:text-muted-foreground before:content-[attr(data-label)] @6xl:before:content-none'

interface ContractDataRowProps {
  line: ContractDataLine
  commissionsVisible: boolean
  canManage: boolean
  /** Multi-offer work order: each line names its own client. */
  showRegistry: boolean
  /** Bulk selection (spec 0333), only meaningful with `canManage`. */
  isSelected: boolean
  onToggleSelection: () => void
  onEdit: () => void
}

/** One product line: figures with their calculation hints, payment, and the single actions button. */
export function ContractDataRow({
  line,
  commissionsVisible,
  canManage,
  showRegistry,
  isSelected,
  onToggleSelection,
  onEdit,
}: ContractDataRowProps) {
  const { t } = useTranslation()
  const label = (key: string) => t(`workOrders.contractData.columns.${key}`)

  return (
    <tr className="relative grid grid-cols-2 gap-x-3 border-t first:border-t-0 @6xl:table-row">
      {canManage ? (
        <td className="absolute top-1.5 left-2 @6xl:static @6xl:table-cell @6xl:w-px @6xl:px-2 @6xl:py-1.5 @6xl:align-middle">
          <Checkbox
            checked={isSelected}
            onCheckedChange={onToggleSelection}
            aria-label={t('workOrders.contractData.bulk.selectLine', { product: line.product.name })}
            className="align-middle"
          />
        </td>
      ) : null}
      <th
        scope="row"
        className={`${CELL} col-span-2 pr-10 text-left font-normal @6xl:w-full @6xl:min-w-[20rem] @6xl:max-w-0 @6xl:pr-2 ${canManage ? 'pl-8 @6xl:pl-2' : ''}`}
      >
        <ProductCell line={line} showRegistry={showRegistry} />
      </th>
      <td data-label={label('quantity')} className={`${NUMERIC_CELL} ${LABEL}`}>
        {plainNumber(line.quantity)}
      </td>
      <td data-label={label('unitPrice')} className={`${NUMERIC_CELL} ${LABEL}`}>
        {money(line.unit_price)}
      </td>
      <td data-label={label('netAmount')} className={`${NUMERIC_CELL} ${LABEL}`}>
        <Amount value={line.net_amount} hint={netAmountHint(line, t)} />
      </td>
      {commissionsVisible ? (
        <>
          <td data-label={label('supplierCommission')} className={`${NUMERIC_CELL} ${LABEL}`}>
            <Amount value={line.supplier_commission?.amount ?? null} hint={commissionHint(line, t)} />
          </td>
          <td data-label={label('netOfCommissions')} className={`${NUMERIC_CELL} ${LABEL}`}>
            <Amount value={line.net_of_commissions} hint={netOfCommissionsHint(line, t)} />
          </td>
        </>
      ) : null}
      <td data-label={label('effectiveRevenue')} className={`${NUMERIC_CELL} ${LABEL} font-medium`}>
        <Amount value={line.effective_revenue} hint={revenueHint(line, commissionsVisible, t)} />
      </td>
      <td data-label={label('payment')} className={`${CELL} col-span-2 flex items-center justify-between gap-2 @6xl:min-w-[16rem] @6xl:whitespace-nowrap ${LABEL}`}>
        <PaymentCell line={line} />
      </td>
      {canManage ? (
        <td className="absolute top-1.5 right-2 @6xl:sticky @6xl:right-0 @6xl:z-10 @6xl:table-cell @6xl:bg-surface @6xl:px-2 @6xl:py-1.5 @6xl:text-right @6xl:align-middle @6xl:shadow-[-6px_0_6px_-6px_rgb(0_0_0/0.25)]">
          <Button
            type="button"
            variant="outline"
            size="icon-xs"
            className="bg-card"
            aria-label={t('workOrders.contractData.payment.edit', { product: line.product.name })}
            onClick={onEdit}
          >
            <Pencil aria-hidden="true" />
          </Button>
        </td>
      ) : null}
    </tr>
  )
}
