import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { Scale } from 'lucide-react'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { formatDate } from '@/lib/formatting/date-display'
import { Button } from '@/components/ui/button'
import { useConfirm } from '@/components/confirm-dialog-context'
import { useAbilities } from '@/features/auth/use-abilities'
import { CompensationDialog } from '@/features/compensations/compensation-dialog'
import type { CompensationTarget, InstallmentCompensation } from '@/features/compensations/types'
import { useDeleteCompensation, useRegistryCompensations } from '@/features/compensations/use-compensation-queries'
import { formatEuro } from '@/features/invoices/invoice-format'

/** Both abilities the server requires to register or undo a compensation (spec 0364 D-5). */
const COLLECT_ABILITY = 'invoices.collect'
const PAY_ABILITY = 'purchase-invoices.pay'

const HEAD_CELL = 'px-2 py-1 text-left text-xs font-medium text-muted-foreground'
const NUMERIC_HEAD_CELL = 'px-2 py-1 text-right text-xs font-medium text-muted-foreground'
const CELL = 'px-2 py-1 text-xs'
const NUMERIC_CELL = 'px-2 py-1 text-right text-xs tabular-nums'

function CompensationRow({ compensation }: { compensation: InstallmentCompensation }) {
  const { t, i18n } = useTranslation()
  const confirm = useConfirm()
  const { mutate, isPending } = useDeleteCompensation()
  const receivable = `${compensation.invoice_installment.invoice.number_label} / ${compensation.invoice_installment.sequence}`
  const payable = `${compensation.purchase_invoice_installment.purchase_invoice.number_label} / ${compensation.purchase_invoice_installment.sequence}`

  const requestUndo = async () => {
    const confirmed = await confirm({
      tone: 'destructive',
      title: t('compensations.list.undoTitle'),
      description: t('compensations.list.undoDescription', { receivable, payable }),
      confirmLabel: t('compensations.list.undo'),
    })
    if (!confirmed) {
      return
    }
    mutate(compensation.id, {
      onSuccess: () => toast.success(t('compensations.list.undone')),
      onError: (error) => toast.error(apiErrorMessage(error, t('compensations.list.undoError'), i18n)),
    })
  }

  return (
    <tr className="border-t">
      <td className={CELL}>{formatDate(compensation.compensated_at)}</td>
      <td className={CELL}>{compensation.company.name}</td>
      <td className={CELL}>{receivable}</td>
      <td className={CELL}>{payable}</td>
      <td className={NUMERIC_CELL}>{formatEuro(Number(compensation.amount))}</td>
      <td className={`${CELL} max-w-48 truncate`}>{compensation.note ?? '-'}</td>
      <td className={CELL}>
        {compensation.can_delete ? (
          <Button type="button" size="xs" variant="secondary" disabled={isPending} onClick={() => void requestUndo()}>
            {t('compensations.list.undo')}
          </Button>
        ) : null}
      </td>
    </tr>
  )
}

/** Compensa button (only with both abilities) and the list of the registry's compensations with Annulla. */
export function LedgerCompensations({ registryId }: { registryId: number }) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const [target, setTarget] = useState<CompensationTarget | null>(null)
  const { data } = useRegistryCompensations(registryId, true)
  const compensations = Array.isArray(data) ? data : []
  const canCompensate = can(COLLECT_ABILITY) && can(PAY_ABILITY)

  return (
    <section aria-label={t('compensations.list.title')} className="flex flex-col gap-2">
      <div className="flex items-center justify-between gap-2">
        <h3 className="text-base font-semibold">{t('compensations.list.title')}</h3>
        {canCompensate ? (
          <Button type="button" size="sm" variant="secondary" onClick={() => setTarget({ registryId })}>
            <Scale aria-hidden="true" />
            {t('compensations.action')}
          </Button>
        ) : null}
      </div>
      {compensations.length > 0 ? (
        <div className="overflow-x-auto rounded-lg border bg-card">
          <table className="w-full min-w-[40rem] border-collapse">
            <caption className="sr-only">{t('compensations.list.title')}</caption>
            <thead>
              <tr>
                <th scope="col" className={HEAD_CELL}>{t('compensations.list.date')}</th>
                <th scope="col" className={HEAD_CELL}>{t('compensations.list.company')}</th>
                <th scope="col" className={HEAD_CELL}>{t('compensations.list.receivable')}</th>
                <th scope="col" className={HEAD_CELL}>{t('compensations.list.payable')}</th>
                <th scope="col" className={NUMERIC_HEAD_CELL}>{t('compensations.list.amount')}</th>
                <th scope="col" className={HEAD_CELL}>{t('compensations.list.note')}</th>
                <th scope="col" className={HEAD_CELL}>
                  <span className="sr-only">{t('compensations.list.actions')}</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {compensations.map((compensation) => (
                <CompensationRow key={compensation.id} compensation={compensation} />
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <p className="text-xs text-muted-foreground">{t('compensations.list.empty')}</p>
      )}
      <CompensationDialog target={target} onClose={() => setTarget(null)} />
    </section>
  )
}
