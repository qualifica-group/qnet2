import { useTranslation } from 'react-i18next'
import { Pencil, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { formatDate } from '@/lib/formatting/date-display'
import { formatEuro } from '@/features/invoices/invoice-format'
import type { SettlementMovement } from '@/features/commission-settlements/types'

const HEAD = 'px-2 py-1 text-left text-xs font-medium text-muted-foreground'
const NUM_HEAD = 'px-2 py-1 text-right text-xs font-medium text-muted-foreground'
const CELL = 'px-2 py-1 text-xs'
const NUM_CELL = 'px-2 py-1 text-right text-xs tabular-nums'

interface CommissionSettlementMovementsProps {
  movements: SettlementMovement[]
  /** True only for a DRAFT whose actor holds `adjust`: enables edit/delete of `can_edit` adjustments. */
  canManageAdjustments: boolean
  onEdit: (movement: SettlementMovement) => void
  onDelete: (movement: SettlementMovement) => void
}

function documentLabel(movement: SettlementMovement): string {
  return [movement.invoice?.code, movement.quote?.code].filter(Boolean).join(' / ')
}

/** Compact read-only list of the movements of a settlement; ADJUSTMENT rows editable in a draft. */
export function CommissionSettlementMovements({
  movements,
  canManageAdjustments,
  onEdit,
  onDelete,
}: CommissionSettlementMovementsProps) {
  const { t } = useTranslation()

  if (movements.length === 0) {
    return <p className="px-2 py-3 text-xs text-muted-foreground">{t('commissionSettlements.movements.empty')}</p>
  }

  const showAmount = movements.some((movement) => movement.amount !== undefined)

  return (
    <div className="overflow-x-auto rounded-lg border border-border bg-card">
      <table className="w-full min-w-max border-collapse">
        <caption className="sr-only">{t('commissionSettlements.movements.title')}</caption>
        <thead className="border-b border-border bg-surface">
          <tr>
            <th scope="col" className={HEAD}>{t('commissionSettlements.movements.accruedOn')}</th>
            <th scope="col" className={HEAD}>{t('commissionSettlements.movements.type')}</th>
            <th scope="col" className={HEAD}>{t('commissionSettlements.movements.role')}</th>
            <th scope="col" className={HEAD}>{t('commissionSettlements.movements.document')}</th>
            <th scope="col" className={HEAD}>{t('commissionSettlements.movements.product')}</th>
            <th scope="col" className={HEAD}>{t('commissionSettlements.movements.notes')}</th>
            {showAmount ? <th scope="col" className={NUM_HEAD}>{t('commissionSettlements.movements.amount')}</th> : null}
            {canManageAdjustments ? (
              <th scope="col" className={HEAD}>
                <span className="sr-only">{t('commissionSettlements.movements.actions')}</span>
              </th>
            ) : null}
          </tr>
        </thead>
        <tbody>
          {movements.map((movement) => (
            <tr key={movement.id} className="border-b border-border last:border-b-0">
              <td className={CELL}>{formatDate(movement.accrued_on)}</td>
              <td className={CELL}>{t(`enums.commission_accrual_type.${movement.type}`)}</td>
              <td className={CELL}>
                {movement.recipient_role ? t(`enums.commission_recipient_role.${movement.recipient_role}`) : ''}
              </td>
              <td className={CELL}>{documentLabel(movement)}</td>
              <td className={`${CELL} max-w-48 truncate`} title={movement.product ?? undefined}>{movement.product}</td>
              <td className={`${CELL} max-w-56 truncate`} title={movement.notes ?? undefined}>{movement.notes}</td>
              {showAmount ? (
                <td className={`${NUM_CELL} font-medium`}>{movement.amount === undefined ? '' : formatEuro(movement.amount)}</td>
              ) : null}
              {canManageAdjustments ? (
                <td className={CELL}>
                  {movement.can_edit && movement.type === 'ADJUSTMENT' ? (
                    <div className="flex items-center gap-1">
                      <Button
                        variant="ghost"
                        size="icon-xs"
                        aria-label={t('commissionSettlements.movements.edit')}
                        onClick={() => onEdit(movement)}
                      >
                        <Pencil aria-hidden="true" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="icon-xs"
                        aria-label={t('commissionSettlements.movements.delete')}
                        onClick={() => onDelete(movement)}
                      >
                        <Trash2 aria-hidden="true" />
                      </Button>
                    </div>
                  ) : null}
                </td>
              ) : null}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
