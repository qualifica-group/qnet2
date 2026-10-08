import { useTranslation } from 'react-i18next'
import { Pencil } from 'lucide-react'
import { Button } from '@/components/ui/button'
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
 * Below `md` each row is a card (grid of label/value pairs, the labels drawn
 * from `data-label`); from `md` it is a plain single-height table row.
 */
const CELL = 'px-2 py-1.5 md:table-cell md:align-middle'
const NUMERIC_CELL = `${CELL} flex items-baseline justify-between gap-2 tabular-nums md:w-px md:text-right md:whitespace-nowrap`
const LABEL = 'before:text-[11px] before:text-muted-foreground before:content-[attr(data-label)] md:before:content-none'

interface ContractDataRowProps {
  line: ContractDataLine
  commissionsVisible: boolean
  canManage: boolean
  onEdit: () => void
}

/** One product line: figures with their calculation hints, payment, and the single actions button. */
export function ContractDataRow({ line, commissionsVisible, canManage, onEdit }: ContractDataRowProps) {
  const { t } = useTranslation()
  const label = (key: string) => t(`workOrders.contractData.columns.${key}`)

  return (
    <tr className="relative grid grid-cols-2 gap-x-3 border-t first:border-t-0 md:table-row">
      <th scope="row" className={`${CELL} col-span-2 pr-10 text-left font-normal md:w-full md:min-w-[20rem] md:max-w-0 md:pr-2`}>
        <ProductCell line={line} />
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
      <td data-label={label('payment')} className={`${CELL} col-span-2 flex items-center justify-between gap-2 md:min-w-[16rem] md:whitespace-nowrap ${LABEL}`}>
        <PaymentCell line={line} />
      </td>
      {canManage ? (
        <td className="absolute top-1.5 right-2 md:sticky md:right-0 md:z-10 md:table-cell md:bg-surface md:px-2 md:py-1.5 md:text-right md:align-middle md:shadow-[-6px_0_6px_-6px_rgb(0_0_0/0.25)]">
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
