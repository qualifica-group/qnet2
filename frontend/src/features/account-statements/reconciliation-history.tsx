import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Plus, Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useConfirm } from '@/components/confirm-dialog-context'
import { Can } from '@/features/auth/can'
import { formatEuro } from '@/features/invoices/invoice-format'
import { ReconciliationDialog } from '@/features/account-statements/reconciliation-dialog'
import { useDeleteReconciliation, useReconciliations } from '@/features/account-statements/use-reconciliation'

const CELL = 'px-2 py-1.5'
const NUMERIC = `${CELL} text-right tabular-nums whitespace-nowrap`

/** Saved bank reconciliations of the account, with "New reconciliation" and delete (permission `reconcile`). */
export function ReconciliationHistory({ accountId }: { accountId: number }) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const [dialogOpen, setDialogOpen] = useState(false)
  const { data, isError } = useReconciliations(accountId)
  const remove = useDeleteReconciliation()

  const requestDelete = async (id: number) => {
    const confirmed = await confirm({
      tone: 'destructive',
      title: t('accountStatements.reconciliation.deleteConfirm.title'),
      description: t('accountStatements.reconciliation.deleteConfirm.description'),
    })
    if (confirmed) {
      remove.mutate(id)
    }
  }

  return (
    <section
      className="flex flex-col gap-2 rounded-lg border bg-surface p-3"
      aria-label={t('accountStatements.reconciliation.title')}
    >
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 className="text-sm font-semibold">{t('accountStatements.reconciliation.title')}</h2>
        <Can permission="treasury-movements.reconcile">
          <Button type="button" size="sm" onClick={() => setDialogOpen(true)}>
            <Plus className="size-3.5" aria-hidden="true" />
            {t('accountStatements.reconciliation.new')}
          </Button>
        </Can>
      </div>
      {isError ? (
        <p role="alert" className="text-xs text-destructive">{t('accountStatements.loadError')}</p>
      ) : data && data.length === 0 ? (
        <p className="text-xs text-muted-foreground">{t('accountStatements.reconciliation.empty')}</p>
      ) : data ? (
        <div className="overflow-x-auto rounded-md border bg-card">
          <table className="w-full min-w-[40rem] text-xs">
            <thead className="bg-surface text-muted-foreground">
              <tr className="text-left">
                <th scope="col" className={CELL}>{t('accountStatements.reconciliation.columns.date')}</th>
                <th scope="col" className={`${CELL} text-right`}>{t('accountStatements.reconciliation.columns.bank')}</th>
                <th scope="col" className={`${CELL} text-right`}>{t('accountStatements.reconciliation.columns.reconciled')}</th>
                <th scope="col" className={`${CELL} text-right`}>{t('accountStatements.reconciliation.columns.difference')}</th>
                <th scope="col" className={CELL}>{t('accountStatements.reconciliation.columns.note')}</th>
                <th scope="col" className={CELL}>{t('accountStatements.reconciliation.columns.createdBy')}</th>
                <th scope="col" className={CELL}>
                  <span className="sr-only">{t('accountStatements.reconciliation.columns.actions')}</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {data.map((item) => (
                <tr key={item.id} className="border-t">
                  <td className={`${CELL} whitespace-nowrap`}>{item.statement_date}</td>
                  <td className={NUMERIC}>{formatEuro(item.bank_balance)}</td>
                  <td className={NUMERIC}>{formatEuro(item.reconciled_balance)}</td>
                  <td className={NUMERIC}>
                    {formatEuro(item.difference)}
                    {item.adjustment_movement_id !== null ? (
                      <span className="ml-1 text-muted-foreground">({t('accountStatements.reconciliation.adjusted')})</span>
                    ) : null}
                  </td>
                  <td className={`${CELL} max-w-48 truncate`} title={item.note ?? ''}>{item.note ?? ''}</td>
                  <td className={CELL}>{item.created_by?.name ?? ''}</td>
                  <td className={CELL}>
                    <Can permission="treasury-movements.reconcile">
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon-xs"
                        aria-label={t('accountStatements.reconciliation.delete', { date: item.statement_date })}
                        disabled={remove.isPending}
                        onClick={() => void requestDelete(item.id)}
                      >
                        <Trash2 aria-hidden="true" />
                      </Button>
                    </Can>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}
      <ReconciliationDialog accountId={accountId} open={dialogOpen} onClose={() => setDialogOpen(false)} />
    </section>
  )
}
