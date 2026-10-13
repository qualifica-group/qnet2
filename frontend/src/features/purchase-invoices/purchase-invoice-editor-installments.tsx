import type { UseQueryResult } from '@tanstack/react-query'
import axios, { type AxiosError } from 'axios'
import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { formatEuro } from '@/features/invoices/invoice-format'
import type { PurchaseInstallmentPreviewRow } from '@/features/purchase-invoices/types'
import { BADGE_BASE, BADGE_COLOR_CLASSES } from '@/features/table/cell-renderers'
import { formatDate } from '@/lib/formatting/date-display'
import { cn } from '@/lib/utils'

interface PurchaseInvoiceEditorInstallmentsProps {
  preview: UseQueryResult<PurchaseInstallmentPreviewRow[], AxiosError>
}

/** The server's own message of a rejected preview, when present. */
function previewErrorMessage(error: AxiosError): string | null {
  const message = axios.isAxiosError(error) ? (error.response?.data as { message?: unknown } | undefined)?.message : undefined
  return typeof message === 'string' && message !== '' ? message : null
}

/** Scadenze: sequence, due date and amount computed by the server planner (paid rows stay as they are). */
export function PurchaseInvoiceEditorInstallments({ preview }: PurchaseInvoiceEditorInstallmentsProps) {
  const { t } = useTranslation()
  const { data, error, isError, isFetching } = preview
  const hasLocked = data?.some((row) => row.locked) ?? false

  return (
    <section aria-labelledby="purchase-invoice-editor-installments" className="grid gap-2 rounded-lg border bg-card p-3">
      <h3 id="purchase-invoice-editor-installments" className="text-sm font-semibold">
        {t('purchaseInvoices.editor.sections.installments')}
      </h3>
      {isError ? (
        <p role="alert" className="text-xs text-destructive">
          {previewErrorMessage(error) ?? t('purchaseInvoices.editor.installments.error')}
        </p>
      ) : data && data.length > 0 ? (
        <table className="w-full text-xs" aria-busy={isFetching}>
          <thead>
            <tr className="border-b text-left text-muted-foreground">
              <th scope="col" className="py-1 pr-2 font-medium">{t('purchaseInvoices.editor.installments.sequence')}</th>
              <th scope="col" className="px-2 py-1 font-medium">{t('purchaseInvoices.editor.installments.dueDate')}</th>
              <th scope="col" className="px-2 py-1 text-right font-medium">{t('purchaseInvoices.editor.installments.amount')}</th>
              {hasLocked ? (
                <th scope="col" className="py-1 pl-2 text-right font-medium">{t('purchaseInvoices.editor.installments.status')}</th>
              ) : null}
            </tr>
          </thead>
          <tbody>
            {data.map((row) => (
              <tr key={row.sequence} className={cn('border-b last:border-0', row.locked && 'bg-muted/40')}>
                <td className="py-1 pr-2">{row.sequence}</td>
                <td className="px-2 py-1">{formatDate(row.due_date)}</td>
                <td className="px-2 py-1 text-right tabular-nums">{formatEuro(row.amount)}</td>
                {hasLocked ? (
                  <td className="py-1 pl-2 text-right">
                    {row.locked ? (
                      <Badge variant="secondary" className={cn(BADGE_BASE, BADGE_COLOR_CLASSES.green)}>
                        {t('purchaseInvoices.editor.installments.paidBadge', { amount: formatEuro(row.paid_amount) })}
                      </Badge>
                    ) : null}
                  </td>
                ) : null}
              </tr>
            ))}
          </tbody>
        </table>
      ) : (
        <p className="text-xs text-muted-foreground">
          {isFetching ? t('purchaseInvoices.editor.installments.loading') : t('purchaseInvoices.editor.installments.empty')}
        </p>
      )}
    </section>
  )
}
