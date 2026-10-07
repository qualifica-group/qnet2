import { useTranslation } from 'react-i18next'
import { Pencil } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { money, plainNumber } from '@/features/work-order-contract-data/contract-data-format'
import { netOfCommissionsFormula, revenueFormula } from '@/features/work-order-contract-data/contract-data-formulas'
import { ContractDataWarnings } from '@/features/work-order-contract-data/contract-data-warnings'
import { ContractPaymentEditor } from '@/features/work-order-contract-data/contract-payment-editor'
import { PaymentStatusLabel } from '@/features/work-order-contract-data/payment-status-label'
import type { ContractDataLine } from '@/features/work-order-contract-data/types'

const CELL = 'px-2 py-1.5'
const NUMERIC_CELL = `${CELL} text-right tabular-nums whitespace-nowrap`

interface PaymentCellProps {
  line: ContractDataLine
  canManage: boolean
  isEditing: boolean
  onEdit: () => void
}

function PaymentCell({ line, canManage, isEditing, onEdit }: PaymentCellProps) {
  const { t } = useTranslation()
  const { status, payment_agreement: agreement, has_unpaid: hasUnpaid } = line.payment

  return (
    <td className={`${CELL} min-w-44`}>
      <div className="flex items-start justify-between gap-1">
        <div className="flex min-w-0 flex-col gap-0.5">
          {status ? (
            <PaymentStatusLabel status={status} />
          ) : (
            <span className="text-muted-foreground">{t('workOrders.contractData.payment.noStatus')}</span>
          )}
          {agreement ? (
            <span className="max-w-56 truncate text-[11px] text-muted-foreground" title={agreement}>
              {agreement}
            </span>
          ) : null}
          <span className="text-[11px] text-muted-foreground">
            {t('workOrders.contractData.payment.unpaid')}:{' '}
            {hasUnpaid ? t('common.yes') : t('common.no')}
          </span>
        </div>
        {canManage && !isEditing ? (
          <Button
            type="button"
            variant="ghost"
            size="icon-xs"
            aria-label={t('workOrders.contractData.payment.edit', { product: line.product.name })}
            onClick={onEdit}
          >
            <Pencil aria-hidden="true" />
          </Button>
        ) : null}
      </div>
    </td>
  )
}

interface ContractDataRowProps {
  workOrderId: number
  line: ContractDataLine
  commissionsVisible: boolean
  /** Total number of columns, for the full-width rows under the line. */
  columnCount: number
  canManage: boolean
  isEditing: boolean
  onEdit: () => void
  onCloseEditor: () => void
}

/**
 * One product line of the contract data table: the figures, the readable
 * formula (with warnings) under them and, while editing, the payment editor.
 */
export function ContractDataRow({
  workOrderId,
  line,
  commissionsVisible,
  columnCount,
  canManage,
  isEditing,
  onEdit,
  onCloseEditor,
}: ContractDataRowProps) {
  const { t } = useTranslation()
  const netFormula = netOfCommissionsFormula(line, t)

  return (
    <>
      <tr className="border-t align-top">
        <th scope="row" className={`${CELL} min-w-40 text-left font-normal`}>
          <div className="flex flex-col gap-1">
            <span>
              <span className="font-mono text-muted-foreground">{line.product.code}</span> {line.product.name}
            </span>
            <Badge variant={line.supplier_commission_direction === 'RECEIVED' ? 'secondary' : 'outline'} className="w-fit text-[11px]">
              {line.typology?.name ?? t('workOrders.contractData.kind.consultancy')}
            </Badge>
          </div>
        </th>
        <td className={NUMERIC_CELL}>{plainNumber(line.quantity)}</td>
        <td className={NUMERIC_CELL}>{money(line.unit_price)}</td>
        <td className={NUMERIC_CELL}>{money(line.net_amount)}</td>
        {commissionsVisible ? (
          <>
            <td className={NUMERIC_CELL}>
              {line.supplier_commission ? money(line.supplier_commission.amount) : '—'}
            </td>
            <td className={NUMERIC_CELL}>{line.net_of_commissions ? money(line.net_of_commissions) : '—'}</td>
          </>
        ) : null}
        <td className={`${NUMERIC_CELL} font-medium`}>{money(line.effective_revenue)}</td>
        <PaymentCell line={line} canManage={canManage} isEditing={isEditing} onEdit={onEdit} />
      </tr>
      <tr>
        <td colSpan={columnCount} className="px-2 pb-1.5 text-[11px] text-muted-foreground">
          <p>{revenueFormula(line, commissionsVisible, t)}</p>
          {netFormula ? <p>{netFormula}</p> : null}
          <ContractDataWarnings warnings={line.warnings} />
        </td>
      </tr>
      {isEditing ? (
        <tr>
          <td colSpan={columnCount} className="border-t bg-muted/40 px-2 py-2">
            <ContractPaymentEditor workOrderId={workOrderId} line={line} onClose={onCloseEditor} />
          </td>
        </tr>
      ) : null}
    </>
  )
}
